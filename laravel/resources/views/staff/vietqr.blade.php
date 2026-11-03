@extends('layouts.staff')
@section('title', 'Chuyển khoản VietQR | Sài Gòn CMC')
@section('content')
<div class="container-fluid container-wide pb-5"><div class="page-intro"><h1 class="page-title">Chuyển khoản VietQR</h1><p class="page-subtitle">Quét mã QR tài khoản nhận tiền do quản trị viên cấu hình.</p></div>
@include('store._bank-qr')
<a class="btn btn-outline-dark" href="{{ route('staff.section', 'finance') }}">Ghi nhận thanh toán tại Tài chính &amp; công nợ</a>
</div>
@endsection
