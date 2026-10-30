<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>{{ $kind === 'invoice' ? 'Hóa đơn' : 'Phiếu thu' }}</title>
    <style>
        @page { margin: 30pt 34pt 43pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.45; color: #243b34; }
        * { box-sizing: border-box; }
        h1 { font-size: 21pt; line-height: 1.25; margin: 20pt 0 5pt; color: #235d4d; }
        h2 { font-size: 11pt; margin: 18pt 0 8pt; color: #235d4d; }
        p { margin: 3pt 0; }
        .house { padding-bottom: 12pt; border-bottom: 1pt solid #bfd0c7; }
        .house-name { font-size: 14pt; font-weight: bold; margin-bottom: 4pt; }
        .muted { color: #63736d; font-size: 8pt; }
        .small { font-size: 8pt; }
        .break { word-wrap: break-word; overflow-wrap: break-word; white-space: pre-wrap; }
        .status { font-size: 10pt; font-weight: bold; margin: 6pt 0 12pt; }
        .cancelled { color: #9c3d2f; border: 1pt solid #d6a8a0; padding: 10pt; margin: 10pt 0; background: #fff5f1; }
        .facts { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; }
        .facts td { vertical-align: top; padding: 4pt 0; }
        .facts .label { width: 25%; color: #63736d; }
        .facts .value { width: 75%; }
        table.lines { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 8pt 0 12pt; }
        .lines thead { display: table-header-group; }
        .lines th { padding: 8pt 5pt; background: #edf4ef; border-bottom: 1pt solid #bfd0c7; font-size: 8pt; text-align: left; }
        .lines td { padding: 8pt 5pt; border-bottom: .5pt solid #dce5df; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        .lines tr { page-break-inside: avoid; }
        .lines .number { text-align: right; }
        .numeric { white-space: nowrap; }
        .numeric.compact { font-size: 7pt; }
        .money { white-space: nowrap; }
        .totals { width: 100%; border-collapse: collapse; margin: 12pt 0; page-break-inside: avoid; }
        .totals td { padding: 4pt 8pt; }
        .totals .number { text-align: right; width: 38%; }
        .totals .emphasis td { font-weight: bold; font-size: 11pt; padding-top: 9pt; border-top: 1pt solid #bfd0c7; }
        .receipt { padding: 10pt 0; border-bottom: .5pt solid #dce5df; page-break-inside: avoid; }
        .receipt-title { font-weight: bold; font-size: 10pt; }
        .note { margin: 12pt 0; padding: 9pt 11pt; background: #f3f6f2; border-left: 2pt solid #bfd0c7; }
        .amount { font-size: 21pt; color: #235d4d; font-weight: bold; margin: 14pt 0; }
        footer { position: fixed; bottom: -27pt; left: 0; right: 75pt; font-size: 7pt; color: #63736d; }
    </style>
</head>
<body>
@php
    $money = fn ($value) => \App\Services\PdfDocuments::decimal($value).' đ';
    $decimal = fn ($value, $scale = 3) => \App\Services\PdfDocuments::decimal($value, $scale);
    $text = fn ($value) => \App\Services\PdfDocuments::text($value);
    $wideQuantity = collect($invoice['items'])->contains(fn ($item) => mb_strlen($decimal($item['quantity'])) > 12);
@endphp
<footer>Chứng từ quản lý nội bộ · Xuất {{ $exported_at }} · Giờ Việt Nam</footer>
<div class="house">
    @if($profile)
        <div class="house-name break">{{ $text($profile['name'] ?? '') }}</div>
        @if($profile['address'] ?? null)<p class="break">{{ $text($profile['address']) }}</p>@endif
        @if(($profile['contact_name'] ?? null) || ($profile['phone'] ?? null))<p class="break">Liên hệ: {{ $text(implode(' · ', array_filter([$profile['contact_name'] ?? null, $profile['phone'] ?? null]))) }}</p>@endif
        @if($profile['email'] ?? null)<p class="break">{{ $text($profile['email']) }}</p>@endif
    @else
        <div class="house-name">Thông tin nhà trọ chưa được cấu hình</div>
    @endif
</div>

<h1>{{ $kind === 'invoice' ? 'HÓA ĐƠN TIỀN THUÊ' : 'PHIẾU THU' }}</h1>
<p class="muted break">{{ $text($kind === 'invoice' ? $invoice['number'] : $receipt['number']) }} · Kỳ hóa đơn {{ $invoice['billing_month'] }}</p>

@if($kind === 'invoice')
    <p class="status">{{ $invoice['status_label'] }}</p>
    @if($invoice['status'] === 'cancelled')<div class="cancelled"><strong>HÓA ĐƠN ĐÃ HỦY</strong><p>Không dùng để yêu cầu thanh toán. Các dòng gốc và lịch sử bên dưới được giữ để đối chiếu.</p><p>Thời điểm hủy: {{ $invoice['cancelled_at'] }}</p></div>@endif
    @if($invoice['status'] === 'inconsistent')<div class="cancelled"><strong>CẦN ĐỐI CHIẾU SỐ LIỆU</strong><p>Vui lòng liên hệ chủ trọ để kiểm tra trước khi thanh toán.</p></div>@endif
@elseif($receipt['voided'])
    <div class="cancelled"><strong>ĐÃ HỦY GHI NHẬN</strong><p>Phiếu gốc được giữ để đối chiếu, không còn tính vào số tiền đã thu. Đây không phải phiếu thu còn hiệu lực.</p></div>
@else
    <p class="status">Phiếu thu có hiệu lực</p>
@endif

<table class="facts"><tbody>
    <tr><td class="label">Phòng / nhóm</td><td class="value break">{{ $text($invoice['room']) }} / {{ $text($invoice['group']) }}</td></tr>
    @if($kind === 'receipt')<tr><td class="label">Hóa đơn liên quan</td><td class="value break">{{ $text($invoice['number']) }}</td></tr>@endif
    @if($kind === 'invoice')
        <tr><td class="label">Phát hành hóa đơn</td><td class="value">{{ $invoice['issued_at'] }}</td></tr>
        <tr><td class="label">Hạn thanh toán</td><td class="value">{{ $invoice['due_date'] ?? 'Chưa đặt hạn' }}</td></tr>
    @endif
    @if($kind === 'receipt')
        <tr><td class="label">Thực nhận lúc</td><td class="value">{{ $receipt['paid_at'] }}</td></tr>
        <tr><td class="label">Ghi vào sổ lúc</td><td class="value">{{ $receipt['recorded_at'] }}</td></tr>
        <tr><td class="label">Hình thức</td><td class="value">{{ $receipt['method'] }}</td></tr>
    @endif
</tbody></table>

@if($kind === 'receipt')
    <div class="amount">{{ $money($receipt['amount']) }}</div>
    @if($receipt['replaces_id'])<p class="note">Phiếu này thay thế phiếu PT-{{ $receipt['replaces_id'] }}. Cần đối chiếu trạng thái hiện tại ghi trên từng phiếu.</p>@endif
    @if($receipt['correction'])
        <div class="note"><strong>Lịch sử sửa sai phiếu gốc</strong><p>Đã xử lý lúc {{ $receipt['correction']['created_at'] }}</p><p class="break">Lý do: {{ $text($receipt['correction']['reason']) }}</p><p>{{ $receipt['correction']['replacement_id'] ? 'Phiếu thay thế: PT-'.$receipt['correction']['replacement_id'].'.' : 'Không tạo phiếu thay thế.' }}</p></div>
    @endif
    <h2>Số dư hóa đơn tại thời điểm xuất</h2>
@else
    <h2>Chi tiết các khoản gốc</h2>
    <table class="lines"><thead><tr><th style="width:{{ $wideQuantity ? 39 : 43 }}%">Nội dung</th><th class="number" style="width:{{ $wideQuantity ? 17 : 13 }}%">Số lượng</th><th class="number" style="width:22%">Đơn giá</th><th class="number" style="width:22%">Thành tiền</th></tr></thead><tbody>
        @forelse($invoice['items'] as $item)
            <tr><td class="break">{{ $text($item['description']) }}<p class="muted">Đơn vị: {{ $text($item['unit']) }}</p>@if($item['period_start'])<p class="muted">{{ $item['period_start'] }} - {{ $item['period_end'] }}</p>@endif @if($item['meter'])<p class="muted">Chỉ số: {{ $decimal($item['meter']['previous']) }} - {{ $decimal($item['meter']['current']) }}<br>Tiêu thụ: {{ $decimal($item['meter']['consumption']) }}</p>@endif</td><td class="number"><span class="numeric {{ mb_strlen($decimal($item['quantity'])) > 12 ? 'compact' : '' }}">{{ $decimal($item['quantity']) }}</span></td><td class="number"><span class="numeric {{ mb_strlen($decimal($item['unit_price'], 4)) > 20 ? 'compact' : '' }}">{{ $decimal($item['unit_price'], 4) }}</span></td><td class="number"><span class="numeric {{ mb_strlen($money($item['line_total'])) > 21 ? 'compact' : '' }}">{{ $money($item['line_total']) }}</span></td></tr>
        @empty
            <tr><td colspan="4">Chưa có dòng tính tiền.</td></tr>
        @endforelse
    </tbody></table>
@endif

<table class="totals"><tbody>
    @if($kind === 'invoice')
        <tr><td>Tổng các khoản gốc</td><td class="number">{{ $money($invoice['original_amount']) }}</td></tr>
        <tr><td>Điều chỉnh tăng / giảm</td><td class="number">{{ $money($invoice['adjustment_amount']) }}</td></tr>
    @endif
    <tr><td>Tổng hóa đơn có hiệu lực</td><td class="number">{{ $money($invoice['total_amount']) }}</td></tr>
    <tr><td>Đã thu còn hiệu lực</td><td class="number">{{ $money($invoice['paid_amount']) }}</td></tr>
    <tr class="emphasis"><td>Còn phải thu</td><td class="number">{{ $money($invoice['outstanding_amount']) }}</td></tr>
</tbody></table>

@if($kind === 'invoice' && count($invoice['changes']))
    <h2>Lịch sử điều chỉnh hóa đơn</h2>
    @foreach($invoice['changes'] as $change)
        <div class="receipt"><p><strong>{{ $change['label'] }}</strong> · {{ $change['created_at'] }}@if($change['action'] === 'adjustment') · {{ $money($change['amount']) }}@endif</p><p class="break">{{ $text($change['reason']) }}</p></div>
    @endforeach
@endif

@if($kind === 'invoice')
    <h2>Lịch sử các lần thu</h2>
    @forelse($payments as $payment)
        <div class="receipt"><p class="receipt-title">{{ $payment['number'] }} · {{ $money($payment['amount']) }} · {{ $payment['voided'] ? 'Đã hủy ghi nhận' : 'Có hiệu lực' }}</p><p>{{ $payment['paid_at'] }} · {{ $payment['method'] }}</p>@if($payment['replaces_id'])<p>Thay thế phiếu PT-{{ $payment['replaces_id'] }}.</p>@endif @if($payment['correction'])<p class="muted">Sửa sai lúc {{ $payment['correction']['created_at'] }}{{ $payment['correction']['replacement_id'] ? ' · Phiếu thay thế PT-'.$payment['correction']['replacement_id'] : '' }}</p><p class="break">Lý do: {{ $text($payment['correction']['reason']) }}</p>@endif</div>
    @empty
        <p class="muted">Chưa ghi nhận lần thu nào.</p>
    @endforelse
@endif

@if($kind === 'invoice' && in_array($invoice['status'], ['unpaid', 'partially_paid'], true) && $profile && ($profile['bank_name'] ?? null) && ($profile['bank_account_number'] ?? null))
    <h2>Thông tin nhận chuyển khoản</h2>
    <div class="note"><p class="break">Ngân hàng: {{ $text($profile['bank_name']) }}</p><p class="break">Số tài khoản: {{ $text($profile['bank_account_number']) }}</p>@if($profile['bank_account_name'] ?? null)<p class="break">Chủ tài khoản: {{ $text($profile['bank_account_name']) }}</p>@endif<p class="break">Nội dung đối chiếu: {{ $text($invoice['number']) }}</p></div>
@endif
@if($profile['notes'] ?? null)<p class="note break">{{ $text($profile['notes']) }}</p>@endif
<p class="muted" style="margin-top:15pt">Số dư, trạng thái chứng từ và thông tin nhà trọ là dữ liệu tại thời điểm xuất {{ $exported_at }}. Bản tải trước đó không tự cập nhật khi có điều chỉnh về sau.</p>
</body>
</html>
