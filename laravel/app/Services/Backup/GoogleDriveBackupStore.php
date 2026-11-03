<?php

namespace App\Services\Backup;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleDriveBackupStore
{
    private const API = 'https://www.googleapis.com/drive/v3/';

    public function __construct(private GoogleDriveConnection $connection, private BackupRepository $repository) {}

    public function upload(array $run, string $artifact): array
    {
        if (filesize($artifact) > config('backup.max_artifact_bytes') || hash_file('sha256', $artifact) !== $run['sha256']) {
            throw new \DomainException('Backup vượt giới hạn 512 MB hoặc hash local không đúng; không tải lên.');
        }
        $owner = $this->connection->identity();
        $folder = $this->connection->folder();
        if (isset($run['cloud_owner']) && $run['cloud_owner'] !== $owner) {
            throw new \DomainException('Backup thuộc tài khoản Google khác; kết nối lại đúng tài khoản trước retry.');
        }
        $properties = ['instance' => config('backup.instance'), 'backup' => $run['id'], 'kind' => 'artifact'];
        if (empty($run['cloud_id'])) {
            $ids = $this->request('GET', self::API.'files/generateIds', ['count' => 1, 'space' => 'drive', 'type' => 'files'])->json('ids');
            if (empty($ids[0])) {
                throw new RuntimeException('Không tạo được Drive file ID.');
            }
            $run = $this->repository->update($run['id'], ['cloud_id' => $ids[0], 'cloud_owner' => $owner, 'cloud_folder' => $folder]);
        }
        if (($run['cloud_folder'] ?? '') !== $folder) {
            throw new \DomainException('Thư mục Google đã thay đổi; không retry vào namespace khác.');
        }
        $id = rawurlencode($run['cloud_id']);
        $existing = $this->request('GET', self::API.'files/'.$id, ['fields' => 'id,size,appProperties,parents,trashed'], true);
        if ($existing->status() !== 404) {
            $this->owned($existing->json(), $run, 'artifact');
            if ((int) $existing->json('size') !== (int) $run['size']) {
                throw new \DomainException('File ID đã tồn tại nhưng kích thước khác; giữ nguyên để kiểm tra.');
            }
        } else {
            $offset = 0;
            $location = null;
            if (! empty($run['upload_session_enc'])) {
                $location = Crypt::decryptString($run['upload_session_enc']);
                $this->validateSession($location);
                $this->budget();
                $probe = Http::withToken($this->connection->accessToken())->timeout(30)->connectTimeout(5)
                    ->withOptions(['allow_redirects' => false])->withHeaders(['Content-Range' => 'bytes */'.$run['size']])
                    ->withBody('', 'application/octet-stream')->put($location);
                if ($probe->successful()) {
                    $offset = (int) $run['size'];
                } elseif ($probe->status() === 308) {
                    $range = $probe->header('Range');
                    if ($range !== null && ! preg_match('/^bytes=0-(\d+)$/D', $range, $match)) {
                        throw new RuntimeException('Offset phiên upload không hợp lệ.');
                    }
                    $offset = $range === null ? 0 : ((int) $match[1] + 1);
                    if ($offset > $run['size']) {
                        throw new RuntimeException('Offset vượt kích thước backup.');
                    }
                } elseif ($probe->status() === 404) {
                    $location = null;
                } else {
                    $this->failure($probe);
                }
            }
            if ($location === null) {
                $start = $this->request('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', [
                    'id' => $run['cloud_id'], 'name' => $run['id'].'.cmcbackup', 'parents' => [$folder], 'appProperties' => $properties]);
                $location = $start->header('Location') ?? '';
                $this->validateSession($location);
                $run = $this->repository->update($run['id'], ['upload_session_enc' => Crypt::encryptString($location)]);
            }
            $input = fopen($artifact, 'rb');
            try {
                if (fseek($input, $offset) !== 0) {
                    throw new RuntimeException('Không seek được file backup.');
                }
                while ($offset < $run['size']) {
                    $chunk = fread($input, 4 * 256 * 1024);
                    if ($chunk === false || $chunk === '') {
                        throw new RuntimeException('Không đọc được file backup để upload.');
                    }
                    $this->budget(strlen($chunk));
                    $response = Http::withToken($this->connection->accessToken())->timeout(60)->connectTimeout(5)
                        ->withOptions(['allow_redirects' => false])->withHeaders([
                            'Content-Range' => 'bytes '.$offset.'-'.($offset + strlen($chunk) - 1).'/'.$run['size']])
                        ->withBody($chunk, 'application/octet-stream')->put($location);
                    if ($response->status() !== 308 && ! $response->successful()) {
                        $this->failure($response);
                    }
                    $offset += strlen($chunk);
                    if ($response->status() === 308 && $response->header('Range') !== 'bytes=0-'.($offset - 1)) {
                        throw new RuntimeException('Offset upload Google không khớp; sẽ kiểm tra lại cùng file ID.');
                    }
                    if ($response->successful() && $offset < $run['size']) {
                        throw new RuntimeException('Upload kết thúc sớm; chưa xác minh cloud.');
                    }
                }
            } finally {
                fclose($input);
            }
        }
        $this->repository->update($run['id'], ['state' => 'verifying']);
        $temporary = $this->repository->path($run['id']).'/readback.tmp';
        try {
            $this->download($run, $temporary);
            if (filesize($temporary) !== (int) $run['size'] || ! hash_equals($run['sha256'], hash_file('sha256', $temporary))) {
                throw new \DomainException('Hash bản cloud không khớp; không ghi thành công.');
            }
        } finally {
            @unlink($temporary);
        }
        $markerData = ['id' => $run['id'], 'capture_at' => $run['capture_at'] ?? null, 'sha256' => $run['sha256'],
            'size' => $run['size'], 'key_id' => $run['key_id'] ?? null, 'cloud_id' => $run['cloud_id'], 'cloud_owner' => $owner];
        if (empty($run['cloud_marker'])) {
            $response = $this->request('POST', self::API.'files', ['name' => $run['id'].'.completed.json', 'parents' => [$folder],
                'mimeType' => 'application/json', 'description' => json_encode($markerData, JSON_THROW_ON_ERROR),
                'appProperties' => array_replace($properties, ['kind' => 'completed'])]);
            if (! $response->json('id')) {
                throw new RuntimeException('Không tạo được commit marker.');
            }
            $run = $this->repository->update($run['id'], ['cloud_marker' => $response->json('id')]);
        }

        return ['cloud_id' => $run['cloud_id'], 'cloud_marker' => $run['cloud_marker'], 'cloud_owner' => $owner, 'cloud_folder' => $folder];
    }

