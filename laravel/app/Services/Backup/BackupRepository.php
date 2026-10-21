<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class BackupRepository
{
    public function root(): string
    {
        $root = rtrim((string) config('backup.root'), '/\\');
        File::ensureDirectoryExists($root, 0700);

        return $root;
    }

    public function path(string $id): string
    {
        if (! Str::isUuid($id)) {
            throw new InvalidArgumentException('Mã bản sao lưu không hợp lệ.');
        }

        return $this->root().'/'.$id;
    }

    public function create(string $source, ?int $userId): array
    {
        $id = (string) Str::uuid();
        File::ensureDirectoryExists($this->path($id), 0700);

        return $this->update($id, ['id' => $id, 'source' => $source, 'user_id' => $userId,
            'created_at' => now()->toIso8601String(), 'state' => 'queued', 'attempts' => 0]);
    }

    public function find(string $id): array
    {
        $path = $this->path($id).'/run.json';
        if (! is_file($path)) {
            throw new RuntimeException('Không tìm thấy bản sao lưu.');
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function update(string $id, array $changes): array
    {
        return $this->locked('metadata', function () use ($id, $changes): array {
            $path = $this->path($id).'/run.json';
            $current = is_file($path) ? $this->find($id) : [];
            $data = array_replace($current, $changes, ['updated_at' => now()->toIso8601String()]);
            $this->writeJson($path, $data);

            return $data;
        });
    }

    public function all(): array
    {
        $runs = [];
        foreach (glob($this->root().'/*/run.json') ?: [] as $path) {
            $runs[] = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        }
        usort($runs, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $runs;
    }

    public function writeJson(string $path, array $data): void
    {
        $temp = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($temp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Không ghi được metadata backup.');
        }
        @chmod($temp, 0600);
        if (! rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException('Không cập nhật được metadata backup.');
        }
    }

    public function locked(string $name, callable $callback, bool $wait = true): mixed
    {
        if (! preg_match('/^[a-z0-9_-]+$/D', $name)) {
            throw new InvalidArgumentException('Khóa không hợp lệ.');
        }
        $handle = fopen($this->root().'/'.$name.'.lock', 'c');
        if (! $handle || ! flock($handle, LOCK_EX | ($wait ? 0 : LOCK_NB))) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Một tác vụ sao lưu khác đang xử lý.');
        }
        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
