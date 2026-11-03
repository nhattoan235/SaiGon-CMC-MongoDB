@extends('layouts.staff')
@section('title', 'Chuyển khoản VietQR | Sài Gòn CMC')
@section('content')
<div class="container-fluid container-wide pb-5">
    <div class="page-intro"><h1 class="page-title">Chuyển khoản VietQR</h1><p class="page-subtitle">Tạo QR để khách chuyển khoản. Chỉ xác nhận sau khi đối chiếu tiền đã vào tài khoản ngân hàng.</p></div>
    @if($accounts->isNotEmpty())
        <section class="mb-5">
            <h2 class="h5">QR nhận tiền vào tài khoản</h2>
            <p class="small muted">Khách tự nhập số tiền và nội dung. Để gắn QR với công nợ và đối soát trong hệ thống, tạo yêu cầu ở bên dưới.</p>
            <div class="d-flex flex-wrap gap-3">
                @foreach($accounts as $account)
                    <article class="order-card">
                        <h3 class="h6">{{ $account->bank_name }} · {{ $account->account_no }}</h3>
                        <img src="{{ $account->qr_url }}" alt="Mã QR nhận tiền vào {{ $account->bank_name }}, tài khoản {{ $account->account_no }}" width="270" height="320" class="img-fluid" loading="lazy">
                        <p class="small muted mb-0">{{ $account->account_name }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
    <section class="mb-5"><h2 class="h5">Tạo yêu cầu chuyển khoản</h2>
        @if($accounts->isEmpty())<p class="order-card">Chưa có tài khoản nhận tiền đang hoạt động. Quản trị viên cần cấu hình tài khoản VietQR.</p>
        @elseif($debts->isEmpty())<p class="order-card">Không có khoản phải thu đủ điều kiện.</p>
        @else<form class="order-card row g-3" method="post" action="{{ route('staff.vietqr.create') }}">@csrf
            <div class="col-md-5"><label class="form-label">Khoản phải thu<select class="form-select" name="debt_id" required>@foreach($debts as $debt)<option value="{{ $debt->id }}">{{ $debt->code }} @if($debt->order_code)· {{ $debt->order_code }}@endif · còn {{ number_format((float) $debt->amount - (float) $debt->paid, 0, ',', '.') }} ₫</option>@endforeach</select></label></div>
            <div class="col-md-4"><label class="form-label">Tài khoản nhận<select class="form-select" name="bank_account_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->bank_name }} · {{ $account->account_no }} · {{ $account->account_name }}</option>@endforeach</select></label></div>
            <div class="col-md-3"><label class="form-label">Số tiền (VND)<input class="form-control" type="number" name="amount" min="1" step="1" required></label></div>
            <div class="col-12"><button class="btn btn-dark">Tạo VietQR cho khách</button></div>
        </form>@endif
    </section>
    <section><h2 class="h5">Yêu cầu chuyển khoản</h2>
        @forelse($requests as $transfer)
            <article class="order-card mb-3"><div class="d-flex justify-content-between gap-3 flex-wrap"><div><strong>{{ $transfer->code }}</strong> · {{ $transfer->customer_name }} · {{ $transfer->debt_code }}<p class="mb-1">{{ number_format((float) $transfer->amount, 0, ',', '.') }} ₫ · {{ $transfer->bank_name }} · {{ $transfer->account_no }}</p><p class="small muted mb-0">Nội dung: <strong>{{ $transfer->transfer_content }}</strong> · {{ ['pending'=>'Chờ khách chuyển','claimed'=>'Khách báo đã chuyển, chờ đối chiếu','confirmed'=>'Đã xác nhận tiền','cancelled'=>'Đã hủy'][$transfer->status] ?? $transfer->status }}</p>@if($transfer->customer_reference)<p class="small mb-0">Mã khách cung cấp: {{ $transfer->customer_reference }}</p>@endif @if($transfer->bank_reference)<p class="small mb-0">Mã ngân hàng đối chiếu: {{ $transfer->bank_reference }}</p>@endif @if($transfer->rejected_reason)<p class="small mb-0">Lý do hủy: {{ $transfer->rejected_reason }}</p>@endif</div>
                @if(in_array($transfer->status, ['pending','claimed'], true))<div class="d-flex gap-2 flex-wrap align-items-start"><form method="post" action="{{ route('staff.vietqr.confirm', $transfer->id) }}" onsubmit="return confirm('Bạn đã kiểm tra tiền thực nhận trên tài khoản ngân hàng?');">@csrf<label class="form-label">Mã giao dịch trên sao kê<input class="form-control" name="bank_reference" maxlength="100" required></label><button class="btn btn-dark">Xác nhận đã nhận tiền</button></form><form method="post" action="{{ route('staff.vietqr.cancel', $transfer->id) }}">@csrf<label class="form-label">Lý do hủy<input class="form-control" name="reason" maxlength="500" required></label><button class="btn btn-outline-danger">Hủy yêu cầu</button></form></div>@endif</div></article>
        @empty<p>Chưa có yêu cầu chuyển khoản.</p>@endforelse
    </section>
</div>
@endsection
