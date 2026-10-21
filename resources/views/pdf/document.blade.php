<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>{{ $cfg['doc'] }} {{ $doc->number }}</title>
<style>
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { font-size: 11px; color: #1F2937; margin: 0; }
    .header { border-bottom: 3px solid #FF6B00; padding-bottom: 12px; margin-bottom: 18px; }
    .company { font-size: 16px; font-weight: bold; color: #FF6B00; }
    .muted { color: #6B7280; }
    .title { font-size: 20px; font-weight: bold; text-transform: uppercase; text-align: right; }
    table { width: 100%; border-collapse: collapse; }
    .items th { background: #FFF1E6; color: #E65C00; padding: 7px 6px; text-align: left; font-size: 10px; text-transform: uppercase; }
    .items td { padding: 7px 6px; border-bottom: 1px solid #E5E7EB; }
    .right { text-align: right; }
    .totals td { padding: 4px 6px; }
    .grand td { font-size: 14px; font-weight: bold; color: #E65C00; border-top: 2px solid #FF6B00; padding-top: 8px; }
    .box { background: #F8FAFC; padding: 10px; border-radius: 4px; }
    .sign td { width: 50%; text-align: center; vertical-align: top; padding-top: 40px; }
    .demo { color: #B45309; font-weight: bold; text-align: center; border: 1px dashed #F59E0B; padding: 4px; margin-bottom: 10px; }
</style>
</head>
<body>
@if ($doc->is_demo)<div class="demo">{{ __('DỮ LIỆU DEMO — KHÔNG CÓ GIÁ TRỊ THỰC TẾ') }}</div>@endif
<table class="header">
    <tr>
        <td style="width:60%">
            <div class="company">{{ $settings['company_name'] }}</div>
            @if ($settings['company_address'])<div class="muted">{{ $settings['company_address'] }}</div>@endif
            <div class="muted">{{ $settings['company_phone'] }} @if ($settings['company_email']) · {{ $settings['company_email'] }} @endif</div>
            @if ($settings['company_tax_code'] ?? null)<div class="muted">{{ __('MST') }}: {{ $settings['company_tax_code'] }}</div>@endif
        </td>
        <td>
            <div class="title">{{ $cfg['doc'] }}</div>
            <div class="right">{{ __('Số:') }} <strong>{{ $doc->number }}</strong></div>
            <div class="right">{{ __('Ngày: :date', ['date' => vn_date($doc->{$cfg['dateField']})]) }}</div>
            @if ($cfg['route'] === 'quotations' && $doc->valid_until)<div class="right">{{ __('Hiệu lực đến: :date', ['date' => vn_date($doc->valid_until)]) }}</div>@endif
            @if ($cfg['route'] === 'invoices' && $doc->due_date)<div class="right">{{ __('Hạn thanh toán: :date', ['date' => vn_date($doc->due_date)]) }}</div>@endif
        </td>
    </tr>
</table>

<div class="box" style="margin-bottom:16px">
    <strong>{{ __('Khách hàng:') }}</strong> {{ $doc->customer?->name }} ({{ $doc->customer?->code }})<br>
    @if ($doc->customer?->address)<span class="muted">{{ __('Địa chỉ:') }}</span> {{ $doc->customer->address }}<br>@endif
    @if ($doc->customer?->phone)<span class="muted">{{ __('Điện thoại:') }}</span> {{ $doc->customer->phone }} @endif
    @if ($doc->customer?->email)<span class="muted"> · {{ __('Email:') }}</span> {{ $doc->customer->email }}@endif
</div>

<table class="items">
    <thead><tr><th style="width:5%">#</th><th>{{ __('Sản phẩm / dịch vụ') }}</th><th class="right" style="width:9%">{{ __('SL') }}</th><th class="right" style="width:17%">{{ __('Đơn giá') }}</th><th class="right" style="width:9%">{{ __('Giảm') }}</th><th class="right" style="width:18%">{{ __('Thành tiền') }}</th></tr></thead>
    <tbody>
    @foreach ($doc->items as $i => $item)
        <tr><td>{{ $i + 1 }}</td><td>{{ $item->description }}</td>
            <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
            <td class="right">{{ money($item->unit_price) }}</td><td class="right">{{ (float) $item->discount_percent }}%</td>
            <td class="right">{{ money($item->line_total) }}</td></tr>
    @endforeach
    </tbody>
</table>

<table style="margin-top:12px"><tr><td style="width:50%"></td><td>
    <table class="totals">
        <tr><td class="muted">{{ __('Tạm tính') }}</td><td class="right">{{ money($doc->subtotal) }}</td></tr>
        <tr><td class="muted">{{ __('Chiết khấu') }}</td><td class="right">- {{ money($doc->discount_amount) }}</td></tr>
        <tr><td class="muted">{{ __('Thuế VAT (:rate%)', ['rate' => (float) $doc->tax_rate]) }}</td><td class="right">{{ money($doc->tax_amount) }}</td></tr>
        <tr class="grand"><td>{{ __('TỔNG CỘNG') }}</td><td class="right">{{ money($doc->total) }}</td></tr>
        @if ($cfg['route'] === 'invoices')
            @if ((float) $doc->credited_amount > 0)<tr><td class="muted">{{ __('Giảm trừ') }}</td><td class="right">− {{ money($doc->credited_amount) }}</td></tr>@endif
            <tr><td class="muted">{{ __('Đã thanh toán') }}</td><td class="right">{{ money($doc->amount_paid) }}</td></tr>
            <tr><td><strong>{{ __('Còn phải thu') }}</strong></td><td class="right"><strong>{{ money($doc->balance) }}</strong></td></tr>
        @endif
    </table>
</td></tr></table>

@if ($cfg['route'] === 'invoices' && ($settings['bank_account_number'] ?? null) && (float) $doc->balance > 0)
    <div class="box" style="margin-top:16px"><strong>{{ __('Thông tin chuyển khoản') }}</strong><br>
        {{ __('Ngân hàng') }}: {{ $settings['bank_name'] }} · {{ __('Số tài khoản') }}: <strong>{{ $settings['bank_account_number'] }}</strong> · {{ __('Chủ tài khoản') }}: {{ $settings['bank_account_name'] }}<br>
        {{ __('Nội dung chuyển khoản') }}: <strong>{{ $doc->number }}</strong></div>
@endif
@if ($doc->notes)<div style="margin-top:16px"><strong>{{ __('Ghi chú / điều khoản:') }}</strong><br>{!! nl2br(e($doc->notes)) !!}</div>@endif
@if ($cfg['route'] === 'invoices' && ($settings['invoice_terms'] ?? null))<div style="margin-top:10px" class="muted">{!! nl2br(e($settings['invoice_terms'])) !!}</div>@endif
@if ($cfg['route'] === 'invoices')<div style="margin-top:10px; font-size:9px" class="muted">{{ __('Hóa đơn trong CRM là chứng từ nội bộ để theo dõi công nợ — không phải hóa đơn điện tử hợp lệ theo quy định thuế Việt Nam.') }}</div>@endif

<table class="sign"><tr>
    <td><strong>{{ __('labels.customer') }}</strong><br><span class="muted">{{ __('(Ký, ghi rõ họ tên)') }}</span></td>
    <td><strong>{{ __('Người lập') }}</strong><br><span class="muted">{{ __('(Ký, ghi rõ họ tên)') }}</span><br><br><br>{{ $doc->creator?->name }}</td>
</tr></table>
</body>
</html>
