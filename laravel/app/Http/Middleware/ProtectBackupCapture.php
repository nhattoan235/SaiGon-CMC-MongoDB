<?php

namespace App\Http\Middleware;

use App\Services\Backup\BackupWriteGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectBackupCapture
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('staff/backups', 'staff/backups/*')) {
            return $next($request);
        }
        $gate = app(BackupWriteGate::class);
        try {
            $gate->acquireShared();
        } catch (\RuntimeException $exception) {
            return response('Hệ thống đang chụp bản sao lưu. Vui lòng thử lại sau ít giây.', 503)->header('Retry-After', '10');
        }
        try {
            return $next($request);
        } finally {
            $gate->releaseShared();
        }
    }
}
