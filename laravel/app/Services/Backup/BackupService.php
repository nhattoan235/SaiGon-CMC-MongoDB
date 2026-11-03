<?php

namespace App\Services\Backup;

use App\Jobs\CreateBackupJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupService
{
    public const ACTIVE = ['queued', 'capturing', 'packing', 'uploading', 'verifying'];

    public function __construct(private BackupRepository $repository, private BackupArchive $archive,
        private MongoBackup $mongo, private GoogleDriveConnection $google, private GoogleDriveBackupStore $cloud) {}

    public function request(string $source, ?int $userId, bool $localOnly = false): array
    {
        $this->archive->key();
        if (! $localOnly && ! $this->google->status()['connected']) {
            throw new RuntimeException('Cần kết nối Google Drive trước khi tạo backup cloud.');
        }

        return $this->repository->locked('request', function () use ($source, $userId, $localOnly): array {
            foreach ($this->repository->all() as $run) {
                if (in_array($run['state'], self::ACTIVE, true)) {
                    return $run;
                }
            }
            $run = $this->repository->create($source, $userId);
            $run = $this->repository->update($run['id'], ['local_only' => $localOnly]);
            try {
                CreateBackupJob::dispatch($run['id'])->onConnection('backup')->onQueue('backup');
            } catch (\Throwable $exception) {
                $this->repository->update($run['id'], ['state' => 'failed', 'error' => 'Không đưa được tác vụ vào queue. Kiểm MongoDB/worker.']);
                throw new RuntimeException('Không đưa được tác vụ vào queue backup.');
            }

            return $run;
        });
    }

    public function run(string $id): void
    {
        $this->repository->locked('run', function () use ($id): void {
            $run = $this->repository->find($id);
            if ($run['state'] !== 'queued') {
                return;
            }
            $run = $this->repository->update($id, ['attempt_started_at' => now()->toIso8601String()]);
            $directory = $this->repository->path($id);
            $capture = $directory.'/capture';
            $plain = $directory.'/plain.zip';
            $artifact = $directory.'/backup.cmcbackup';
            try {
                if (empty($run['sha256']) || ! is_file($artifact)) {
                    $this->repository->update($id, ['state' => 'capturing', 'started_at' => now()->toIso8601String(), 'error' => null]);
                    File::deleteDirectory($capture);
                    @unlink($plain);
                    @unlink($artifact);
                    $manifest = $this->mongo->capture($capture);
                    $this->repository->update($id, ['state' => 'packing', 'capture_at' => $manifest['capture_at']]);
                    $this->archive->pack($capture, $plain);
                    $this->archive->encrypt($plain, $artifact);
                    $run = $this->repository->update($id, ['sha256' => hash_file('sha256', $artifact), 'size' => filesize($artifact), 'key_id' => $this->archive->keyId()]);
                }
                File::deleteDirectory($capture);
                @unlink($plain);
                if ($run['local_only'] ?? false) {
                    $this->mongo->verify($artifact);
                    $this->repository->update($id, ['state' => 'local_verified', 'error' => null]);

                    return;
                }
                $run = $this->repository->update($id, ['state' => 'uploading', 'error' => null]);
                $uploaded = $this->cloud->upload($run, $artifact);
                $this->repository->update($id, array_merge($uploaded, ['state' => 'completed', 'verified_at' => now()->toIso8601String(), 'retry_at' => null, 'error' => null]));
                try {
                    $this->retention();
                } catch (\Throwable $exception) {
                    $this->repository->update($id, ['retention_warning' => 'Backup đã xác minh; chưa dọn được bản cũ. Sẽ thử dọn sau lần thành công tiếp theo.']);
                }
            } catch (\Throwable $exception) {
                $run = $this->repository->find($id);
                $uploadFailed = ! empty($run['sha256']) && is_file($artifact);
                $retryable = $uploadFailed && ! ($exception instanceof \DomainException);
                $first = $run['first_failure_at'] ?? now()->toIso8601String();
                $attempt = $run['attempts'] ?? 0;
                $delay = [5, 15, 30][$attempt] ?? null;
                $this->repository->update($id, ['state' => $uploadFailed ? 'upload_failed' : 'failed',
                    'error' => $exception instanceof \DomainException ? $exception->getMessage()
                        : ($uploadFailed ? 'Tải/kiểm tra Drive thất bại. File mã hóa còn trên máy; có thể thử tải lại.' : 'Chụp/đóng gói thất bại. Kiểm công cụ MongoDB, dung lượng và thao tác ghi đang chạy.'),
                    'retryable' => $retryable, 'first_failure_at' => $first,
                    'retry_at' => $retryable && $delay !== null ? Carbon::parse($first)->addMinutes($delay)->toIso8601String() : null]);
            } finally {
                File::deleteDirectory($capture);
                @unlink($plain);
            }
        }, false);
    }

    public function retryUpload(string $id): void
    {
        $this->repository->locked('request', function () use ($id): void {
            $run = $this->repository->find($id);
            if ($run['state'] !== 'upload_failed' || ! is_file($this->repository->path($id).'/backup.cmcbackup')) {
                throw new RuntimeException('Chỉ thử tải lại bản mã hóa đã tạo nhưng chưa lên cloud.');
            }
            foreach ($this->repository->all() as $other) {
                if (in_array($other['state'], self::ACTIVE, true)) {
                    throw new RuntimeException('Một backup khác đang xử lý; thử lại sau.');
                }
            }
            $this->repository->update($id, ['state' => 'queued', 'attempts' => ($run['attempts'] ?? 0) + 1, 'retry_at' => null]);
            try {
                CreateBackupJob::dispatch($id)->onConnection('backup')->onQueue('backup');
            } catch (\Throwable $exception) {
                $this->repository->update($id, ['state' => 'upload_failed', 'retryable' => false, 'error' => 'Không đưa được retry vào queue.']);
                throw new RuntimeException('Queue backup chưa sẵn sàng.');
            }
        });
    }

    public function recoverInterrupted(): void
    {
        try {
            $this->repository->locked('run', function (): void {
                foreach ($this->repository->all() as $run) {
                    if (in_array($run['state'], ['capturing', 'packing', 'uploading', 'verifying'], true)) {
                        $path = $this->repository->path($run['id']);
                        $valid = isset($run['sha256']) && is_file($path.'/backup.cmcbackup') && hash_equals($run['sha256'], hash_file('sha256', $path.'/backup.cmcbackup'));
                        $this->repository->update($run['id'], ['state' => $valid ? 'upload_failed' : 'interrupted', 'retryable' => false, 'retry_at' => null,
                            'error' => 'Tiến trình trước bị ngắt. Kiểm tra và thử lại thủ công; chưa có xác nhận cloud.']);
                        File::deleteDirectory($path.'/capture');
                        @unlink($path.'/plain.zip');
                        if (! $valid) {
                            @unlink($path.'/backup.cmcbackup');
                        }
                    }
                }
            }, false);
        } catch (RuntimeException $exception) {
            // An owned live process still holds the run lock.
        }
    }

    public function status(): array
    {
        $runs = $this->repository->all();
        $completed = array_values(array_filter($runs, fn (array $run): bool => $run['state'] === 'completed'));
        usort($completed, fn (array $a, array $b): int => strcmp($b['capture_at'], $a['capture_at']));
        $last = $completed[0]['capture_at'] ?? null;
        $heartbeat = $this->repository->root().'/heartbeat.json';
        $tick = is_file($heartbeat) ? json_decode(file_get_contents($heartbeat), true) : [];
        $workerPath = $this->repository->root().'/worker-heartbeat.json';
        $worker = is_file($workerPath) ? json_decode(file_get_contents($workerPath), true) : [];
        $drillPath = $this->repository->root().'/restore-drill.json';
        $drill = is_file($drillPath) ? json_decode(file_get_contents($drillPath), true) : [];
        $key = base64_decode((string) config('backup.key'), true);
        $keyReady = is_string($key) && strlen($key) === 32;

        $google = $this->google->status();
        $cloudDrill = false;
        if ($keyReady && ($drill['key_id'] ?? '') === substr(hash('sha256', $key), 0, 16)) {
            foreach ($completed as $run) {
                if (! empty($drill['artifact_sha256']) && ($run['sha256'] ?? null) === $drill['artifact_sha256']
                    && ! empty($google['owner']) && ($run['cloud_owner'] ?? null) === $google['owner']) {
                    $cloudDrill = true;
                }
            }
        }

        return ['google' => $google, 'runs' => $runs, 'last_capture' => $last, 'cloud_drill_verified' => $cloudDrill,
            'overdue' => $last === null || Carbon::parse($last)->lt(now()->subDay()),
            'scheduler_at' => $tick['at'] ?? null, 'worker_at' => $worker['at'] ?? null,
            'key_ready' => $keyReady,
            'restore_verified' => $keyReady && ($drill['key_id'] ?? '') === substr(hash('sha256', $key), 0, 16)];
    }

    private function retention(): void
    {
        $runs = $this->cloud->completed();
        usort($runs, fn (array $a, array $b): int => strcmp($b['capture_at'] ?? '', $a['capture_at'] ?? ''));
        foreach (array_slice($runs, (int) config('backup.cloud_copies')) as $run) {
            $this->cloud->delete($run);
            try {
                $this->repository->update($run['id'], ['cloud_pruned' => true]);
            } catch (\Throwable $exception) {
                // Remote history can include backups from before this machine was installed.
            }
        }
        $local = array_values(array_filter($this->repository->all(), fn (array $run): bool => $run['state'] === 'completed'));
        usort($local, fn (array $a, array $b): int => strcmp($b['capture_at'], $a['capture_at']));
        foreach (array_slice($local, (int) config('backup.local_copies')) as $run) {
            @unlink($this->repository->path($run['id']).'/backup.cmcbackup');
        }
    }
}
