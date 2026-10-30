<?php

namespace App\Console\Commands;

use App\Services\MongoSnapshot;
use Illuminate\Console\Command;

class MongoRehearsal extends Command
{
    protected $signature = 'cmms:mongo-rehearse {database : Database mới bắt đầu bằng cmms_rehearsal_} {--verify : Chỉ đối soát snapshot đã có}';

    protected $description = 'Sao chép snapshot MySQL sang MongoDB thử nghiệm và đối soát dữ liệu';

    public function handle(MongoSnapshot $snapshot): int
    {
        $report = $this->option('verify')
            ? $snapshot->verify($this->argument('database'))
            : $snapshot->import($this->argument('database'));
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
