<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupArchive;
use App\Services\Backup\BackupRepository;
use Illuminate\Console\Command;

class BackupSetup extends Command
{
    protected $signature = 'cmms:backup-setup {--google-client=} {--confirm-no-billing} {--export-key=}';

    protected $description = 'Initialize local backup configuration; never enables cloud billing or purchases';

    public function handle(BackupRepository $repository, BackupArchive $archive): int
    {
        if (app()->environment('testing')) {
            $this->error('Setup thay .env bị vô hiệu trong testing.');

            return self::FAILURE;
        }
        try {
            $values = [];
            if (! config('backup.key')) {
                $values['BACKUP_ENCRYPTION_KEY'] = base64_encode(random_bytes(32));
            } else {
                $archive->key();
            }
            $file = $this->option('google-client');
            if ($file) {
                $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)['web'] ?? [];
                if (empty($data['client_id']) || empty($data['client_secret']) || ! in_array(config('backup.google.redirect'), $data['redirect_uris'] ?? [], true)) {
                    throw new \RuntimeException('Cần Web client với redirect URI khớp.');
                }
                $values['BACKUP_GOOGLE_CLIENT_ID'] = $data['client_id'];
                $values['BACKUP_GOOGLE_CLIENT_SECRET'] = $data['client_secret'];
            }
            if ($this->option('confirm-no-billing')) {
                if (! $file && ! config('backup.google.client_id')) {
                    throw new \RuntimeException('Chưa có OAuth client.');
                }
                $values['BACKUP_GOOGLE_NO_BILLING_CONFIRMED'] = 'true';
            }
            if (PHP_OS_FAMILY === 'Windows') {
                foreach (['MONGODUMP_PATH' => 'mongodump', 'MONGORESTORE_PATH' => 'mongorestore'] as $env => $tool) {
                    $path = 'C:/Program Files/MongoDB/Tools/100/bin/'.$tool.'.exe';
                    if (is_file($path)) {
                        $values[$env] = $path;
                    }
                }
            }
            $envPath = base_path('.env');
            $content = file_get_contents($envPath);
            foreach ($values as $name => $value) {
                if (str_contains($value, "\n") || str_contains($value, "\r")) {
                    throw new \RuntimeException('Giá trị cấu hình không hợp lệ.');
                }
                $line = $name.'="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
                $pattern = '/^'.preg_quote($name, '/').'=.*$/m';
                $content = preg_match($pattern, $content) ? preg_replace_callback($pattern, fn () => $line, $content) : $content."\n".$line."\n";
            }
            $temporary = $envPath.'.backup-setup.tmp';
            file_put_contents($temporary, $content, LOCK_EX);
            @chmod($temporary, 0600);
            if (! rename($temporary, $envPath)) {
                throw new \RuntimeException('Không cập nhật được .env.');
            }
            $repository->root();
            if ($export = $this->option('export-key')) {
                $stream = fopen($export, 'xb');
                if (! $stream) {
                    throw new \RuntimeException('Không ghi đè file key đã tồn tại.');
                }
                fwrite($stream, ($values['BACKUP_ENCRYPTION_KEY'] ?? config('backup.key'))."\n");
                fclose($stream);
                @chmod($export, 0600);
                $this->info('Đã xuất key vào file riêng. Cất bản thứ hai ở nơi an toàn khác, không tải vào thư mục backup Drive.');
            }
            $this->info('Đã setup local. Không kích hoạt Billing/trial/Google One; lịch vẫn tắt tới khi kết nối và phục hồi thử đạt.');
            $this->info('Chạy cmms:backup-check bằng tiến trình mới để đọc cấu hình.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Setup chưa hoàn tất. Kiểm Web client, redirect URI, file key chưa tồn tại và quyền ghi. Không gửi secrets qua chat.');

            return self::FAILURE;
        }
    }
}
