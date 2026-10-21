@extends('layouts.staff')
@section('title', 'Sao lưu dữ liệu')
@section('content')
@php
    $labels = ['queued'=>'Chờ worker', 'capturing'=>'Đang chụp dữ liệu', 'packing'=>'Đang mã hóa', 'uploading'=>'Đang tải và kiểm tra Drive', 'verifying'=>'Đang kiểm tra', 'completed'=>'Đã xác minh trên Drive', 'local_verified'=>'Có bản local, chưa lên Drive', 'upload_failed'=>'Tải Drive thất bại', 'failed'=>'Chụp/đóng gói thất bại', 'interrupted'=>'Tiến trình bị ngắt'];
    $active = collect($status['runs'])->contains(fn ($run) => in_array($run['state'], \App\Services\Backup\BackupService::ACTIVE, true));
    $connected = $status['google']['connected'];
    $scheduleLate = !$status['scheduler_at'] || \Carbon\Carbon::parse($status['scheduler_at'])->lt(now()->subMinutes(3));
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-2">Sao lưu dữ liệu</h1><p class="text-body-secondary mb-0">Bản full độc lập • mã hóa trước khi lưu • Google Drive riêng tư</p></div>
    <form method="post" action="{{ route('staff.backups.create') }}">@csrf<button class="btn btn-success" @disabled(!$connected || !$status['key_ready'] || $active)>{{ $active ? 'Đang có tác vụ xử lý' : 'Tạo bản sao lưu' }}</button></form>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><section class="card h-100"><div class="card-body"><h2 class="h6">Google Drive</h2><strong>{{ $connected ? 'Đã kết nối' : ($status['google']['configured'] ? 'Cần kết nối' : 'Chưa cấu hình') }}</strong><p class="small mt-2 mb-0">{{ $status['google']['email'] ?? 'Không yêu cầu thẻ thanh toán hoặc Google One.' }}</p></div></section></div>
    <div class="col-md-4"><section class="card h-100"><div class="card-body"><h2 class="h6">Dữ liệu cloud mới nhất</h2><strong>{{ $status['last_capture'] ? \Carbon\Carbon::parse($status['last_capture'])->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') : 'Chưa có bản thành công' }}</strong><p class="small mt-2 mb-0">{{ $status['overdue'] ? 'Cần chú ý: chưa có bản cloud trong 24 giờ gần đây.' : 'Đang trong mục tiêu sao lưu hằng ngày.' }}</p></div></section></div>
    <div class="col-md-4"><section class="card h-100"><div class="card-body"><h2 class="h6">Lịch tự động</h2><strong>{{ $settings['enabled'] ? 'Đang bật' : 'Đang tắt' }} · {{ $settings['time'] }}</strong><p class="small mt-2 mb-0">Giờ Việt Nam (UTC+7). {{ $scheduleLate ? 'Chưa nhận tín hiệu lịch trong 3 phút gần đây.' : 'Tiến trình lịch đang gửi tín hiệu.' }}</p></div></section></div>
</div>
<div class="alert alert-info" role="status">Giữ 7 bản cloud thành công và 2 bản local. Drive đầy hoặc mất quyền sẽ báo lỗi; ứng dụng không mua dung lượng hay nâng cấp dịch vụ. Khi chụp dữ liệu, thao tác nghiệp vụ có thể tạm bận trong ít giây.</div>
@if(!$status['key_ready'])<div class="alert alert-warning">Chưa có khóa backup hợp lệ. Cần chạy bước setup local trước khi sao lưu.</div>@endif
@if(!$status['cloud_drill_verified'])<div class="alert alert-warning">Chưa ghi nhận phục hồi thử đúng bản cloud của tài khoản hiện tại. Hãy tạo backup, tải bản mã hóa và phục hồi vào đích riêng; lịch chỉ bật sau khi vòng kiểm tra này đạt.</div>@endif
<div class="row g-3 mb-4">
    <div class="col-lg-6"><section class="card h-100"><div class="card-body"><h2 class="h5">Kết nối Google Drive</h2>
        @if($connected)
        <p>Chỉ truy cập các tệp do ứng dụng tạo. Khi ngắt kết nối, các bản sao lưu vẫn nằm trong Drive.</p>
        <form method="post" action="{{ route('staff.backups.google.disconnect') }}">@csrf<button class="btn btn-outline-secondary">Ngắt kết nối Google</button></form>
        @else
        <p>Tạo OAuth client cho đồ án, không liên kết Billing, không bật trial. Sau khi cấu hình, bạn tự đăng nhập Google để cấp quyền cho thư mục backup.</p>
        <form method="post" action="{{ route('staff.backups.google.connect') }}">@csrf<button class="btn btn-outline-success" @disabled(!$status['google']['configured'])>Kết nối Google Drive</button></form>
        @endif
        <details class="mt-3"><summary>Hướng dẫn cấu hình miễn phí</summary><ol class="small mt-2">
            <li>Mở <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">Google Cloud Console</a>, tạo project riêng; không gắn Billing và không chọn dùng thử trả phí.</li>
            <li>Bật Google Drive API. Trong Google Auth Platform, cấu hình ứng dụng và tạo OAuth client loại Web application.</li>
            <li>Thêm redirect URI: <code>{{ config('backup.google.redirect') }}</code>.</li>
            <li>Lưu Client ID/Client Secret trong cấu hình local theo tài liệu backup-runbook; không gửi mật khẩu/secret qua chat hoặc Git.</li>
            <li>Kết nối bằng nút trên, tạo backup và phục hồi thử trước khi bật lịch.</li>
        </ol><p class="small">Chế độ OAuth Testing có thể cần cấp quyền lại sau 7 ngày. Thư mục backup không được chia sẻ công khai. Khi hết dung lượng, dọn file hoặc dừng sao lưu; không cần mua gói.</p></details>
    </div></section></div>
    <div class="col-lg-6"><section class="card h-100"><div class="card-body"><h2 class="h5">Giờ sao lưu hằng ngày</h2>
        <form method="post" action="{{ route('staff.backups.settings') }}">@csrf
            <label class="form-label" for="backup-time">Giờ Việt Nam</label><input class="form-control mb-3" id="backup-time" name="time" type="time" required value="{{ old('time', $settings['time']) }}">
            <div class="form-check mb-3"><input class="form-check-input" id="backup-enabled" name="enabled" value="1" type="checkbox" @checked($settings['enabled'])><label class="form-check-label" for="backup-enabled">Bật sao lưu tự động mỗi ngày</label></div>
            <p class="small">Máy bật muộn sẽ chạy bù một bản cho hôm nay. Máy tắt không thể sao lưu. Cần tiến trình lịch và worker chạy độc lập với trình duyệt. Worker: {{ $status['worker_at'] && \Carbon\Carbon::parse($status['worker_at'])->gte(now()->subMinutes(3)) ? 'đang gửi tín hiệu' : 'chưa có tín hiệu gần đây' }}.</p>
            <button class="btn btn-outline-success">Lưu lịch</button>
        </form>
    </div></section></div>
</div>
<section class="card"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Lịch sử sao lưu</h2><a class="btn btn-outline-secondary btn-sm" href="{{ route('staff.backups.index') }}">Cập nhật trạng thái</a></div>
@if(empty($status['runs']))<p class="text-body-secondary mb-0">Chưa có bản sao lưu. Sau khi kết nối, bấm Tạo bản sao lưu để bắt đầu.</p>
@else<div class="table-responsive"><table class="table align-middle"><thead><tr><th scope="col">Thời điểm chụp</th><th scope="col">Nguồn</th><th scope="col">Kích thước</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead><tbody>
@foreach($status['runs'] as $run)<tr><td>{{ \Carbon\Carbon::parse($run['capture_at'] ?? $run['created_at'])->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}<small class="d-block text-body-secondary">{{ substr($run['id'], 0, 8) }}</small></td><td>{{ $run['source'] === 'scheduled' ? 'Theo lịch' : 'Thủ công / CLI' }}</td><td>{{ isset($run['size']) ? number_format($run['size']/1048576, 2).' MB' : '—' }}</td><td><strong>{{ $labels[$run['state']] ?? $run['state'] }}</strong>@if($run['cloud_pruned'] ?? false)<small class="d-block">Đã dọn cloud theo chính sách lưu giữ.</small>@endif @if($run['error'] ?? null)<small class="d-block text-danger">{{ $run['error'] }}</small>@endif @if($run['retention_warning'] ?? null)<small class="d-block">{{ $run['retention_warning'] }}</small>@endif @if($run['retry_at'] ?? null)<small class="d-block">Thử lại: {{ \Carbon\Carbon::parse($run['retry_at'])->timezone('Asia/Ho_Chi_Minh')->format('H:i') }}</small>@endif</td><td>
@if(in_array($run['state'], ['completed','local_verified','upload_failed'], true) && !($run['cloud_pruned'] ?? false))<a class="btn btn-outline-success btn-sm mb-1" href="{{ route('staff.backups.download', $run['id']) }}">Tải bản mã hóa</a>@endif
@if($run['state'] === 'upload_failed')<form method="post" action="{{ route('staff.backups.retry', $run['id']) }}">@csrf<button class="btn btn-outline-secondary btn-sm" @disabled(!$connected || $active)>Thử tải lại</button></form>@endif
</td></tr>@endforeach
</tbody></table></div>@endif
</div></section>
<p class="small text-body-secondary mt-3">File tải về cần khóa phục hồi riêng. Không có nút khôi phục đè dữ liệu đang chạy; dùng lệnh phục hồi vào database và thư mục mới.</p>
@endsection
