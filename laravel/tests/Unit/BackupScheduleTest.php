<?php

namespace Tests\Unit;

use App\Services\Backup\BackupRepository;
use App\Services\Backup\BackupSchedule;
use App\Services\Backup\BackupService;
use App\Services\Backup\GoogleDriveConnection;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupScheduleTest extends TestCase
{
    public function test_late_start_runs_once_for_today_and_old_capture_is_not_todays_backup(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        $repository = app(BackupRepository::class);
        $repository->root();
        $repository->writeJson($root.'/schedule.json', ['enabled' => true, 'time' => '20:00']);
        $old = $repository->create('manual', 1);
        $repository->update($old['id'], ['state' => 'completed', 'capture_at' => '2026-10-20T20:00:00+07:00']);
        $this->mock(BackupService::class, function ($mock) use ($repository): void {
            $mock->shouldReceive('request')->once()->with('scheduled', null)->andReturnUsing(fn () => $repository->create('scheduled', null));
        });
        try {
            $this->travelTo(Carbon::parse('2026-10-21 19:59:00', 'Asia/Ho_Chi_Minh'));
            $schedule = app(BackupSchedule::class);
            $schedule->tick();
            $this->travelTo(Carbon::parse('2026-10-21 21:30:00', 'Asia/Ho_Chi_Minh'));
            $schedule->tick();
            $schedule->tick();
            $this->assertCount(2, $repository->all());
        } finally {
            $this->travelBack();
            File::deleteDirectory($root);
        }
    }

    public function test_verified_manual_capture_after_due_satisfies_daily_schedule(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        $repository = app(BackupRepository::class);
        $repository->root();
        $repository->writeJson($root.'/schedule.json', ['enabled' => true, 'time' => '20:00']);
        $run = $repository->create('manual', 1);
        $repository->update($run['id'], ['state' => 'completed', 'capture_at' => '2026-10-21T20:10:00+07:00']);
        $this->mock(BackupService::class, fn ($mock) => $mock->shouldNotReceive('request'));
        try {
            $this->travelTo(Carbon::parse('2026-10-21 21:30:00', 'Asia/Ho_Chi_Minh'));
            app(BackupSchedule::class)->tick();
            $this->assertCount(1, $repository->all());
        } finally {
            $this->travelBack();
            File::deleteDirectory($root);
        }
    }

    public function test_manual_upload_retry_runs_even_when_daily_schedule_is_disabled(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        $repository = app(BackupRepository::class);
        $run = $repository->create('manual', 1);
        $repository->update($run['id'], ['state' => 'upload_failed', 'retryable' => true, 'retry_at' => now()->subMinute()->toIso8601String()]);
        $this->mock(BackupService::class, function ($mock) use ($run): void {
            $mock->shouldReceive('retryUpload')->once()->with($run['id']);
            $mock->shouldNotReceive('request');
        });
        try {
            app(BackupSchedule::class)->tick();
            $this->assertFileDoesNotExist($root.'/schedule-claim.json');
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_exhausted_retries_do_not_run_forever_each_minute(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        $repository = app(BackupRepository::class);
        $run = $repository->create('manual', 1);
        $repository->update($run['id'], ['state' => 'upload_failed', 'retryable' => true, 'attempts' => 3, 'retry_at' => now()->subMinute()->toIso8601String()]);
        $this->mock(BackupService::class, function ($mock): void {
            $mock->shouldNotReceive('retryUpload');
            $mock->shouldNotReceive('request');
        });
        try {
            app(BackupSchedule::class)->tick();
            $this->assertFileExists($root.'/heartbeat.json');
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_daily_enable_requires_restore_of_a_verified_cloud_artifact_from_current_account(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        $key = random_bytes(32);
        config(['backup.root' => $root, 'backup.key' => base64_encode($key)]);
        $this->mock(GoogleDriveConnection::class, fn ($mock) => $mock->shouldReceive('status')->andReturn(['connected' => true, 'owner' => 'owner-current']));
        $repository = app(BackupRepository::class);
        $repository->root();
        $repository->writeJson($root.'/restore-drill.json', ['key_id' => substr(hash('sha256', $key), 0, 16), 'artifact_sha256' => str_repeat('a', 64)]);
        try {
            $schedule = app(BackupSchedule::class);
            try {
                $schedule->save(true, '20:00');
                $this->fail('Local drill alone must not enable cloud schedule.');
            } catch (\RuntimeException $exception) {
                $this->assertFalse($schedule->settings()['enabled']);
            }
            $run = $repository->create('manual', 1);
            $repository->update($run['id'], ['state' => 'completed', 'cloud_owner' => 'owner-current', 'sha256' => str_repeat('a', 64), 'capture_at' => now()->toIso8601String()]);
            $schedule->save(true, '20:00');
            $this->assertTrue($schedule->settings()['enabled']);
        } finally {
            File::deleteDirectory($root);
        }
    }
}
