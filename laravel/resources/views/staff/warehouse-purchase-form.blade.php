<section class="warehouse-purchase-form" aria-labelledby="new-purchase-heading">
    <h3 id="new-purchase-heading">Lập đề xuất nhập hàng</h3>
    <form method="post" action="{{ route('staff.purchases.create') }}">
        @csrf
        <div class="warehouse-purchase-fields">
            <label class="form-label">Nhà cung cấp<select class="form-select @error('supplier_id') is-invalid @enderror" name="supplier_id" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select>@error('supplier_id')<span class="invalid-feedback">{{ $message }}</span>@enderror</label>
            <label class="form-label">Kho nhận hàng<select class="form-select @error('warehouse_id') is-invalid @enderror" name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select>@error('warehouse_id')<span class="invalid-feedback">{{ $message }}</span>@enderror</label>
            <label class="form-label">Vật liệu<select class="form-select @error('items.0.product_id') is-invalid @enderror" name="items[0][product_id]" required>@foreach($products as $product)<option value="{{ $product->id }}" @selected(old('items.0.product_id') == $product->id)>{{ $product->name }} · {{ $product->unit }}</option>@endforeach</select>@error('items.0.product_id')<span class="invalid-feedback">{{ $message }}</span>@enderror</label>
            <label class="form-label">Số lượng<input class="form-control @error('items.0.quantity') is-invalid @enderror" type="number" name="items[0][quantity]" min="1" step="1" value="{{ old('items.0.quantity') }}" required>@error('items.0.quantity')<span class="invalid-feedback">{{ $message }}</span>@enderror</label>
            <label class="form-label">Giá nhập<input class="form-control @error('items.0.unit_price') is-invalid @enderror" type="number" name="items[0][unit_price]" min="0" step="0.01" value="{{ old('items.0.unit_price') }}" required>@error('items.0.unit_price')<span class="invalid-feedback">{{ $message }}</span>@enderror</label>
            <button class="btn btn-dark" type="submit">Gửi đề xuất nhập hàng</button>
        </div>
    </form>
</section>
