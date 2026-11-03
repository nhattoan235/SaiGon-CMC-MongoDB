<?php

namespace Tests\Unit;

use App\Services\Backup\BackupRepository;
use App\Services\Backup\GoogleDriveBackupStore;
use App\Services\Backup\GoogleDriveConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoogleDriveBackupStoreTest extends TestCase
{
    public function test_upload_uses_stable_file_id_and_verifies_remote_ciphertext(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        $this->mock(GoogleDriveConnection::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access');
            $mock->shouldReceive('identity')->andReturn('owner-1');
            $mock->shouldReceive('folder')->andReturn('folder-1');
        });
        $repository = app(BackupRepository::class);
        $run = $repository->create('manual', 1);
        file_put_contents($root.'/cipher', 'encrypted');
        $hash = hash('sha256', 'encrypted');
        $run = $repository->update($run['id'], ['size' => 9, 'sha256' => $hash]);
        Http::preventStrayRequests();
        Http::fake([
            'www.googleapis.com/drive/v3/files/generateIds*' => Http::response(['ids' => ['file-1']]),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response([], 200, ['Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=one']),
            'www.googleapis.com/drive/v3/files/file-1?alt=media*' => Http::response('encrypted'),
            'www.googleapis.com/drive/v3/files/file-1*' => Http::response([], 404),
            'www.googleapis.com/drive/v3/files' => Http::response(['id' => 'marker-1']),
        ]);
        try {
            $result = app(GoogleDriveBackupStore::class)->upload($run, $root.'/cipher');
            $this->assertSame('file-1', $result['cloud_id']);
            $this->assertSame('owner-1', $result['cloud_owner']);
            $this->assertSame('file-1', $repository->find($run['id'])['cloud_id']);
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_interrupted_upload_resumes_same_session_at_acknowledged_offset(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        $this->mock(GoogleDriveConnection::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access');
            $mock->shouldReceive('identity')->andReturn('owner-1');
            $mock->shouldReceive('folder')->andReturn('folder-1');
        });
        $repository = app(BackupRepository::class);
        $run = $repository->create('manual', 1);
        $bytes = str_repeat('a', 1048576).'remaining';
        file_put_contents($root.'/cipher', $bytes);
        $run = $repository->update($run['id'], ['size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)]);
        $creates = 0;
        $tailAttempts = 0;
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($bytes, &$creates, &$tailAttempts) {
            if (str_contains($request->url(), 'generateIds')) {
                return Http::response(['ids' => ['file-1']]);
            }
            if (str_contains($request->url(), '/upload/')) {
                if ($request->method() === 'POST') {
                    $creates++;

                    return Http::response([], 200, ['Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=session']);
                }
                $range = $request->header('Content-Range')[0] ?? '';
                if (str_starts_with($range, 'bytes */') || str_starts_with($range, 'bytes 0-')) {
                    return Http::response([], 308, ['Range' => 'bytes=0-1048575']);
                }
                $tailAttempts++;
                if ($tailAttempts === 1) {
                    throw new ConnectionException('simulated disconnect');
                }
                $this->assertSame('bytes 1048576-1048584/1048585', $range);

                return Http::response(['id' => 'file-1']);
            }
            if (str_contains($request->url(), 'alt=media')) {
                return Http::response($bytes);
            }
            if (str_contains($request->url(), '/files/file-1')) {
                return Http::response([], 404);
            }

            return Http::response(['id' => 'marker']);
        });
        try {
            $store = app(GoogleDriveBackupStore::class);
            try {
                $store->upload($run, $root.'/cipher');
            } catch (\Throwable $exception) {
            }
            $pending = $repository->find($run['id']);
            $this->assertArrayHasKey('upload_session_enc', $pending);
            $this->assertStringNotContainsString('upload_id=session', file_get_contents($repository->path($run['id']).'/run.json'));
            $result = $store->upload($pending, $root.'/cipher');
            $this->assertSame('file-1', $result['cloud_id']);
            $this->assertSame(1, $creates);
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_quota_error_stops_instead_of_creating_more_files(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        $this->mock(GoogleDriveConnection::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access');
            $mock->shouldReceive('identity')->andReturn('owner-1');
            $mock->shouldReceive('folder')->andReturn('folder-1');
        });
        $repository = app(BackupRepository::class);
        $run = $repository->create('manual', 1);
        file_put_contents($root.'/cipher', 'encrypted');
        $run = $repository->update($run['id'], ['size' => 9, 'sha256' => hash('sha256', 'encrypted')]);
        Http::fake(['*' => Http::response(['error' => ['errors' => [['reason' => 'storageQuotaExceeded']]]], 403)]);
        try {
            $this->expectException(\DomainException::class);
            app(GoogleDriveBackupStore::class)->upload($run, $root.'/cipher');
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_retention_history_ignores_orphan_markers_and_deduplicates_valid_artifacts(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        $this->mock(GoogleDriveConnection::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access');
            $mock->shouldReceive('identity')->andReturn('owner-1');
            $mock->shouldReceive('folder')->andReturn('folder-1');
        });
        $markers = [];
        $artifacts = [];
        for ($i = 0; $i < 9; $i++) {
            $id = (string) Str::uuid();
            $properties = ['instance' => config('backup.instance'), 'backup' => $id, 'kind' => 'completed'];
            $data = ['id' => $id, 'cloud_id' => 'artifact-'.$i, 'cloud_owner' => 'owner-1', 'capture_at' => '2026-10-21T20:00:00+07:00', 'sha256' => str_repeat('a', 64), 'size' => 10, 'key_id' => str_repeat('b', 16)];
            $markers[] = ['id' => 'marker-'.$i, 'description' => json_encode($data), 'appProperties' => $properties, 'parents' => ['folder-1'], 'trashed' => false];
            if ($i < 8) {
                $artifacts['artifact-'.$i] = ['id' => 'artifact-'.$i, 'size' => '10', 'appProperties' => array_replace($properties, ['kind' => 'artifact']), 'parents' => ['folder-1'], 'trashed' => false];
            }
        }
        $markers[] = array_replace($markers[0], ['id' => 'duplicate-marker']);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($markers, $artifacts) {
            $id = basename(parse_url($request->url(), PHP_URL_PATH));
            if ($id === 'files') {
                return Http::response(['files' => $markers]);
            }

            return isset($artifacts[$id]) ? Http::response($artifacts[$id]) : Http::response([], 404);
        });
        try {
            $completed = app(GoogleDriveBackupStore::class)->completed();
            $this->assertCount(8, $completed);
            $this->assertCount(8, array_unique(array_column($completed, 'cloud_id')));
        } finally {
            File::deleteDirectory($root);
        }
    }
}
