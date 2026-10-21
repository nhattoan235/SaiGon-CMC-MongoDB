<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupRepository;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupCreate extends Command
{
    protected $signature = 'cmms:backup-create {--local-only} {--now}';

    protected $description = 'Create full encrypted backup; local-only never claims cloud success';

    public function handle(BackupService $service, BackupRepository $repository): int
    {
        try {
            $run = $service->request('cli', null, (bool) $this->option('local-only'));
            if ($this->option('now')) {
                $service->run($run['id']);
            }
            $run = $repository->find($run['id']);
            $this->line(json_encode(['id' => $run['id'], 'state' => $run['state'], 'artifact' => $repository->path($run['id']).'/backup.cmcbackup'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return in_array($run['state'], ['failed', 'interrupted', 'upload_failed'], true) ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Backup chưa hoàn tất. Chạy cmms:backup-check và kiểm cấu hình/công cụ/worker.');

            return self::FAILURE;
        }
    }
}
