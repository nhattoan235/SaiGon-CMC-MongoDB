@extends('layouts.store')
@section('title', 'Hồ sơ mua hàng | Sài Gòn CMC')
@section('content')
<div class="container-fluid container-wide pb-5 customer-dashboard">
    <div class="page-intro">
        <div class="breadcrumbs"><a href="{{ route('home') }}">Trang chủ</a> / Hồ sơ mua hàng</div>
        <h1 class="page-title">Hồ sơ mua hàng</h1>
        <p class="page-subtitle">Theo dõi báo giá, hợp đồng, đơn hàng và thanh toán tại một nơi.</p>
    </div>

    <section class="customer-dashboard-actions" aria-labelledby="customerActionTitle">
        <div class="section-head"><div><span class="eyebrow muted">BƯỚC TIẾP THEO</span><h2 id="customerActionTitle">Việc cần bạn xem</h2></div></div>
        @forelse($actions as $action)
            <article class="customer-action-card">
                <div><span class="status status-wait">{{ $action['title'] }}</span><p>{{ $action['description'] }}</p></div>
                <a class="btn-editorial dark" href="{{ $action['url'] }}">Mở hồ sơ <span aria-hidden="true">→</span></a>
            </article>
        @empty
            <div class="customer-action-empty" role="status">Hiện không có việc cần bạn xác nhận.</div>
        @endforelse
    </section>

    <section class="customer-records" aria-labelledby="customerRecordsTitle">
        <div class="section-head"><div><span class="eyebrow muted">HỒ SƠ CỦA BẠN</span><h2 id="customerRecordsTitle">Mở theo nhu cầu</h2></div></div>
        <div class="customer-record-grid">
            @foreach($sections as $section)
                <a class="customer-record-card" href="{{ $section['url'] }}">
                    <span class="customer-record-count">{{ $section['count'] }}</span>
                    <strong>{{ $section['label'] }}</strong>
                    <span>{{ $section['note'] }}</span>
                    <span class="customer-record-link">Xem hồ sơ <span aria-hidden="true">→</span></span>
                </a>
            @endforeach
        </div>
    </section>
</div>
@endsection
