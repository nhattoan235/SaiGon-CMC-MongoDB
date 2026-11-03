<?php

namespace App\Services\Backup;

use RuntimeException;

class BackupWriteGate
{
    private mixed $writer = null;

    private int $depth = 0;

    public function __construct(private BackupRepository $repository) {}

    public function acquireShared(): void
    {
        if ($this->depth > 0) {
            $this->depth++;

            return;
        }
        $handle = fopen($this->repository->root().'/write.lock', 'c');
        if (! $handle || ! flock($handle, LOCK_SH | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Hệ thống đang chụp bản sao lưu. Vui lòng thử lại sau ít giây.');
        }
        $this->writer = $handle;
        $this->depth = 1;
    }

    public function releaseShared(): void
    {
        if ($this->depth > 0 && --$this->depth === 0) {
            flock($this->writer, LOCK_UN);
            fclose($this->writer);
            $this->writer = null;
        }
    }

    public function shared(callable $callback): mixed
    {
        $this->acquireShared();
        try {
            return $callback();
        } finally {
            $this->releaseShared();
        }
    }

    public function exclusive(callable $callback, int $timeoutSeconds = 30): mixed
    {
        if ($this->depth > 0) {
            throw new RuntimeException('Không capture trong một thao tác ghi đang chạy.');
        }
        $handle = fopen($this->repository->root().'/write.lock', 'c');
        if (! $handle) {
            throw new RuntimeException('Không tạo được khóa capture.');
        }
        $deadline = microtime(true) + $timeoutSeconds;
        try {
            while (! flock($handle, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Hết thời gian chờ thao tác ghi hoàn tất.');
                }
                usleep(50000);
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
