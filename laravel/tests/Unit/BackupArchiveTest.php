<?php

namespace Tests\Unit;

use App\Services\Backup\BackupArchive;
use App\Services\Backup\BackupRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupArchiveTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = base_path('../.runtime/backup-tests/'.Str::uuid());
        File::ensureDirectoryExists($this->root.'/capture/files/private');
        config(['backup.root' => $this->root.'/state', 'backup.key' => base64_encode(random_bytes(32))]);
        file_put_contents($this->root.'/capture/database.archive.gz', 'BSON archive');
        file_put_contents($this->root.'/capture/files/private/contract.txt', 'contract');
        file_put_contents($this->root.'/capture/manifest.json', json_encode(['format' => 1, 'application' => 'saigon-cmc', 'files' => ['database.archive.gz' => hash('sha256', 'BSON archive'), 'files/private/contract.txt' => hash('sha256', 'contract')]]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_encrypted_archive_round_trip_and_key_are_separate_from_manifest(): void
    {
        $archive = app(BackupArchive::class);
        $archive->pack($this->root.'/capture', $this->root.'/plain.zip');
        $archive->encrypt($this->root.'/plain.zip', $this->root.'/copy.cmcbackup');
        $manifest = $archive->unpackVerified($this->root.'/copy.cmcbackup', $this->root.'/restore');
        $this->assertSame(1, $manifest['format']);
        $this->assertSame('contract', file_get_contents($this->root.'/restore/files/private/contract.txt'));
        $this->assertStringNotContainsString(config('backup.key'), file_get_contents($this->root.'/copy.cmcbackup'));
    }

    public function test_tampered_ciphertext_cannot_create_restored_files(): void
    {
        $archive = app(BackupArchive::class);
        $archive->pack($this->root.'/capture', $this->root.'/plain.zip');
        $archive->encrypt($this->root.'/plain.zip', $this->root.'/copy.cmcbackup');
        $bytes = file_get_contents($this->root.'/copy.cmcbackup');
        $bytes[strlen($bytes) - 1] = chr(ord($bytes[strlen($bytes) - 1]) ^ 1);
        file_put_contents($this->root.'/copy.cmcbackup', $bytes);
        try {
            $archive->unpackVerified($this->root.'/copy.cmcbackup', $this->root.'/restore');
            $this->fail('Tampered backup must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertDirectoryDoesNotExist($this->root.'/restore');
        }
    }

    public function test_authenticated_zip_with_parent_traversal_is_refused(): void
    {
        $zip = new \ZipArchive;
        $zip->open($this->root.'/evil.zip', \ZipArchive::CREATE);
        $zip->addFromString('../escaped.txt', 'bad');
        $zip->close();
        $archive = app(BackupArchive::class);
        $archive->encrypt($this->root.'/evil.zip', $this->root.'/evil.cmcbackup');
        $this->expectException(\RuntimeException::class);
        $archive->unpackVerified($this->root.'/evil.cmcbackup', $this->root.'/restore');
    }

    public function test_repository_preserves_other_runs_and_refuses_paths(): void
    {
        $repository = app(BackupRepository::class);
        $first = $repository->create('manual', 1);
        $second = $repository->create('scheduled', null);
        $repository->update($first['id'], ['state' => 'upload_failed']);
        $this->assertCount(2, $repository->all());
        $this->assertSame('queued', $repository->find($second['id'])['state']);
        $this->expectException(\InvalidArgumentException::class);
        $repository->find('../token');
    }
}
