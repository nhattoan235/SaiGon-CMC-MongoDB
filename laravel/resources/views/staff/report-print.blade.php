<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $report->code }} | Sài Gòn CMC</title>
<style>body{font:14px 'DejaVu Sans',Arial,sans-serif;color:#18251f;margin:0;background:#f3f5f2}.page{max-width:900px;margin:28px auto;padding:42px 48px;background:#fff;box-shadow:0 8px 32px #16261b12}.brand{color:#27654c;letter-spacing:.08em;font-size:12px}.report-header{border-bottom:2px solid #27654c;padding-bottom:20px;margin-bottom:28px}h1{font-size:28px;margin:12px 0}h2{font-size:18px;margin:0 0 14px}.meta{color:#52625a;line-height:1.7}.section{break-inside:avoid-page;margin-top:30px}table{width:100%;border-collapse:collapse;margin:16px 0 25px}th,td{border-bottom:1px solid #dce4df;padding:11px 12px;text-align:left}th{background:#eff5f1;color:#254d3b}.actions{max-width:900px;margin:24px auto 0;display:flex;gap:16px;align-items:center}.actions button{background:#27654c;color:white;border:0;border-radius:6px;padding:10px 16px;cursor:pointer}.actions a{color:#27654c}.chart{margin:12px 0 22px}.chart-row{display:grid;grid-template-columns:minmax(110px,22%) 1fr minmax(90px,18%);gap:12px;align-items:center;margin:10px 0}.chart-label{overflow-wrap:anywhere}.chart-track{background:#e8efea;height:12px;border-radius:6px;overflow:hidden}.chart-bar{display:block;background:#2d6950;height:12px;border-radius:6px}.chart-value{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}.empty{color:#64746b}@media screen and (max-width:600px){.page{margin:0;padding:24px 16px;box-shadow:none}.actions{margin:16px}.chart-row{grid-template-columns:90px 1fr 80px;gap:8px;font-size:12px}table,tbody,tr,td{display:block}table{border:0}thead{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}tbody tr{border:1px solid #dce4df;border-radius:8px;overflow:hidden;margin-bottom:12px}td{display:grid;grid-template-columns:minmax(95px,40%) minmax(0,1fr);gap:12px;border:0;border-bottom:1px solid #e5e5e5;padding:10px 12px;overflow-wrap:anywhere}td:last-child{border-bottom:0}td::before{content:attr(data-label);font-weight:700;color:#52625a}}@media print{body{background:white}.actions{display:none}.page{max-width:none;margin:0;padding:0;box-shadow:none}.section{break-inside:avoid-page}@page{size:A4;margin:16mm}}</style>
</head>
<body>
@php
    $reportTypes = ['finance' => 'Tài chính', 'inventory' => 'Tồn kho', 'sales' => 'Kinh doanh', 'logistics' => 'Giao nhận'];
    $sections = ['payments' => 'Thanh toán', 'debts' => 'Công nợ', 'stock' => 'Tồn kho', 'movements' => 'Biến động kho', 'orders' => 'Đơn hàng', 'contracts' => 'Hợp đồng', 'shipments' => 'Chuyến giao hàng', 'returns' => 'Hàng trả'];
    $fields = ['code' => 'Mã chứng từ', 'status' => 'Trạng thái', 'total' => 'Tổng tiền', 'paid' => 'Đã thanh toán', 'amount' => 'Giá trị', 'method' => 'Phương thức', 'transactions' => 'Số giao dịch', 'direction' => 'Loại công nợ', 'warehouse' => 'Kho', 'product' => 'Mã vật liệu', 'quantity' => 'Số lượng', 'minimum_stock' => 'Tồn tối thiểu', 'product_id' => 'Mã vật liệu', 'change' => 'Thay đổi', 'created_at' => 'Ngày tạo', 'carrier_name' => 'Đơn vị vận chuyển', 'delivered_at' => 'Ngày giao', 'credit_amount' => 'Giá trị hoàn'];
    $methods = ['cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản'];
    $directions = ['receivable' => 'Phải thu', 'payable' => 'Phải trả'];
@endphp
@if(!($download ?? false))<div class="actions"><button onclick="window.print()">In hoặc lưu PDF</button> <a href="{{ route('staff.reports.index') }}">Quay lại</a></div>@endif
<main class="page"><header class="report-header"><strong class="brand">SÀI GÒN CMC · VẬT LIỆU XÂY DỰNG</strong><h1>Báo cáo {{ $report->code }}</h1><p class="meta">{{ $reportTypes[$report->type] ?? 'Báo cáo' }} · Kỳ {{ $report->from_date }} đến {{ $report->to_date }} · Đã phê duyệt</p></header>
@foreach($snapshot as $section => $rows)
<section class="section"><h2>{{ $sections[$section] ?? 'Dữ liệu' }}</h2>
@if(count($rows))
@php
    $chartRows = collect($rows)->take(8)->map(fn ($row, $index) => [
        'label' => (string) ($row['code'] ?? $row['product'] ?? $methods[$row['method'] ?? ''] ?? $directions[$row['direction'] ?? ''] ?? $row['warehouse'] ?? ($index + 1)),
        'value' => (float) ($row['amount'] ?? $row['total'] ?? $row['quantity'] ?? $row['transactions'] ?? 1),
    ]);
    $chartMax = max(1, $chartRows->max('value'));
    $chartCurrency = in_array($section, ['payments', 'debts', 'orders', 'contracts', 'returns'], true);
@endphp
<div class="chart" role="img" aria-label="Biểu đồ {{ $sections[$section] ?? $section }}">
@foreach($chartRows as $bar)
<div class="chart-row"><span class="chart-label">{{ $bar['label'] }}</span><span class="chart-track"><span class="chart-bar" style="width:{{ round(100 * $bar['value'] / $chartMax) }}%"></span></span><span class="chart-value">{{ number_format($bar['value'], 0, ',', '.') }}@if($chartCurrency) ₫@endif</span></div>
@endforeach
</div>
<table>
<thead><tr>
@foreach(array_keys((array) $rows[0]) as $field)
<th>{{ $fields[$field] ?? 'Thông tin' }}</th>
@endforeach
</tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
@foreach((array) $row as $field => $value)
<td data-label="{{ $fields[$field] ?? 'Thông tin' }}">@if(in_array($field, ['amount', 'paid', 'total', 'credit_amount'])){{ number_format((float) $value, 0, ',', '.') }} ₫@elseif(in_array($field, ['quantity', 'minimum_stock', 'change'])){{ \App\Support\UiFormat::quantity($value) }}@elseif($field === 'status'){{ \App\Support\UiFormat::status($value) }}@elseif($field === 'direction'){{ $directions[$value] ?? $value }}@elseif($field === 'method'){{ $methods[$value] ?? $value }}@else{{ $value }}@endif</td>
@endforeach
</tr>
@endforeach
</tbody></table>
@else
<p class="empty">Không có dữ liệu trong kỳ.</p>
@endif
 </section>@endforeach</main>
</body></html>
