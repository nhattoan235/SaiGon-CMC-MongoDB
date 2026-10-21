<?php

namespace App\Console\Commands;

use App\Services\Backup\MongoBackup;
use Illuminate\Console\Command;

class BackupVerify extends Command
{
    protected $signature = 'cmms:backup-verify {artifact}';

    protected $description = 'Authenticate and verify backup without modifying the database';

    public function handle(MongoBackup $mongo): int
    {
        try {
            $manifest = $mongo->verify($this->argument('artifact'));
            $this->line(json_encode(['verified' => true, 'source_database' => $manifest['source_database'], 'capture_at' => $manifest['capture_at'], 'files' => count($manifest['files'])], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Backup không xác minh được. Kiểm khóa, định dạng và tính toàn vẹn.');

            return self::FAILURE;
        }
    }
}
