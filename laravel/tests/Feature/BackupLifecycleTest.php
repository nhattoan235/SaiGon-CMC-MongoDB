<?php

namespace Tests\Feature;

use App\Jobs\CreateBackupJob;
use App\Services\Backup\BackupRepository;
use App\Services\Backup\BackupService;
use App\Services\Backup\GoogleDriveBackupStore;
use App\Services\Backup\GoogleDriveConnection;
use App\Services\Backup\MongoBackup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupLifecycleTest extends TestCase
{
    public function test_failed_upload_keeps_encrypted_artifact_and_retry_does_not_recapture(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root, 'backup.key' => base64_encode(random_bytes(32))]);
        Queue::fake();
        $this->mock(GoogleDriveConnection::class, fn ($mock) => $mock->shouldReceive('status')->andReturn(['connected' => true]));
        $this->mock(MongoBackup::class, function ($mock): void {
            $mock->shouldReceive('capture')->once()->andReturnUsing(function (string $directory): array {
                File::ensureDirectoryExists($directory);
                file_put_contents($directory.'/database.archive.gz', 'dump');
                $manifest = ['format' => 1, 'application' => 'saigon-cmc', 'capture_at' => now()->toIso8601String(), 'files' => ['database.archive.gz' => hash('sha256', 'dump')]];
                file_put_contents($directory.'/manifest.json', json_encode($manifest));

                return $manifest;
            });
        });
        $this->mock(GoogleDriveBackupStore::class, function ($mock): void {
            $mock->shouldReceive('upload')->once()->andThrow(new \RuntimeException('network'));
            $mock->shouldReceive('upload')->once()->andReturn(['cloud_id' => 'a', 'cloud_marker' => 'b', 'cloud_owner' => 'owner', 'cloud_folder' => 'folder']);
            $mock->shouldReceive('completed')->andReturn([]);
        });
        try {
            $service = app(BackupService::class);
            $run = $service->request('manual', 1);
            $second = $service->request('manual', 1);
            $this->assertSame($run['id'], $second['id']);
            Queue::assertPushed(CreateBackupJob::class, 1);
            $service->run($run['id']);
            $repository = app(BackupRepository::class);
            $failed = $repository->find($run['id']);
            $this->assertSame('upload_failed', $failed['state']);
            $this->assertFileExists($repository->path($run['id']).'/backup.cmcbackup');
            $this->assertDirectoryDoesNotExist($repository->path($run['id']).'/capture');
            $this->travel(1)->days();
            $service->retryUpload($run['id']);
            $service->run($run['id']);
            $this->assertSame(now()->toIso8601String(), $repository->find($run['id'])['attempt_started_at']);
            $this->assertSame('completed', $repository->find($run['id'])['state']);
        } finally {
            $this->travelBack();
            File::deleteDirectory($root);
        }
    }

    public function test_new_verified_backup_retains_seven_cloud_copies_and_two_local_copies(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root, 'backup.key' => base64_encode(random_bytes(32))]);
        Queue::fake();
        $repository = app(BackupRepository::class);
        $old = [];
        for ($i = 0; $i < 8; $i++) {
            $run = $repository->create('manual', 1);
            $run = $repository->update($run['id'], ['state' => 'completed', 'capture_at' => now()->subDays(8 - $i)->toIso8601String(), 'cloud_id' => $run['id'], 'cloud_owner' => 'owner', 'cloud_folder' => 'folder']);
            file_put_contents($repository->path($run['id']).'/backup.cmcbackup', 'old');
            $old[] = $run;
        }
        $this->mock(GoogleDriveConnection::class, fn ($mock) => $mock->shouldReceive('status')->andReturn(['connected' => true]));
        $this->mock(MongoBackup::class, function ($mock): void {
            $mock->shouldReceive('capture')->once()->andReturnUsing(function (string $directory): array {
                File::ensureDirectoryExists($directory);
                file_put_contents($directory.'/database.archive.gz', 'dump');
                $manifest = ['format' => 1, 'application' => 'saigon-cmc', 'capture_at' => now()->toIso8601String(), 'files' => ['database.archive.gz' => hash('sha256', 'dump')]];
                file_put_contents($directory.'/manifest.json', json_encode($manifest));

                return $manifest;
            });
        });
        $this->mock(GoogleDriveBackupStore::class, function ($mock) use ($repository, $old): void {
            $mock->shouldReceive('upload')->once()->andReturnUsing(fn (array $run) => ['cloud_id' => $run['id'], 'cloud_marker' => 'marker', 'cloud_owner' => 'owner', 'cloud_folder' => 'folder']);
            $mock->shouldReceive('completed')->once()->andReturnUsing(fn () => array_values(array_filter($repository->all(), fn ($run) => $run['state'] === 'completed')));
            $mock->shouldReceive('delete')->once()->withArgs(fn ($run) => $run['id'] === $old[0]['id']);
            $mock->shouldReceive('delete')->once()->withArgs(fn ($run) => $run['id'] === $old[1]['id']);
        });
        try {
            $service = app(BackupService::class);
            $run = $service->request('manual', 1);
            $service->run($run['id']);
            $this->assertSame('completed', $repository->find($run['id'])['state']);
            $this->assertTrue($repository->find($old[0]['id'])['cloud_pruned']);
            $this->assertArrayNotHasKey('cloud_pruned', $repository->find($old[7]['id']));
            $this->assertFileExists($repository->path($run['id']).'/backup.cmcbackup');
            $this->assertFileExists($repository->path($old[7]['id']).'/backup.cmcbackup');
            $this->assertFileDoesNotExist($repository->path($old[6]['id']).'/backup.cmcbackup');
        } finally {
            File::deleteDirectory($root);
        }
    }
}
