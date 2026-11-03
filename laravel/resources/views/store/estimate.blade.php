@extends('layouts.store')
@section('title','Dự toán vật liệu | Sài Gòn CMC')
@section('content')
<div class="container-fluid container-wide pb-5">
    <div class="page-intro">
        <div class="eyebrow muted">Công cụ tham khảo</div>
        <h1 class="page-title">Dự toán vật liệu</h1>
        <p class="page-subtitle">Nhập diện tích một tầng điển hình và số tầng để ước tính sơ bộ. Khối lượng thực tế cần kiểm tra theo bản vẽ và hiện trạng công trình.</p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="get" action="{{ route('estimate') }}" class="order-card estimate-calculator-form row g-3">
        <div class="col-md-4">
            <label class="form-label" for="estimate-building-type">Loại công trình</label>
            <select class="form-select @error('building_type') is-invalid @enderror" id="estimate-building-type" name="building_type" required>
                @if($buildingTypes->isEmpty())
                    <option value="" selected disabled>Chưa có định mức đang áp dụng</option>
                @else
                    <option value="" disabled @selected(!isset($data['building_type']))>Chọn loại công trình</option>
                    @foreach($buildingTypes as $buildingType)
                        <option value="{{ $buildingType }}" @selected(($data['building_type'] ?? '') === $buildingType)>
                            {{ $buildingTypeLabels[$buildingType] ?? str_replace('_', ' ', $buildingType) }}
                        </option>
                    @endforeach
                @endif
            </select>
            @error('building_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="estimate-area">Diện tích một tầng (m²)</label>
            <input class="form-control @error('area') is-invalid @enderror" id="estimate-area" name="area" type="number" min="0.01" max="10000" step="0.01" value="{{ $data['area'] ?? '' }}" required>
            <div class="form-text">Nhập diện tích nền/tầng điển hình, không phải tổng diện tích các tầng.</div>
            @error('area')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
            <label class="form-label" for="estimate-floors">Số tầng</label>
            <input class="form-control @error('floors') is-invalid @enderror" id="estimate-floors" name="floors" type="number" min="1" max="50" step="1" value="{{ $data['floors'] ?? 1 }}" required>
            @error('floors')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3 estimate-calculator-actions d-flex gap-2 flex-wrap">
            <span class="estimate-action-spacer" aria-hidden="true"></span>
            <button class="btn-editorial dark" type="submit" @disabled($buildingTypes->isEmpty())>Tính vật liệu</button>
            @if(isset($data['building_type'], $data['area']))<a class="btn btn-link" href="{{ route('estimate') }}">Nhập lại</a>@endif
        </div>
        </form>
    </section>

    <section aria-label="Kết quả dự toán">
    @if($rows->isNotEmpty())
        @if($estimateSet)
            <div class="alert alert-info mt-4 mb-2" role="status">
                Bộ định mức: <strong>{{ $estimateSet['label'] }}</strong>
                @if($estimateSet['versioned']) · phiên bản {{ $estimateSet['version'] }}@else · {{ $estimateSet['change_note'] }}@endif
            </div>
        @endif
        <div class="table-responsive mt-4">
            <table class="table">
                <thead><tr><th>Vật liệu</th><th>Khối lượng ước tính</th>@auth<th>Đơn giá tham khảo</th><th>Tạm tính dòng</th><th></th>@endauth</tr></thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row->name }}<small class="d-block muted">{{ $row->source ?: 'Định mức minh họa, chưa có nguồn xác nhận' }}</small></td>
                            <td>{{ \App\Support\UiFormat::quantity($row->quantity) }} {{ $row->unit }}</td>
                            @auth
                                <td>{{ number_format($row->price,0,',','.') }} ₫ / {{ $row->unit }}</td>
                                <td>{{ number_format($row->line_total,0,',','.') }} ₫</td>
                                <td><form method="post" action="{{ route('cart.add') }}">@csrf<input type="hidden" name="product_id" value="{{ $row->product_id }}"><input type="hidden" name="quantity" value="{{ $row->quantity }}"><button class="btn btn-outline-dark btn-sm">Thêm vào giỏ báo giá</button></form></td>
                            @endauth
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @auth
            <p class="fs-5 fw-bold">Tạm tính tham khảo: {{ number_format($total,0,',','.') }} ₫</p>
        @else
            <p><a href="{{ route('login') }}">Đăng nhập</a> để xem giá tham khảo và yêu cầu báo giá.</p>
        @endauth
    @elseif(isset($data['building_type'], $data['area']))
        <div class="alert alert-info mt-4" role="status">Chưa có định mức hoặc vật liệu đang bán cho loại công trình này. Bạn liên hệ nhân viên để được hỗ trợ.</div>
    @else
        <p class="muted mt-3">Chọn thông tin công trình rồi bấm “Tính vật liệu” để xem danh sách ước tính.</p>
    @endif
    <p class="muted small mt-3">Định mức hiện có chỉ mang tính minh họa; nhân viên sẽ xác nhận giá và khối lượng trong báo giá chính thức.</p>
</div>

@endsection
