<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupSchedule;
use App\Services\Backup\BackupService;
use App\Services\Backup\GoogleDriveBackupStore;
use Illuminate\Console\Command;

class BackupCheck extends Command
{
    protected $signature = 'cmms:backup-check {--cloud}';

    protected $description = 'Show non-secret readiness and optional cloud recovery markers';

    public function handle(BackupService $service, BackupSchedule $schedule, GoogleDriveBackupStore $cloud): int
    {
        try {
            $status = $service->status();
            $runs = $status['runs'];
            unset($status['runs']);
            $status['schedule'] = $schedule->settings();
            $status['runs'] = array_map(fn (array $run): array => array_intersect_key($run, array_flip(['id', 'state', 'capture_at', 'size', 'retry_at', 'error'])), $runs);
            if ($this->option('cloud')) {
                $status['cloud_markers'] = $cloud->completed();
            }
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Không đọc được trạng thái backup/Drive.');

            return self::FAILURE;
        }
    }
}
