@extends('layouts.staff')
@section('title', in_array(auth()->user()->role, ['manager', 'admin'], true) && ($activeSection ?? 'overview') === 'overview' ? 'Tổng quan | Sài Gòn CMC' : 'Bảng làm việc | Sài Gòn CMC')
@section('content')
@php
    $role = auth()->user()->role;
    $staffSections = [
        'quotes' => ['Báo giá', 'BG', ['quotes.view']],
        'contracts' => ['Hợp đồng', 'HĐ', ['contracts.view']],
        'sales_orders' => ['Đơn hàng', 'DH', ['sales.orders.view']],
        'finance' => ['Tài chính & công nợ', 'TC', ['finance.view']],
        'purchases' => ['Nhập hàng', 'NH', ['purchases.view']],
        'orders' => ['Xuất kho & giao nhận', 'GN', ['orders.view']],
        'returns' => ['Đổi trả', 'ĐT', ['returns.view']],
        'inventory' => ['Tồn kho', 'TK', ['inventory.view']],
    ];
@endphp
<div class="container-fluid container-wide pb-5">
  <div class="page-intro"><h1 class="page-title">{{ $activeSection === 'overview' ? (in_array($role, ['manager', 'admin'], true) ? 'Tổng quan' : 'Bảng làm việc') : ($staffSections[$activeSection][0] ?? 'Bảng làm việc') }}</h1></div>
  @if($activeSection === 'overview')
  @if($role === 'sales' && auth()->user()->hasPermission('quotes.view') && auth()->user()->hasPermission('sales.orders.view') && auth()->user()->hasPermission('contracts.view'))
    @include('staff.sales-overview')
  @elseif($role === 'warehouse' && auth()->user()->hasPermission('purchases.view') && auth()->user()->hasPermission('inventory.view') && auth()->user()->hasPermission('orders.view') && auth()->user()->hasPermission('returns.view'))
    @include('staff.warehouse-overview')
  @elseif($role === 'accounting' && auth()->user()->hasPermission('finance.view') && auth()->user()->hasPermission('orders.view'))
    @include('staff.accounting-overview')
  @elseif($role === 'delivery' && auth()->user()->hasPermission('orders.view') && auth()->user()->hasPermission('shipments.manage'))
    @include('staff.delivery-overview')
  @elseif($role === 'manager' && auth()->user()->hasPermission('quotes.view') && auth()->user()->hasPermission('contracts.view') && auth()->user()->hasPermission('purchases.view') && auth()->user()->hasPermission('finance.view'))
    @include('staff.manager-overview')
  @elseif($role === 'admin' && auth()->user()->hasPermission('management.view') && auth()->user()->hasPermission('users.manage') && auth()->user()->hasPermission('quotes.view') && auth()->user()->hasPermission('contracts.view') && auth()->user()->hasPermission('purchases.view') && auth()->user()->hasPermission('inventory.view'))
    @include('staff.admin-overview')
  @else
  @php
    $staffCounts = ['quotes' => $quotes->count(), 'contracts' => $contracts->count(), 'sales_orders' => $salesOrders->count(), 'finance' => $debts->count(), 'purchases' => $purchases->count(), 'orders' => $orders->count(), 'returns' => $returns->count(), 'inventory' => $inventory->count()];
  @endphp
  <div class="staff-overview-grid" aria-label="Bản ghi gần đây">
    @foreach($staffSections as $key => [$label, $icon, $permissions])
      @if(collect($permissions)->contains(fn ($permission) => auth()->user()->hasPermission($permission)))
      <a class="staff-stat" href="{{ route('staff.section', $key) }}"><span class="staff-stat-label">{{ $label }}</span><strong>{{ $staffCounts[$key] }}</strong><small>Xem hồ sơ</small></a>
      @endif
    @endforeach
  </div>
  @endif
  @else
  <div @class(['staff-panel', 'sales-workspace-panel' => $activeSection === 'quotes', 'warehouse-workspace-panel' => in_array($activeSection, ['purchases', 'orders', 'returns', 'inventory'], true), 'finance-workspace-panel' => $activeSection === 'finance'])><div class="staff-workspace-scroll" tabindex="0" role="region" aria-label="Danh sách và thao tác {{ $staffSections[$activeSection][0] ?? 'nghiệp vụ' }}">
  @if(!in_array($activeSection, ['quotes', 'sales_orders'], true) && request()->hasAny(['status', 'direction', 'view', 'shipment_status']))
    @php
      $activeFilterLabel = match (true) {
          request('status') === 'actionable' => 'việc cần xử lý',
          request('status') === 'unsettled' => 'khoản chưa tất toán',
          request('view') === 'pending-invoices' => 'hóa đơn cần lập',
          request('status') => \App\Support\UiFormat::status(request('status')),
          default => '',
      };
      $activeFilterDetails = collect([
          request('direction') ? '· '.(request('direction') === 'receivable' ? 'phải thu' : 'phải trả') : null,
          request('shipment_status') ? '· chuyến '.\App\Support\UiFormat::status(request('shipment_status')) : null,
      ])->filter()->implode(' ');
    @endphp
    <p class="staff-active-filter" role="status"><strong>Đang lọc:</strong> {{ $activeFilterLabel }} {{ $activeFilterDetails }} <a href="{{ route('staff.section', $activeSection) }}">Bỏ lọc</a></p>
  @endif
  @if(auth()->user()->hasPermission('quotes.view') && $activeSection==='quotes')
  @include('staff.sales-quotes')
  @endif
  @if(auth()->user()->hasPermission('sales.orders.view') && $activeSection==='sales_orders')
  @include('staff.sales-orders')
  @endif
  @if(auth()->user()->hasPermission('contracts.view') && $activeSection==='contracts')
  <section id="contracts" class="mb-5"><form method="get" action="{{ route('staff.section','contracts') }}" class="d-flex gap-2 mb-3"><label class="visually-hidden" for="contract-search">Mã hợp đồng</label><input id="contract-search" class="form-control" style="max-width:280px" name="contract" value="{{ request('contract') }}" placeholder="Tìm theo mã hợp đồng"><button class="btn btn-outline-dark">Tìm</button></form>@forelse($contracts as $contract)<details class="order-card" @if(old('contract_id') == $contract->id && $errors->any()) open @endif><summary><strong>{{ $contract->code }}</strong> · {{ $contract->order_code ? 'Đơn '.$contract->order_code : 'Hồ sơ cũ chưa có đơn' }} · {{ \App\Support\UiFormat::status($contract->status) }} · {{ number_format($contract->total,0,',','.') }} ₫</summary><div class="pt-3"><p>Cọc yêu cầu: {{ number_format($contract->deposit_required,0,',','.') }} ₫ · {{ $contract->payment_terms }}</p><p>Giao hàng: {{ $contract->delivery_terms }}</p>@if($contract->order_id)<div class="table-responsive"><table class="table"><thead><tr><th>Vật liệu</th><th>Quy cách</th><th>Số lượng</th><th>Đơn giá</th></tr></thead><tbody>@foreach($contractItems[$contract->order_id] ?? [] as $item)<tr><td>{{ $item->product_name }}</td><td>{{ $item->specification }}</td><td>{{ \App\Support\UiFormat::quantity($item->quantity) }} {{ $item->unit }}</td><td>{{ number_format($item->unit_price,0,',','.') }} ₫</td></tr>@endforeach</tbody></table></div>@endif @if($contract->approval_note)<p>Ý kiến quản lý: {{ $contract->approval_note }}</p>@endif @if($contract->customer_note)<p>Ý kiến khách hàng: {{ $contract->customer_note }}</p>@endif @if($contract->cancellation_note)<p>Lý do hủy: {{ $contract->cancellation_note }}</p>@endif
    @if($contract->debt_id)
      <h3 class="h6 mt-3">Lịch và tình trạng thanh toán</h3>
      @include('shared.payment-schedule-table', ['scheduleRows' => $paymentSchedules[$contract->debt_id] ?? []])
    @endif
    @if(in_array($contract->status,['draft','changes_requested']) && auth()->user()->hasPermission('contracts.create'))
      <form method="post" action="{{ route('staff.contracts.update',$contract->id) }}" class="mt-3">@csrf @method('put')<input type="hidden" name="contract_id" value="{{ $contract->id }}">
        <div class="row g-2"><div class="col-md-6"><label class="form-label">Thanh toán<textarea class="form-control" name="payment_terms" required>{{ old('contract_id') == $contract->id ? old('payment_terms') : $contract->payment_terms }}</textarea></label></div><div class="col-md-6"><label class="form-label">Giao hàng<textarea class="form-control" name="delivery_terms" required>{{ old('contract_id') == $contract->id ? old('delivery_terms') : $contract->delivery_terms }}</textarea></label></div></div>
        @include('staff.partials.payment-installment-editor', ['scheduleTotal' => $contract->total, 'initialInstallments' => old('contract_id') == $contract->id ? old('installments') : ($contract->debt_id ? ($paymentSchedules[$contract->debt_id] ?? []) : json_decode($contract->payment_schedule_draft ?: '[]', true)), 'editorId' => 'edit-contract-schedule-'.$contract->id])
        <button class="btn btn-outline-dark mt-2">Lưu sửa đổi</button>
      </form>
      <form method="post" action="{{ route('staff.contracts.submit',$contract->id) }}" class="mt-2">@csrf<button class="btn btn-dark">Trình Ban quản lý duyệt</button></form>
    @endif
    @if($contract->status==='pending_approval' && auth()->user()->hasPermission('contracts.review'))<form method="post" action="{{ route('staff.contracts.review',$contract->id) }}">@csrf<label class="form-label">Ghi chú<input class="form-control" name="note"></label><button class="btn btn-dark ms-2" name="decision" value="approve">Phê duyệt</button><button class="btn btn-outline-danger ms-2" name="decision" value="revise">Yêu cầu sửa</button></form>@endif
    @if(in_array($contract->status,['draft','pending_approval','changes_requested','approved']) && auth()->user()->hasPermission('contracts.cancel'))<form method="post" action="{{ route('staff.contracts.cancel',$contract->id) }}" class="mt-2">@csrf<label class="form-label">Lý do hủy<input class="form-control" name="reason" maxlength="2000" required></label><button class="btn btn-outline-danger ms-2">Hủy hợp đồng</button></form>@endif
    @if(in_array($contract->status,['approved','signed']) && $contract->customer_confirmed_at && auth()->user()->hasPermission('contracts.create'))<div class="my-3"><a class="btn btn-outline-dark" href="{{ route('staff.contracts.pdf',$contract->id) }}">Xuất hợp đồng PDF</a><p class="small text-muted mt-2">Tải hợp đồng đã điền sẵn để hai bên ký, sau đó tải bản đã ký lên bên dưới.</p></div>@endif
    @if($contract->status==='approved' && $contract->customer_confirmed_at && auth()->user()->hasPermission('contracts.create'))<form method="post" enctype="multipart/form-data" action="{{ route('staff.contracts.sign',$contract->id) }}">@csrf<label class="form-label">Bản hợp đồng đã ký (PDF hoặc ảnh)<input class="form-control" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label><button class="btn btn-dark ms-2">Ghi nhận đã ký</button></form>@endif</div></details>@empty<p class="contract-empty">Chưa có hợp đồng.</p>@endforelse @if(method_exists($contracts, 'links')){{ $contracts->links() }}@endif</section>
  @endif
  @if(auth()->user()->hasPermission('finance.view') && $activeSection==='finance')
    <section id="finance" class="mb-5">
      <h2>Tài chính & công nợ</h2>
      <form method="get" action="{{ route('staff.section','finance') }}" class="order-card d-flex flex-wrap gap-2 align-items-end mb-3">
        <label>Đối tượng
          <select class="form-select" name="direction"><option value="">Tất cả</option><option value="receivable" @selected(request('direction') === 'receivable')>Phải thu khách hàng</option><option value="payable" @selected(request('direction') === 'payable')>Phải trả nhà cung cấp</option></select>
        </label>
        <label>Tình trạng
          <select class="form-select" name="status"><option value="">Tất cả</option><option value="unsettled" @selected(request('status') === 'unsettled')>Chưa tất toán</option><option value="settled" @selected(request('status') === 'settled')>Đã tất toán</option></select>
        </label>
        <button class="btn btn-outline-dark">Lọc công nợ</button>
        @if(request()->hasAny(['direction','status']))<a class="btn btn-link" href="{{ route('staff.section','finance') }}">Xóa bộ lọc</a>@endif
      </form>
      <div class="table-responsive"><table class="table"><thead><tr><th>Mã</th><th>Đơn / Hợp đồng</th><th>Khách hàng / Nhà cung cấp</th><th>Loại</th><th>Giá trị</th><th>Đã trả</th><th>Còn lại</th><th>Ghi nhận thanh toán</th></tr></thead><tbody>
      @foreach($debts as $debt)
        <tr>
          <td>{{ $debt->code }}</td><td>{{ $debt->order_code ?? '—' }} @if($debt->contract_code)· {{ $debt->contract_code }}@endif</td>
          <td>{{ $debt->direction === 'payable' ? ($debt->supplier_name ?? 'Nhà cung cấp chưa rõ') : ($debt->customer_name ?? 'Khách hàng chưa rõ') }}</td>
          <td>{{ $debt->direction==='payable' ? 'Phải trả' : ((in_array($debt->order_status, ['awaiting_confirmation', 'awaiting_contract', 'rejected', 'cancelled'], true) || ($debt->requires_contract && $debt->contract_status !== 'signed')) ? 'Dự kiến / chưa phát sinh' : 'Phải thu') }}</td>
          <td>{{ number_format($debt->amount,0,',','.') }} ₫</td><td>{{ number_format($debt->paid,0,',','.') }} ₫</td><td>{{ number_format(max(0,$debt->amount-$debt->paid),0,',','.') }} ₫</td>
          <td>
            @if($debt->paid<$debt->amount && auth()->user()->hasPermission('payments.record') && !in_array($debt->order_status, ['awaiting_confirmation', 'awaiting_contract', 'rejected', 'cancelled'], true) && (!$debt->requires_contract || $debt->contract_status === 'signed'))
              <form class="d-flex gap-1 flex-wrap" method="post" action="{{ route('staff.payments.record',$debt->id) }}">@csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input class="form-control form-control-sm" style="width:105px" type="number" name="amount" min="0.01" step="0.01" placeholder="Số tiền" required data-payment-amount>
                <select class="form-select form-select-sm" style="width:130px" name="method"><option value="cash">Tiền mặt</option>@if($debt->direction === 'payable')<option value="bank_transfer">Chuyển khoản</option>@endif</select>
                <input class="form-control form-control-sm" style="width:110px" name="reference" placeholder="Mã giao dịch"><button class="btn btn-dark btn-sm">Ghi nhận</button>
                @if($debt->direction === 'receivable' && auth()->user()->role === 'accounting')<a class="btn btn-outline-dark btn-sm" href="{{ route('staff.vietqr.index') }}">Tạo VietQR</a>@endif
                @include('staff.partials.payment-allocation-preview', ['scheduleRows' => $paymentSchedules[$debt->id] ?? []])
              </form>
            @elseif($debt->order_code && $debt->requires_contract && $debt->contract_status !== 'signed')<small>Chờ hợp đồng đã ký</small>@endif
          </td>
        </tr>
        @if(!empty($paymentSchedules[$debt->id]))<tr><td colspan="8"><details><summary>Lịch thanh toán · {{ $debt->direction === 'payable' ? ($debt->supplier_name ?? 'Nhà cung cấp') : ($debt->customer_name ?? 'Khách hàng') }}</summary>@include('shared.payment-schedule-table', ['scheduleRows' => $paymentSchedules[$debt->id]])</details></td></tr>@endif
      @endforeach
      </tbody></table></div>
      @if(method_exists($debts, 'links')){{ $debts->links() }}@endif
    </section>
  @endif
  @if(auth()->user()->hasPermission('finance.view') && $activeSection==='finance')
    @include('staff.debt-schedule')
    @include('staff.customer-credits')
    @include('staff.supplier-return-settlement')
  @endif
  @if(auth()->user()->hasPermission('purchases.view') && $activeSection==='purchases')
  <section id="purchases" class="warehouse-purchases mb-5" aria-label="Nhập hàng">
    @if(auth()->user()->hasPermission('purchases.manage'))@include('staff.warehouse-purchase-form')@endif
    @forelse($purchases as $purchase)<details class="order-card" @if(old('purchase_order_id') == $purchase->id && $errors->any()) open @endif><summary><strong>{{ $purchase->code }}</strong> · {{ \App\Support\UiFormat::status($purchase->status) }} · {{ number_format($purchase->total,0,',','.') }} ₫</summary><div class="pt-3"><p class="mb-2"><strong>Nhà cung cấp:</strong> {{ $purchase->supplier_name ?: 'Không lưu thông tin' }} · <strong>Kho nhận:</strong> {{ $purchase->warehouse_name ?: 'Không lưu thông tin' }}</p><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Vật liệu</th><th>Đặt</th><th>Đã nhận</th><th>Còn nhận</th><th>Giá nhập</th></tr></thead><tbody>@foreach($purchaseItems[$purchase->id] ?? [] as $item)<tr><td>{{ $item->product_name }} · {{ $item->unit }}</td><td>{{ \App\Support\UiFormat::quantity($item->quantity) }}</td><td>{{ \App\Support\UiFormat::quantity($item->received_quantity) }}</td><td>{{ \App\Support\UiFormat::quantity(max(0, $item->quantity-$item->received_quantity)) }}</td><td>{{ number_format($item->unit_price,0,',','.') }} ₫</td></tr>@endforeach</tbody></table></div>@if($purchase->status==='pending' && auth()->user()->hasPermission('purchases.approve'))<form method="post" action="{{ route('staff.purchases.approve',$purchase->id) }}">@csrf<button class="btn btn-dark">Phê duyệt nhập hàng</button></form>@endif @if($purchase->status==='approved' && auth()->user()->hasPermission('purchases.manage'))<form method="post" action="{{ route('staff.purchases.receive',$purchase->id) }}">@csrf<input type="hidden" name="purchase_order_id" value="{{ $purchase->id }}">@foreach($purchaseItems[$purchase->id] ?? [] as $item)<label class="form-label me-3">{{ $item->product_name }} · còn {{ \App\Support\UiFormat::quantity($item->quantity-$item->received_quantity) }}<input class="form-control @error('received.'.$item->id) is-invalid @enderror" type="number" name="received[{{ $item->id }}]" min="0" max="{{ $item->quantity-$item->received_quantity }}" step="1" value="{{ old('purchase_order_id') == $purchase->id ? old('received.'.$item->id, 0) : 0 }}" required>@if(old('purchase_order_id') == $purchase->id && $errors->has('received.'.$item->id))<span class="invalid-feedback">{{ $errors->first('received.'.$item->id) }}</span>@endif</label>@endforeach<button class="btn btn-dark">Xác nhận số lượng thực nhận</button></form>@endif</div></details>@empty<p class="warehouse-empty">Không tìm thấy phiếu nhập hàng phù hợp bộ lọc.</p>@endforelse
    @if(method_exists($purchases, 'links')){{ $purchases->links() }}@endif
    @include('staff.supplier-incidents')
  </section>@endif
  @if(auth()->user()->hasPermission('orders.view') && $activeSection==='orders')<section id="orders" class="mb-5" aria-label="Danh sách đơn cần xuất kho và giao nhận">@foreach($orders as $order)<div class="order-card d-flex justify-content-between align-items-center flex-wrap gap-2"><span><strong>{{ $order->code }}</strong> · {{ \App\Support\UiFormat::status($order->status) }}<small class="d-block muted">{{ $order->delivery_address }}</small></span>@if($order->status==='ready_to_issue' && !isset($invoices[$order->id]) && auth()->user()->hasPermission('invoices.issue'))<form method="post" action="{{ route('staff.orders.invoice',$order->id) }}">@csrf<button class="btn btn-outline-dark">Lập hóa đơn</button></form>@endif @if(isset($invoices[$order->id]))<span class="small">Hóa đơn {{ $invoices[$order->id]->code }}</span>@endif @if($order->status==='ready_to_issue' && $order->released_by && auth()->user()->hasPermission('inventory.issue'))<form method="post" action="{{ route('staff.orders.issue',$order->id) }}">@csrf @if($order->fulfillment_method==='pickup')<input type="hidden" name="warehouse_id" value="{{ $order->pickup_warehouse_id }}"><span class="small">Kho nhận: {{ $warehouses->firstWhere('id',$order->pickup_warehouse_id)->name ?? '' }}</span>@else<select class="form-select d-inline-block" style="width:180px" name="warehouse_id">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select>@endif<button class="btn btn-dark">Duyệt xuất kho</button></form>@endif @if($order->status==='ready_to_ship' && $order->fulfillment_method==='pickup' && auth()->user()->hasPermission('warehouse.fulfill'))<form method="post" action="{{ route('staff.orders.pickup',$order->id) }}">@csrf<input class="form-control d-inline-block" style="width:180px" name="recipient" placeholder="Tên người nhận" required><button class="btn btn-dark">Bàn giao tại kho</button></form>@endif @if($order->status==='ready_to_ship' && $order->fulfillment_method!=='pickup' && auth()->user()->hasPermission('warehouse.fulfill'))<form class="d-flex gap-1 flex-wrap" method="post" action="{{ route('staff.orders.ship',$order->id) }}">@csrf<input class="form-control" style="width:140px" name="carrier_name" placeholder="Đơn vị vận chuyển" required><input class="form-control" style="width:110px" name="vehicle" placeholder="Phương tiện" required><input class="form-control" style="width:160px" name="route_note" placeholder="Lộ trình" required><button class="btn btn-dark">Lập chuyến</button></form>@endif</div>@endforeach
    @if(method_exists($orders, 'links')){{ $orders->links() }}@endif
    @foreach($shipments as $shipment)@if(in_array($shipment->status,['planned','shipping']) && auth()->user()->hasPermission('shipments.manage'))<details class="order-card"><summary>{{ $shipment->code }} · {{ \App\Support\UiFormat::status($shipment->status) }}</summary>@if($shipment->status==='planned')<form class="pt-3 d-flex gap-2 flex-wrap" method="post" action="{{ route('staff.shipments.pickup',$shipment->id) }}">@csrf<input class="form-control" style="width:220px" name="receiver" placeholder="Người vận chuyển nhận hàng" required><button class="btn btn-dark">Xác nhận nhận hàng</button></form>@else<form class="pt-3 d-flex gap-2 flex-wrap" method="post" action="{{ route('staff.shipments.handover',$shipment->id) }}">@csrf<input class="form-control" style="width:170px" name="recipient" placeholder="Người nhận" required><input class="form-control" style="width:220px" name="condition_note" placeholder="Tình trạng / biên bản" required><select class="form-select" style="width:150px" name="accepted"><option value="1">Đã nhận đủ</option><option value="0">Có sự cố</option></select><button class="btn btn-dark">Xác nhận bàn giao</button></form>@endif</details>@endif @endforeach @foreach($shipments as $shipment)@if($shipment->status==='incident' && auth()->user()->hasPermission('shipments.manage'))<details class="order-card"><summary>{{ $shipment->code }} · Giao hàng gặp sự cố</summary><form class="pt-3 d-flex gap-2 flex-wrap" method="post" action="{{ route('staff.shipments.reschedule',$shipment->id) }}">@csrf<input class="form-control" style="width:160px" name="vehicle" placeholder="Phương tiện mới" required><input class="form-control" style="width:230px" name="route_note" placeholder="Lộ trình và thời gian giao lại" required><button class="btn btn-dark">Lên lịch giao lại</button></form></details>@endif @endforeach @if(method_exists($shipments, 'links')){{ $shipments->links() }}@endif</section>@endif
  @if($activeSection==='orders')
    @include('staff.delivery-runs')
  @endif
  @if(auth()->user()->hasPermission('returns.view') && $activeSection==='returns')<section id="returns" class="mb-5"><h2>Danh sách yêu cầu đổi trả</h2>@forelse($returns as $return)<div class="order-card"><strong>{{ $return->code }}</strong> · {{ \App\Support\UiFormat::status($return->status) }} · {{ $return->reason }} · {{ \App\Support\UiFormat::quantity($return->quantity) }}@if($return->estimated_available_on)<small class="d-block text-warning-emphasis">Hàng đổi thiếu tồn · ngày dự kiến {{ \Carbon\Carbon::parse($return->estimated_available_on)->format('d/m/Y') }}, chưa được nhà cung cấp xác nhận. @if($return->restock_purchase_order_id) Đề xuất nhập #{{ $return->restock_purchase_order_id }} đang chờ xử lý. @else Chưa có nhà cung cấp đã nhập mặt hàng này; kho cần chọn nhà cung cấp để lập đề xuất nhập. @endif</small>@elseif($return->type === 'exchange' && $return->status === 'inspected')<small class="d-block text-warning-emphasis">Hàng đổi đang thiếu tồn; chưa có ngày nhà cung cấp dự kiến. @if($return->restock_purchase_order_status === 'pending') Đề xuất nhập đang chờ duyệt. @elseif($return->restock_purchase_order_status === 'approved') Đề xuất đã duyệt; kho đang chờ nhận hàng. @elseif(!$return->restock_purchase_order_id) Kho cần chọn nhà cung cấp để lập đề xuất nhập. @endif</small>@endif @if($return->collected_at)<small class="d-block">Đã thu hồi: {{ $return->handed_over_by }} · {{ $return->collection_note }}</small>@endif @if($return->status==='requested' && auth()->user()->hasPermission('returns.collect'))<form class="mt-2 d-flex gap-2 flex-wrap" method="post" action="{{ route('staff.returns.collect',$return->id) }}">@csrf<input class="form-control" style="width:180px" name="handed_over_by" placeholder="Người bàn giao" required><input class="form-control" style="width:130px" type="number" step="1" min="1" name="quantity" value="{{ \App\Support\UiFormat::inputQuantity($return->quantity) }}" aria-label="Số lượng thu hồi" required><input class="form-control" style="width:250px" name="note" placeholder="Biên bản, tình trạng hàng" required><button class="btn btn-dark">Xác nhận thu hồi</button></form>@endif @if($return->status==='collected' && auth()->user()->hasPermission('returns.manage'))@include('staff.partials.return-inspection-form', ['return' => $return, 'warehouses' => $warehouses])@endif @if($return->type==='exchange' && $return->status==='inspected' && !$return->restock_purchase_order_id && auth()->user()->hasPermission('returns.manage'))@include('staff.partials.return-restock-form', ['return' => $return, 'suppliers' => $suppliers])@endif @if($return->type==='exchange' && $return->status==='inspected' && auth()->user()->hasPermission('returns.manage'))<form class="mt-2 d-flex gap-2 flex-wrap" method="post" action="{{ route('staff.returns.exchange',$return->id) }}">@csrf<select class="form-select" name="warehouse_id" style="width:180px">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select><input class="form-control" name="carrier_name" placeholder="Đơn vị giao" required><input class="form-control" name="vehicle" placeholder="Phương tiện" required><input class="form-control" name="route_note" placeholder="Lộ trình" required><button class="btn btn-dark">Giao hàng thay thế</button></form>@endif @if($return->type==='return' && $return->status==='inspected' && auth()->user()->hasPermission('returns.settle'))<form method="post" action="{{ route('staff.returns.settle',$return->id) }}" class="mt-2">@csrf<button class="btn btn-dark">Điều chỉnh công nợ {{ number_format($return->credit_amount,0,',','.') }} ₫</button></form>@endif @include('staff.supplier-return-action')</div>@empty<div class="warehouse-empty warehouse-empty-center">Không có yêu cầu đổi trả</div>@endforelse @if(method_exists($returns, 'links')){{ $returns->links() }}@endif</section>@endif
  @if(auth()->user()->hasPermission('inventory.view') && $activeSection==='inventory')
    @include('staff.warehouse-inventory')
  @endif
  </div></div>@endif
</div>
@endsection















