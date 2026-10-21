<?php

namespace App\Console\Commands;

use App\Services\Backup\MongoBackup;
use Illuminate\Console\Command;

class BackupRestore extends Command
{
    protected $signature = 'cmms:backup-restore {artifact} {--database=} {--files=}';

    protected $description = 'Restore into a new isolated database/directory; never replace live data';

    public function handle(MongoBackup $mongo): int
    {
        if (! $this->option('database') || ! $this->option('files')) {
            $this->error('Phải chỉ định --database=cmc_restore_* và --files=thư_mục_mới.');

            return self::FAILURE;
        }
        try {
            $report = $mongo->restore($this->argument('artifact'), $this->option('database'), $this->option('files'));
            $this->line(json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Restore chưa đạt kiểm tra. Không đổi dữ liệu hiện hành. Kiểm target mới, phiên bản, khóa và artifact; giữ target dở để chẩn đoán.');

            return self::FAILURE;
        }
    }
}
