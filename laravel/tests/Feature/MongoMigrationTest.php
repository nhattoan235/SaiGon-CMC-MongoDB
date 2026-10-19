<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ResetsMongoDatabase;
use Tests\TestCase;

class MongoMigrationTest extends TestCase
{
    use ResetsMongoDatabase;

    public function test_migration_repository_aggregates_and_installs_schema_idempotently(): void
    {
        $this->assertNull(DB::table('migrations')->max('batch'));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertSame(1, DB::table('migrations')->max('batch'));
        $this->assertSame(1, DB::table('migrations')->min('batch'));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertDatabaseCount('migrations', 1);
    }
}
