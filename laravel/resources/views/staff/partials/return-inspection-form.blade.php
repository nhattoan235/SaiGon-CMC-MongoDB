<form class="mt-2 d-flex gap-2 flex-wrap align-items-end" method="post" action="{{ route('staff.returns.inspect', $return->id) }}">
    @csrf
    <input type="hidden" name="return_id" value="{{ $return->id }}">
    <label class="small">Kho kiểm tra
        <select class="form-select" name="warehouse_id" required>
            @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach
        </select>
    </label>
    <label class="small">Tình trạng hàng
        <select class="form-select" name="reusable" required><option value="1">Còn sử dụng được</option><option value="0">Hư hỏng</option></select>
    </label>
    @if($return->type === 'exchange')
        <label class="small">Ngày nhà cung cấp dự kiến (nếu đã biết)
            <input class="form-control" type="date" name="estimated_available_on" min="{{ now()->toDateString() }}" value="{{ old('return_id') == $return->id ? old('estimated_available_on') : '' }}">
        </label>
        <span class="small text-muted">Đây là ước tính; chỉ nhập khi đã có thông tin từ nhà cung cấp.</span>
    @endif
    <label class="small flex-grow-1">Kết quả kiểm tra
        <input class="form-control" name="note" value="{{ old('return_id') == $return->id ? old('note') : '' }}" placeholder="Ghi tình trạng thực tế" required>
    </label>
    <button class="btn btn-dark">Xác nhận kiểm tra</button>
</form>
