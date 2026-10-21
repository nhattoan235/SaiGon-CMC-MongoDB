<?php

namespace Tests\Feature;

use App\Services\Backup\BackupWriteGate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BackupWriteGateTest extends TestCase
{
    public function test_capture_excludes_other_writers_and_releases_after_exception(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        $gate = app(BackupWriteGate::class);
        try {
            $gate->exclusive(function () use ($root): void {
                $this->assertSame('busy', $this->probe($root.'/write.lock', LOCK_SH));
                throw new \RuntimeException('capture failed');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('capture failed', $exception->getMessage());
        }
        $this->assertSame('ok', $this->probe($root.'/write.lock', LOCK_EX));
        File::deleteDirectory($root);
    }

    public function test_nested_writer_holds_shared_lock_until_outer_scope_finishes(): void
    {
        $root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $root]);
        $gate = app(BackupWriteGate::class);
        $gate->shared(function () use ($gate, $root): void {
            $gate->shared(fn () => $this->assertSame('busy', $this->probe($root.'/write.lock', LOCK_EX)));
            $this->assertSame('busy', $this->probe($root.'/write.lock', LOCK_EX));
        });
        $this->assertSame('ok', $this->probe($root.'/write.lock', LOCK_EX));
        File::deleteDirectory($root);
    }

    private function probe(string $path, int $mode): string
    {
        $script = '$h=fopen($argv[1],"c");echo flock($h,((int)$argv[2])|LOCK_NB)?"ok":"busy";';
        $process = new Process([PHP_BINARY, '-r', $script, $path, (string) $mode]);
        $process->mustRun();

        return $process->getOutput();
    }
}
