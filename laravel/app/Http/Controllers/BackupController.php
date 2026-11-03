<?php

namespace App\Http\Controllers;

use App\Services\Backup\BackupRepository;
use App\Services\Backup\BackupSchedule;
use App\Services\Backup\BackupService;
use App\Services\Backup\GoogleDriveBackupStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(BackupService $service, BackupSchedule $schedule): View
    {
        return view('staff.backups', ['status' => $service->status(), 'settings' => $schedule->settings()]);
    }

    public function create(Request $request, BackupService $service): RedirectResponse
    {
        try {
            $service->request('manual', (int) $request->user()->getAuthIdentifier());

            return back()->with('success', 'Đã tiếp nhận yêu cầu. Worker sẽ xử lý; chỉ hoàn tất sau khi kiểm tra bản Drive.');
        } catch (\Throwable $exception) {
            return back()->withErrors(['backup' => 'Chưa tạo được backup. Kiểm tra khóa, kết nối Google và queue; thông tin thiếu được hiển thị trên trang.']);
        }
    }

    public function retry(string $id, BackupService $service): RedirectResponse
    {
        try {
            $service->retryUpload($id);

            return back()->with('success', 'Đã xếp hàng tải lại cùng bản mã hóa, không chụp dữ liệu lại.');
        } catch (\Throwable $exception) {
            return back()->withErrors(['backup' => 'Chưa thử lại được. Bản sao lưu phải còn file local và không có tác vụ khác đang chạy.']);
        }
    }

    public function settings(Request $request, BackupSchedule $schedule): RedirectResponse
    {
        $values = $request->validate(['time' => ['required', 'date_format:H:i'], 'enabled' => ['sometimes', 'boolean']]);
        try {
            $schedule->save($request->boolean('enabled'), $values['time']);

            return back()->with('success', 'Đã lưu lịch sao lưu theo giờ Việt Nam.');
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }
    }

    public function download(string $id, BackupRepository $repository, GoogleDriveBackupStore $cloud): BinaryFileResponse|RedirectResponse
    {
        try {
            $run = $repository->find($id);
        } catch (\Throwable $exception) {
            abort(404);
        }
        abort_unless(in_array($run['state'], ['completed', 'local_verified', 'upload_failed'], true), 404);
        $path = $repository->path($id).'/backup.cmcbackup';
        $temporary = false;
        try {
            if (! is_file($path)) {
                if (($run['cloud_pruned'] ?? false) || $run['state'] !== 'completed') {
                    abort(404);
                }
                $path = $repository->path($id).'/download-'.bin2hex(random_bytes(6)).'.tmp';
                $temporary = true;
                $cloud->download($run, $path);
            }
            if (! hash_equals($run['sha256'], hash_file('sha256', $path))) {
                throw new \RuntimeException('Hash không khớp.');
            }

            return response()->download($path, 'saigon-cmc-'.$id.'.cmcbackup', ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend($temporary);
        } catch (\Throwable $exception) {
            if ($temporary) {
                @unlink($path);
            }

            return back()->withErrors(['backup' => 'Không tải được bản đã xác minh. Kiểm tra kết nối Drive hoặc tính toàn vẹn file.']);
        }
    }
}
