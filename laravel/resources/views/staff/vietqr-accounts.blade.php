@extends('layouts.staff')
@section('title', 'Tài khoản VietQR | Sài Gòn CMC')
@section('content')
<div class="container-fluid container-wide pb-5">
    <div class="page-intro"><h1 class="page-title">Tài khoản nhận VietQR</h1><p class="page-subtitle">Chỉ quản trị viên thay đổi tài khoản nhận tiền. QR nhận tiền xuất hiện ở tài khoản đang hoạt động sau khi lưu.</p></div>
    <section class="mb-4"><h2 class="h5">Thêm tài khoản</h2>
        <form class="order-card row g-3" method="post" action="{{ route('management.bank-accounts.store') }}">@csrf
            <div class="col-md-2"><label class="form-label">Mã BIN ngân hàng<input class="form-control" name="bank_bin" pattern="[0-9]{6}" maxlength="6" required></label></div>
            <div class="col-md-3"><label class="form-label">Tên ngân hàng<input class="form-control" name="bank_name" maxlength="100" required></label></div>
            <div class="col-md-3"><label class="form-label">Số tài khoản<input class="form-control" name="account_no" inputmode="numeric" pattern="[0-9]{6,19}" maxlength="19" required></label></div>
            <div class="col-md-3"><label class="form-label">Tên chủ tài khoản<input class="form-control" name="account_name" maxlength="100" required></label></div>
            <input type="hidden" name="active" value="1"><div class="col-12"><button class="btn btn-dark">Lưu tài khoản</button></div>
        </form>
    </section>
    <section class="mb-4"><h2 class="h5">Tài khoản đã cấu hình</h2>
        @forelse($accounts as $account)
            <details class="order-card mb-3" @if($account->active) open @endif><summary><strong>{{ $account->bank_name }} · {{ $account->account_no }}</strong> · {{ $account->account_name }} · {{ $account->active ? 'Đang sử dụng' : 'Ngừng sử dụng' }}</summary>
                <form method="post" action="{{ route('management.bank-accounts.update', $account->id) }}" class="row g-3 pt-3">@csrf @method('put')
                    <div class="col-md-2"><label class="form-label">Mã BIN<input class="form-control" name="bank_bin" value="{{ $account->bank_bin }}" pattern="[0-9]{6}" required></label></div>
                    <div class="col-md-3"><label class="form-label">Tên ngân hàng<input class="form-control" name="bank_name" value="{{ $account->bank_name }}" required></label></div>
                    <div class="col-md-3"><label class="form-label">Số tài khoản<input class="form-control" name="account_no" value="{{ $account->account_no }}" pattern="[0-9]{6,19}" required></label></div>
                    <div class="col-md-3"><label class="form-label">Tên chủ tài khoản<input class="form-control" name="account_name" value="{{ $account->account_name }}" required></label></div>
                    <div class="col-md-2"><label class="form-label">Trạng thái<select class="form-select" name="active"><option value="1" @selected($account->active)>Đang sử dụng</option><option value="0" @selected(!$account->active)>Ngừng sử dụng</option></select></label></div>
                    <div class="col-12"><button class="btn btn-dark">Cập nhật</button></div>
                </form>
                @if($account->active)
                    <div class="mt-4">
                        <h3 class="h6">Mã QR nhận tiền</h3>
                        <img src="{{ $account->qr_url }}" alt="Mã QR nhận tiền vào {{ $account->bank_name }}, tài khoản {{ $account->account_no }}" width="270" height="320" class="img-fluid" loading="lazy">
                        <p class="small muted mb-0">Khách quét mã rồi tự nhập số tiền và nội dung chuyển khoản.</p>
                    </div>
                @endif
            </details>
        @empty <p>Chưa có tài khoản nhận tiền. Hãy nhập thông tin ngân hàng của công ty.</p> @endforelse
    </section>
    <section><h2 class="h5">Lịch sử thay đổi</h2><div class="table-responsive"><table class="table"><thead><tr><th>Thời điểm</th><th>Người thực hiện</th><th>Tài khoản</th><th>Thao tác</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->actor_name }}</td><td>#{{ $event->bank_account_id }}</td><td>{{ $event->action === 'created' ? 'Thêm mới' : 'Cập nhật' }}</td></tr>@empty<tr><td colspan="4">Chưa có thay đổi.</td></tr>@endforelse</tbody></table></div></section>
</div>
@endsection
