<?php

namespace App\Http\Controllers;

use App\Services\Backup\GoogleDriveConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BackupGoogleController extends Controller
{
    public function connect(Request $request, GoogleDriveConnection $google): RedirectResponse
    {
        session()->put('google_backup_admin', (int) $request->user()->getAuthIdentifier());
        try {
            return redirect()->away($google->authorizationUrl());
        } catch (\Throwable $exception) {
            return redirect()->route('staff.backups.index')->withErrors(['backup' => 'Chưa cấu hình OAuth hoặc chưa xác nhận project không Billing. Xem hướng dẫn kết nối trên trang.']);
        }
    }

    public function callback(Request $request, GoogleDriveConnection $google): RedirectResponse
    {
        abort_unless(session('google_backup_admin') === (int) $request->user()->getAuthIdentifier(), 403);
        try {
            $google->callback((string) $request->query('code'), (string) $request->query('state'));
            $google->folder();

            return redirect()->route('staff.backups.index')->with('success', 'Đã kết nối Google Drive và thư mục backup riêng tư.');
        } catch (\Throwable $exception) {
            return redirect()->route('staff.backups.index')->withErrors(['backup' => 'Kết nối Google chưa hoàn tất. Thử lại, kiểm tra redirect URI, tài khoản và quyền drive.file.']);
        }
    }

    public function disconnect(GoogleDriveConnection $google): RedirectResponse
    {
        try {
            $google->disconnect();

            return redirect()->route('staff.backups.index')->with('success', 'Đã ngắt kết nối. Các bản backup trên Drive vẫn được giữ.');
        } catch (\Throwable $exception) {
            return redirect()->route('staff.backups.index')->withErrors(['backup' => 'Chưa thu hồi được quyền Google. Có thể thử lại hoặc thu hồi trong phần Bảo mật tài khoản Google.']);
        }
    }
}
