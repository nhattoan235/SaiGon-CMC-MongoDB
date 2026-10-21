<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupSchedule;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupTick extends Command
{
    protected $signature = 'cmms:backup-tick';

    protected $description = 'Check daily schedule and bounded upload retries once';

    public function handle(BackupSchedule $schedule, BackupService $service): int
    {
        try {
            $service->recoverInterrupted();
            $schedule->tick();

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Không xử lý được lịch backup. Kiểm cấu hình, kết nối, worker và tác vụ đang chạy.');

            return self::FAILURE;
        }
    }
}
