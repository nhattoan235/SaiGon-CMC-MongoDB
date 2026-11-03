@forelse($accounts as $account)
    <article class="order-card mb-4">
        <h2 class="h5">{{ $account->bank_name }}</h2>
        <div class="row g-4 align-items-center">
            <div class="col-md-5"><img src="{{ $account->qr_url }}" alt="VietQR nhận tiền vào {{ $account->bank_name }}" width="270" height="320" class="img-fluid" referrerpolicy="no-referrer"></div>
            <div class="col-md-7"><p>Số tài khoản: <strong>{{ $account->account_no }}</strong></p><p>Chủ tài khoản: <strong>{{ $account->account_name }}</strong></p><p>Nhập số tiền cần thanh toán và mã đơn hàng vào nội dung chuyển khoản. Kế toán ghi nhận thanh toán sau khi đối chiếu tiền thực nhận.</p><p class="small muted">Nếu ảnh QR không tải được, dùng thông tin tài khoản bên trên để chuyển khoản.</p></div>
        </div>
    </article>
@empty
    <div class="empty-state"><h2>Chưa có tài khoản nhận tiền</h2><p>Vui lòng liên hệ cửa hàng để được hướng dẫn thanh toán.</p></div>
@endforelse
