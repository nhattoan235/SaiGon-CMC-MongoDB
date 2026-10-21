<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleDriveConnection
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public function __construct(private BackupRepository $repository) {}

    public function configured(): bool
    {
        return (bool) config('backup.google.client_id') && (bool) config('backup.google.client_secret')
            && config('backup.no_billing_confirmed') === true;
    }

    public function authorizationUrl(): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('Cần OAuth client và xác nhận project Google không liên kết Billing.');
        }
        $redirect = (string) config('backup.google.redirect');
        $parts = parse_url($redirect);
        if (! $parts || ! isset($parts['host']) || ($parts['scheme'] !== 'https' && ! in_array($parts['host'], ['127.0.0.1', 'localhost'], true))) {
            throw new RuntimeException('Redirect URI phải là HTTPS hoặc localhost.');
        }
        $state = Str::random(64);
        session()->put('backup_google_state', ['hash' => hash('sha256', $state), 'expires' => now()->addMinutes(10)->timestamp,
            'admin' => session('google_backup_admin')]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('backup.google.client_id'), 'redirect_uri' => $redirect,
            'response_type' => 'code', 'scope' => self::SCOPE, 'state' => $state,
            'access_type' => 'offline', 'prompt' => 'consent',
        ]);
    }

    public function callback(string $code, string $state): void
    {
        $expected = session()->pull('backup_google_state');
        if (! is_array($expected) || ($expected['expires'] ?? 0) < now()->timestamp
            || ! hash_equals($expected['hash'], hash('sha256', $state))
            || $expected['admin'] !== session('google_backup_admin') || ! $this->configured()) {
            throw new RuntimeException('Phiên kết nối Google không hợp lệ hoặc đã hết hạn.');
        }
        $this->repository->locked('google', function () use ($code): void {
            $token = $this->token(['code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => config('backup.google.redirect')]);
            $scopes = explode(' ', $token['scope'] ?? '');
            if (! in_array(self::SCOPE, $scopes, true) || empty($token['refresh_token'])) {
                throw new RuntimeException('Google chưa cấp đủ quyền chạy nền; hãy kết nối lại.');
            }
            $about = Http::withToken($token['access_token'])->timeout(20)->connectTimeout(5)->withOptions(['allow_redirects' => false])
                ->get('https://www.googleapis.com/drive/v3/about', ['fields' => 'user(permissionId,emailAddress)']);
            if (! $about->successful() || ! $about->json('user.permissionId')) {
                throw new RuntimeException('Không xác minh được tài khoản Google.');
            }
            $previous = $this->read();
            $identity = (string) $about->json('user.permissionId');
            $this->save(['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'],
                'expires_at' => now()->timestamp + (int) ($token['expires_in'] ?? 3600), 'owner' => $identity,
                'email' => $about->json('user.emailAddress'), 'client_id' => config('backup.google.client_id'),
                'folder' => ($previous['owner'] ?? null) === $identity ? ($previous['folder'] ?? null) : null]);
        });
    }

    public function accessToken(): string
    {
        return $this->repository->locked('google', function (): string {
            $data = $this->read();
            if (! $this->configured() || empty($data['refresh_token']) || ($data['reconnect'] ?? false)
                || ($data['client_id'] ?? '') !== config('backup.google.client_id')) {
                throw new RuntimeException('Cần kết nối lại Google Drive.');
            }
            if (($data['expires_at'] ?? 0) <= now()->timestamp + 60) {
                try {
                    $token = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $data['refresh_token']]);
                } catch (\DomainException $exception) {
                    $this->save(array_replace($data, ['reconnect' => true]));
                    throw $exception;
                }
                $data['access_token'] = $token['access_token'];
                $data['refresh_token'] = $token['refresh_token'] ?? $data['refresh_token'];
                $data['expires_at'] = now()->timestamp + (int) ($token['expires_in'] ?? 3600);
                $this->save($data);
            }

            return $data['access_token'];
        });
    }

    public function identity(): string
    {
        $this->accessToken();

        return $this->read()['owner'];
    }

    public function folder(): string
    {
        $token = $this->accessToken();

        return $this->repository->locked('google-folder', function () use ($token): string {
            $data = $this->read();
            if (empty($data['folder'])) {
                $response = Http::withToken($token)->timeout(20)->connectTimeout(5)->withOptions(['allow_redirects' => false])
                    ->post('https://www.googleapis.com/drive/v3/files', ['name' => 'SaiGon-CMC-Backups-'.config('backup.instance'),
                        'mimeType' => 'application/vnd.google-apps.folder',
                        'appProperties' => ['instance' => config('backup.instance'), 'kind' => 'backup-folder']]);
                if (! $response->successful() || ! $response->json('id')) {
                    throw new RuntimeException('Không tạo được thư mục backup riêng trên Drive.');
                }
                $data['folder'] = $response->json('id');
                $this->save($data);
            }
            $response = Http::withToken($token)->timeout(20)->connectTimeout(5)->withOptions(['allow_redirects' => false])
                ->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($data['folder']),
                    ['fields' => 'id,trashed,mimeType,appProperties,permissions(type,role)']);
            if (! $response->successful() || $response->json('trashed') || $response->json('appProperties.instance') !== config('backup.instance')
                || $response->json('mimeType') !== 'application/vnd.google-apps.folder') {
                throw new RuntimeException('Thư mục backup mất quyền hoặc không còn hợp lệ.');
            }
            foreach ($response->json('permissions', []) as $permission) {
                if (in_array($permission['type'] ?? '', ['anyone', 'domain'], true)) {
                    throw new RuntimeException('Thư mục backup đang được chia sẻ rộng; hãy chuyển về riêng tư.');
                }
            }

            return $data['folder'];
        });
    }

    public function status(): array
    {
        try {
            $data = $this->read();

            return ['configured' => $this->configured(), 'connected' => $this->configured() && ! empty($data['refresh_token'])
                && ! ($data['reconnect'] ?? false) && ($data['client_id'] ?? '') === config('backup.google.client_id'),
                'email' => $data['email'] ?? null, 'owner' => $data['owner'] ?? null];
        } catch (\Throwable $exception) {
            return ['configured' => $this->configured(), 'connected' => false, 'email' => null];
        }
    }

    public function disconnect(): void
    {
        $this->repository->locked('google', function (): void {
            $data = $this->read();
            if (! empty($data['refresh_token'])) {
                try {
                    Http::asForm()->timeout(10)->connectTimeout(5)->withOptions(['allow_redirects' => false])
                        ->post('https://oauth2.googleapis.com/revoke', ['token' => $data['refresh_token']]);
                } catch (\Throwable $exception) {
                    throw new RuntimeException('Chưa thu hồi được quyền Google. Hãy thử lại hoặc thu hồi trong tài khoản Google.');
                }
            }
            @unlink($this->repository->root().'/google.enc');
        });
    }

    private function token(array $parameters): array
    {
        try {
            $response = Http::asForm()->timeout(20)->connectTimeout(5)->withOptions(['allow_redirects' => false])
                ->post('https://oauth2.googleapis.com/token', array_merge($parameters, [
                    'client_id' => config('backup.google.client_id'), 'client_secret' => config('backup.google.client_secret')]));
        } catch (\Throwable $exception) {
            throw new RuntimeException('Không kết nối được Google; thử lại sau.');
        }
        if ($response->json('error') === 'invalid_grant') {
            throw new \DomainException('Google đã hết hạn/thu hồi quyền. Cần kết nối lại.');
        }
        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Google từ chối xác thực; kiểm tra OAuth client và redirect URI.');
        }

        return $response->json();
    }

    private function read(): array
    {
        $path = $this->repository->root().'/google.enc';

        return is_file($path) ? json_decode(Crypt::decryptString(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR) : [];
    }

    private function save(array $data): void
    {
        $path = $this->repository->root().'/google.enc';
        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        file_put_contents($temporary, Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)), LOCK_EX);
        @chmod($temporary, 0600);
        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Không lưu được kết nối Google.');
        }
    }
}
