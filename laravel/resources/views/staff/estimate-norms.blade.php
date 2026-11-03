@extends('layouts.staff')
@section('title', 'Quản lý định mức dự toán | Sài Gòn CMC')
@section('content')
@php
    $currentMeta = $current['meta'];
        $editorItems = old('items', $current['items']->map(fn ($item) => [
        'product_id' => $item->product_id,
        'quantity_per_square_meter' => $item->quantity_per_square_meter,
        'per_floor' => (int) $item->per_floor,
        'rounding' => $item->rounding,
        'source' => $item->source,
    ])->all());
    if ($editorItems === []) {
        $editorItems = [['product_id' => '', 'quantity_per_square_meter' => '', 'per_floor' => '0', 'rounding' => 'whole', 'source' => '']];
    }
@endphp
<div class="container-fluid container-wide pb-5">
    <div class="page-intro">
        <a href="{{ route('management.index') }}">← Quản lý hệ thống</a>
        <div class="eyebrow muted mt-3">QUẢN TRỊ / DỰ TOÁN NHANH</div>
        <h1 class="page-title">Quản lý định mức dự toán</h1>
        <p class="page-subtitle">Tạo phiên bản định mức cho từng loại công trình. Phiên bản mới áp dụng ngay khi lưu; phiên bản cũ được giữ lại để xem và kích hoạt lại.</p>
    </div>

    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

    @if($hasLegacyToImport)
        <section class="order-card mb-4" aria-labelledby="legacy-import-title">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div><h2 id="legacy-import-title" class="h5 mb-1">Định mức cũ chưa có lịch sử phiên bản</h2><p class="muted mb-0">Nhập nguyên trạng định mức cũ thành phiên bản 1. Dữ liệu cũ vẫn được giữ lại và nguồn tiếp tục ghi rõ là minh họa.</p></div>
                <form method="post" action="{{ route('management.estimate-norms.import') }}">@csrf<button class="btn btn-outline-dark" type="submit">Nhập và kích hoạt bản cũ</button></form>
            </div>
        </section>
    @endif

    <section class="order-card mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div><h2 class="h4 mb-1">Định mức đang áp dụng</h2><p class="muted mb-0">Chọn một loại đã có để xem thông tin và lịch sử.</p></div>
            @if($currentMeta)
                <span class="badge {{ $currentMeta['versioned'] ? 'bg-success' : 'bg-warning text-dark' }}">
                    {{ $currentMeta['versioned'] ? 'Đang áp dụng · phiên bản '.$currentMeta['version'] : 'Bản cũ chưa phiên bản hóa' }}
                </span>
            @endif
        </div>
        <form method="get" action="{{ route('management.estimate-norms.index') }}" class="row g-2 align-items-end">
            <div class="col-md-8"><label for="estimate-norm-picker" class="form-label">Loại công trình</label>
                <select class="form-select" id="estimate-norm-picker" name="building_type" onchange="this.form.submit()">
                    @forelse($options as $option)
                        <option value="{{ $option->code }}" @selected($selectedCode === $option->code)>{{ $option->label }} · {{ $option->code }}</option>
                    @empty
                        <option value="">Chưa có loại công trình</option>
                    @endforelse
                </select>
            </div>
        </form>
    </section>

    <section class="order-card mb-4" aria-labelledby="norm-editor-title">
        <h2 id="norm-editor-title" class="h4">Tạo và kích hoạt phiên bản</h2>
        <p class="muted">Lưu sẽ đổi ngay kết quả Dự toán nhanh. Mỗi lần lưu tạo một phiên bản mới; không sửa phiên bản cũ. Vật liệu đã ngừng bán cần được thay bằng vật liệu đang bán trước khi kích hoạt. Các định mức đang có vẫn mang nhãn minh họa cho tới khi được người có chuyên môn xác nhận.</p>
        <form method="post" action="{{ route('management.estimate-norms.store') }}" id="estimate-norm-editor">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-lg-4"><label class="form-label" for="norm-building-type">Mã loại công trình</label>
                    <input class="form-control @error('building_type') is-invalid @enderror" id="norm-building-type" name="building_type" value="{{ old('building_type', $selectedCode ?? '') }}" maxlength="50" pattern="[A-Z][A-Z0-9_]*" required>
                    <div class="form-text">Dùng mã đang chọn để tạo phiên bản mới; nhập mã khác để tạo loại mới.</div>
                </div>
                <div class="col-lg-4"><label class="form-label" for="norm-label">Tên hiển thị</label>
                    <input class="form-control @error('label') is-invalid @enderror" id="norm-label" name="label" value="{{ old('label', $currentMeta['label'] ?? '') }}" maxlength="100" required>
                </div>
                <div class="col-lg-4"><label class="form-label" for="norm-change-note">Ghi chú thay đổi</label>
                    <input class="form-control @error('change_note') is-invalid @enderror" id="norm-change-note" name="change_note" value="{{ old('change_note') }}" maxlength="255" placeholder="Ví dụ: Cập nhật sau khi rà soát định mức" required>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h3 class="h5 mb-0">Vật liệu và định mức</h3>
                <button class="btn btn-sm btn-outline-dark" type="button" id="add-estimate-norm-item">Thêm vật liệu</button>
            </div>
            <div id="estimate-norm-items" class="d-grid gap-3">
                @foreach($editorItems as $index => $item)
                    <div class="border rounded-3 p-3" data-norm-item>
                        <div class="row g-2 align-items-end">
                            <div class="col-lg-3"><label class="form-label">Vật liệu
                                <select class="form-select" name="items[{{ $index }}][product_id]" required>
                                    <option value="">Chọn vật liệu</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" @selected((string) ($item['product_id'] ?? '') === (string) $product->id)>{{ $product->name }} · {{ $product->code }}{{ $product->active ? '' : ' (đã ngừng bán — thay thế trước khi lưu)' }}</option>
                                    @endforeach
                                </select>
                            </label></div>
                            <div class="col-lg-2"><label class="form-label">Lượng trên mỗi m²
                                <input class="form-control" type="number" min="0.0001" max="100000" step="0.0001" name="items[{{ $index }}][quantity_per_square_meter]" value="{{ $item['quantity_per_square_meter'] ?? '' }}" required>
                            </label></div>
                            <div class="col-lg-2"><label class="form-label">Nhân với số tầng?
                                <select class="form-select" name="items[{{ $index }}][per_floor]" required><option value="0" @selected((string) ($item['per_floor'] ?? '0') === '0')>Không</option><option value="1" @selected((string) ($item['per_floor'] ?? '0') === '1')>Có</option></select>
                            </label></div>
                            <div class="col-lg-2"><label class="form-label">Làm tròn
                                <select class="form-select" name="items[{{ $index }}][rounding]" required><option value="whole" @selected(($item['rounding'] ?? 'whole') === 'whole')>Số nguyên</option><option value="tenth" @selected(($item['rounding'] ?? '') === 'tenth')>Một chữ số thập phân</option></select>
                            </label></div>
                            <div class="col-lg-3"><label class="form-label">Nguồn / ghi chú
                                <input class="form-control" name="items[{{ $index }}][source]" value="{{ $item['source'] ?? '' }}" maxlength="255" placeholder="Định mức minh họa; cần kiểm tra" required>
                            </label></div>
                            <div class="col-12 d-flex justify-content-end"><button class="btn btn-sm btn-outline-danger" type="button" data-remove-norm-item>Xóa vật liệu này</button></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <button class="btn btn-dark mt-3" type="submit">Lưu và kích hoạt phiên bản mới</button>
        </form>
    </section>

    <section class="order-card mb-4" aria-labelledby="norm-history-title">
        <h2 id="norm-history-title" class="h4">Lịch sử phiên bản</h2>
        @forelse($history as $version)
            <details class="border rounded-3 p-3 mb-2" @if($version->is_active) open @endif>
                <summary class="d-flex flex-wrap align-items-center gap-2">
                    <strong>{{ $version->label }} · phiên bản {{ $version->version }}</strong>
                    @if($version->is_active)<span class="badge bg-success">Đang áp dụng</span>@endif
                    <span class="muted">{{ $version->created_at }} · {{ $version->created_by_name ?: 'Khởi tạo hệ thống' }}</span>
                </summary>
                <p class="mt-3 mb-2">{{ $version->change_note }}</p>
                <ul class="mb-3">
                    @foreach($version->items as $item)
                        <li>{{ $item->product_name ?: 'Vật liệu đã ngừng hoặc bị xóa' }}: {{ $item->quantity_per_square_meter }} / m²{{ $item->per_floor ? ' × số tầng' : '' }} · {{ $item->rounding === 'tenth' ? 'làm tròn 1 chữ số' : 'làm tròn số nguyên' }} <span class="muted">— {{ $item->source }}</span></li>
                    @endforeach
                </ul>
                @unless($version->is_active)
                    <form method="post" action="{{ route('management.estimate-norms.activate', $version->id) }}" onsubmit="return confirm('Kích hoạt lại phiên bản {{ $version->version }}? Kết quả dự toán sẽ dùng định mức này ngay.');">
                        @csrf<button class="btn btn-sm btn-outline-dark" type="submit">Kích hoạt lại phiên bản này</button>
                    </form>
                @endunless
            </details>
        @empty
            <p class="muted mb-0">Chưa có phiên bản lưu cho loại công trình này. Có thể nhập bản cũ hoặc tạo phiên bản mới ở phía trên.</p>
        @endforelse
    </section>

    <section class="order-card" aria-labelledby="norm-activation-title">
        <h2 id="norm-activation-title" class="h4">Nhật ký kích hoạt</h2>
        @forelse($activations as $activation)
            <div class="d-flex flex-wrap gap-2 justify-content-between border-bottom py-2">
                <span>Phiên bản {{ $activation->version }} · {{ $activation->label }}</span>
                <span class="muted">{{ $activation->activated_at }} · {{ $activation->activated_by_name ?: 'Khởi tạo hệ thống' }}</span>
            </div>
        @empty
            <p class="muted mb-0">Chưa có lần kích hoạt nào được ghi nhận.</p>
        @endforelse
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('estimate-norm-items');
    const addButton = document.getElementById('add-estimate-norm-item');
    let nextIndex = Math.max(-1, ...Array.from(list.querySelectorAll('[name^="items["]')).map((field) => {
        const match = field.name.match(/^items\[(\d+)\]/);
        return match ? Number(match[1]) : -1;
    })) + 1;

    addButton.addEventListener('click', () => {
        const source = list.querySelector('[data-norm-item]');
        const row = source.cloneNode(true);
        row.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace(/items\[\d+\]/, `items[${nextIndex}]`);
            if (field.tagName === 'SELECT') field.selectedIndex = 0;
            else field.value = '';
        });
        list.appendChild(row);
        nextIndex++;
        row.querySelector('select')?.focus();
    });

    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-norm-item]');
        if (!button) return;
        const rows = list.querySelectorAll('[data-norm-item]');
        if (rows.length <= 1) return;
        button.closest('[data-norm-item]').remove();
    });
});
</script>
@endsection
