<?php

namespace Tests;

use App\Services\MongoInstaller;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

trait RefreshDocumentDatabase
{
    use RefreshDatabase {
        refreshDatabase as private refreshSqlDatabase;
    }

    public function refreshDatabase(): void
    {
        if (DB::connection()->getDriverName() !== 'mongodb') {
            $this->refreshSqlDatabase();

            return;
        }
        app(MongoInstaller::class)->resetTests();
        DB::transaction(fn () => $this->seed(RolePermissionSeeder::class));
    }
}
