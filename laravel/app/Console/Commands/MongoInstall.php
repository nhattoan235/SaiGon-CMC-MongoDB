<?php

namespace App\Console\Commands;

use App\Services\MongoInstaller;
use Illuminate\Console\Command;

class MongoInstall extends Command
{
    protected $signature = 'cmms:mongo-install';

    protected $description = 'Cài schema, index và bộ đếm MongoDB mà không xóa dữ liệu';

    public function handle(MongoInstaller $installer): int
    {
        $installer->install();
        $this->info('MongoDB schema/index đã sẵn sàng.');

        return self::SUCCESS;
    }
}
