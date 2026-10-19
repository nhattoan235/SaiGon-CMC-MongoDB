<?php

namespace App\Console\Commands;

use App\Services\MysqlSnapshot;
use Illuminate\Console\Command;

class ImportMysqlSnapshot extends Command
{
    protected $signature = 'cmms:import-mysql-snapshot {snapshot : Private snapshot directory} {--dry-run} {--verify : Verify only; never modify application records}';

    protected $description = 'Import or verify a read-only MySQL snapshot in the isolated MongoDB application';

    public function handle(MysqlSnapshot $snapshots): int
    {
        try {
            $result = $this->option('verify')
                ? $snapshots->verify($this->argument('snapshot'))
                : $snapshots->import($this->argument('snapshot'), (bool) $this->option('dry-run'));
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
