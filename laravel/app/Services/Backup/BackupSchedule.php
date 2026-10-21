<?php

namespace App\Services\Backup;

use Carbon\Carbon;
use RuntimeException;

class BackupSchedule
{
    public function __construct(private BackupRepository $repository, private BackupService $service) {}

    public function settings(): array
    {
        $path = $this->repository->root().'/schedule.json';
        $data = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];

        return array_replace(['enabled' => false, 'time' => config('backup.time'), 'timezone' => config('backup.timezone')], $data);
    }

    public function save(bool $enabled, string $time): void
    {
        if (! preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time)) {
            throw new RuntimeException('Giờ backup phải có dạng HH:mm.');
        }
        if ($enabled) {
            $status = $this->service->status();
            if (! $status['google']['connected'] || ! $status['key_ready'] || ! $status['cloud_drill_verified']) {
                throw new RuntimeException('Cần kết nối Drive, khóa backup và phục hồi đúng bản cloud đã xác minh trước khi bật lịch.');
            }
        }
        $this->repository->locked('schedule-settings', fn () => $this->repository->writeJson($this->repository->root().'/schedule.json', ['enabled' => $enabled, 'time' => $time, 'timezone' => config('backup.timezone')]));
    }

    public function tick(): void
    {
        $this->repository->locked('schedule', function (): void {
            $settings = $this->settings();
            $heartbeatPath = $this->repository->root().'/heartbeat.json';
            $heartbeat = is_file($heartbeatPath) ? json_decode(file_get_contents($heartbeatPath), true) : [];
            $this->repository->writeJson($heartbeatPath, array_replace($heartbeat, ['at' => now()->toIso8601String()]));

            $runs = $this->repository->all();
            foreach ($runs as $run) {
                if (in_array($run['state'], BackupService::ACTIVE, true)) {
                    return;
                }
            }
            foreach ($runs as $run) {
                if ($run['state'] === 'upload_failed' && ($run['retryable'] ?? false) && ($run['attempts'] ?? 0) < 3
                    && ! empty($run['retry_at']) && Carbon::parse($run['retry_at'])->lte(now())) {
                    $this->service->retryUpload($run['id']);

                    return;
                }
            }
            if (! $settings['enabled']) {
                return;
            }
            $now = now(config('backup.timezone'));
            $due = $now->copy()->startOfDay()->setTimeFromTimeString($settings['time']);
            if ($now->lt($due)) {
                return;
            }
            foreach ($runs as $run) {
                if ($run['state'] === 'completed' && isset($run['capture_at']) && Carbon::parse($run['capture_at'])->gte($due)) {
                    return;
                }
            }
            $path = $this->repository->root().'/schedule-claim.json';
            $claim = is_file($path) ? json_decode(file_get_contents($path), true) : [];
            if (($claim['day'] ?? '') === $now->format('Y-m-d')) {
                return;
            }
            $run = $this->service->request('scheduled', null);
            $this->repository->writeJson($path, ['day' => $now->format('Y-m-d'), 'run' => $run['id']]);
        }, false);
    }
}