    public function download(array $run, string $destination): void
    {
        if (($run['cloud_owner'] ?? '') !== $this->connection->identity()) {
            throw new \DomainException('Cần kết nối đúng tài khoản sở hữu backup.');
        }
        $this->budget((int) ($run['size'] ?? 0));
        try {
            $response = Http::withToken($this->connection->accessToken())->timeout(120)->connectTimeout(5)
                ->withOptions(['allow_redirects' => false, 'sink' => $destination])
                ->get(self::API.'files/'.rawurlencode($run['cloud_id']), ['alt' => 'media']);
            if (! $response->successful()) {
                $this->failure($response);
            }
            // Laravel HTTP fakes do not implement Guzzle's sink option.
            if (! is_file($destination) && app()->environment('testing')) {
                file_put_contents($destination, $response->body());
            }
        } catch (\Throwable $exception) {
            @unlink($destination);
            if ($exception instanceof \DomainException) {
                throw $exception;
            }
            throw new RuntimeException('Tải/kiểm tra Drive chưa hoàn tất; sẽ thử lại có giới hạn.');
        }
    }

    public function completed(): array
    {
        $folder = $this->connection->folder();
        $owner = $this->connection->identity();
        $instance = $this->escape((string) config('backup.instance'));
        $query = "'".$this->escape($folder)."' in parents and trashed = false and appProperties has { key='instance' and value='".$instance."' } and appProperties has { key='kind' and value='completed' }";
        $result = [];
        $seenIds = [];
        $seenArtifacts = [];
        $page = null;
        do {
            $data = $this->request('GET', self::API.'files', array_filter(['q' => $query, 'fields' => 'nextPageToken,files(id,description,appProperties,parents,trashed)', 'pageSize' => 100, 'pageToken' => $page]))->json();
            foreach ($data['files'] ?? [] as $file) {
                $marker = json_decode($file['description'] ?? '', true);
                if (! is_array($marker) || ($marker['cloud_owner'] ?? '') !== $owner
                    || ! Str::isUuid($marker['id'] ?? '')
                    || ! preg_match('/^[a-zA-Z0-9_-]+$/D', $marker['cloud_id'] ?? '')
                    || ! preg_match('/^[a-f0-9]{64}$/D', $marker['sha256'] ?? '')
                    || ! preg_match('/^[a-f0-9]{16}$/D', $marker['key_id'] ?? '')
                    || ! is_numeric($marker['size'] ?? null) || $marker['size'] <= 0 || $marker['size'] > config('backup.max_artifact_bytes')
                    || empty($marker['capture_at']) || isset($seenIds[$marker['id']]) || isset($seenArtifacts[$marker['cloud_id']])) {
                    continue;
                }
                $marker = array_replace($marker, ['cloud_marker' => $file['id'], 'cloud_folder' => $folder]);
                try {
                    Carbon::parse($marker['capture_at']);
                    $this->owned($file, $marker, 'completed');
                    $artifact = $this->request('GET', self::API.'files/'.rawurlencode($marker['cloud_id']),
                        ['fields' => 'id,size,appProperties,parents,trashed,sha256Checksum'], true);
                    if ($artifact->status() === 404) {
                        continue;
                    }
                    $this->owned($artifact->json(), $marker, 'artifact');
                    if ((int) $artifact->json('size') !== (int) $marker['size']
                        || ($artifact->json('sha256Checksum') && ! hash_equals($marker['sha256'], $artifact->json('sha256Checksum')))) {
                        continue;
                    }
                } catch (\DomainException|InvalidFormatException $exception) {
                    continue;
                }
                $seenIds[$marker['id']] = true;
                $seenArtifacts[$marker['cloud_id']] = true;
                $result[] = $marker;
            }
            $page = $data['nextPageToken'] ?? null;
        } while ($page !== null);

        return $result;
    }

