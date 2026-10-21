<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupCommandsTest extends TestCase
{
    public function test_tick_records_heartbeat_without_enabling_schedule_or_contacting_google(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        try {
            $this->artisan('cmms:backup-tick')->assertSuccessful();
            $this->assertFileExists($root.'/heartbeat.json');
            $this->assertFileDoesNotExist($root.'/google.enc');
            $this->assertFileDoesNotExist($root.'/schedule.json');
        } finally {
            File::deleteDirectory($root);
        }
    }
}
