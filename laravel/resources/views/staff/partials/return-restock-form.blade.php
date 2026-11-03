<form class="mt-2 d-flex gap-2 flex-wrap align-items-end" method="post" action="{{ route('staff.returns.restock', $return->id) }}">
    @csrf
    <input type="hidden" name="return_id" value="{{ $return->id }}">
    <label class="small">Nhà cung cấp
        <select class="form-select" name="supplier_id" required>
            @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach
        </select>
    </label>
    <label class="small">Giá nhập mỗi đơn vị
        <input class="form-control" type="number" name="unit_price" min="0" step="0.01" value="{{ old('return_id') == $return->id ? old('unit_price') : '' }}" required>
    </label>
    <label class="small">Ngày nhà cung cấp dự kiến (nếu đã biết)
        <input class="form-control" type="date" name="estimated_available_on" min="{{ now()->toDateString() }}" value="{{ old('return_id') == $return->id ? old('estimated_available_on') : $return->estimated_available_on }}">
    </label>
    <span class="small text-muted">Ngày này là ước tính và chưa xác nhận giao hàng.</span>
    <button class="btn btn-dark">Lập đề xuất nhập hàng đổi</button>
</form>