    public function delete(array $run): void
    {
        if (($run['cloud_owner'] ?? '') !== $this->connection->identity() || ($run['cloud_folder'] ?? '') !== $this->connection->folder()) {
            throw new \DomainException('Từ chối xóa backup ngoài tài khoản/thư mục hiện tại.');
        }
        foreach (['cloud_id' => 'artifact', 'cloud_marker' => 'completed'] as $field => $kind) {
            if (empty($run[$field])) {
                continue;
            }
            $url = self::API.'files/'.rawurlencode($run[$field]);
            $file = $this->request('GET', $url, ['fields' => 'id,appProperties,parents,trashed'], true);
            if ($file->status() === 404) {
                continue;
            }
            $this->owned($file->json(), $run, $kind);
            $this->request('DELETE', $url);
        }
    }

    private function owned(array $file, array $run, string $kind): void
    {
        if (($file['appProperties']['instance'] ?? '') !== config('backup.instance')
            || ($file['appProperties']['backup'] ?? '') !== $run['id'] || ($file['appProperties']['kind'] ?? '') !== $kind
            || ! in_array($run['cloud_folder'], $file['parents'] ?? [], true) || ($file['trashed'] ?? false)) {
            throw new \DomainException('File Drive không thuộc bản backup/thư mục đã ghi nhận.');
        }
    }

    private function request(string $method, string $url, array $data = [], bool $allowNotFound = false): Response
    {
        $this->budget();
        try {
            $request = Http::withToken($this->connection->accessToken())->timeout(30)->connectTimeout(5)->withOptions(['allow_redirects' => false]);
            $response = match ($method) {
                'GET' => $request->get($url, $data),
                'POST' => $request->post($url, $data),
                'DELETE' => $request->delete($url),
            };
        } catch (\Throwable $exception) {
            throw new RuntimeException('Mất kết nối Google Drive; thử lại có giới hạn.');
        }
        if (! $response->successful() && ! ($allowNotFound && $response->status() === 404)) {
            $this->failure($response);
        }

        return $response;
    }

    private function failure(Response $response): never
    {
        $reason = $response->json('error.errors.0.reason');
        if (in_array($reason, ['storageQuotaExceeded', 'dailyLimitExceeded', 'insufficientFilePermissions'], true) || in_array($response->status(), [401, 402, 404], true)) {
            throw new \DomainException('Drive hết dung lượng/hạn mức hoặc mất quyền; cần xử lý trước khi thử lại.');
        }
        throw new RuntimeException('Google Drive chưa xử lý được (HTTP '.$response->status().'); thử lại có giới hạn.');
    }

    private function budget(int $bytes = 0): void
    {
        $this->repository->locked('google-budget', function () use ($bytes): void {
            $path = $this->repository->root().'/drive-budget.json';
            $data = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
            if (($data['day'] ?? '') !== now()->format('Y-m-d')) {
                $data = ['day' => now()->format('Y-m-d'), 'requests' => 0, 'bytes' => 0];
            }
            $data['requests']++;
            $data['bytes'] += $bytes;
            if ($data['requests'] > 500 || $data['bytes'] > 2 * 1024 * 1024 * 1024) {
                throw new \DomainException('Đã đạt giới hạn bảo vệ của ứng dụng hôm nay (500 thao tác/2 GB truyền). Không tự tăng hạn mức.');
            }
            $this->repository->writeJson($path, $data);
        });
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    private function validateSession(string $location): void
    {
        if (! str_starts_with($location, 'https://www.googleapis.com/upload/drive/v3/files?') || str_contains($location, "\r") || str_contains($location, "\n")) {
            throw new RuntimeException('Phiên upload Google không hợp lệ.');
        }
    }
}
