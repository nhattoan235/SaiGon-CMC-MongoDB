<?php

namespace App\Console\Commands;

use App\Services\MongoInstaller;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Support\Facades\DB;

class MongoFresh extends FreshCommand
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (DB::connection($this->option('database'))->getDriverName() !== 'mongodb') {
            return parent::handle();
        }
        app(MongoInstaller::class)->resetTests();
        DB::transaction(fn () => $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]));
        if ($this->option('seed')) {
            $this->call('db:seed', ['--force' => true]);
        }

        return self::SUCCESS;
    }
}
