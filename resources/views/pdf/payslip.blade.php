<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>{{ __('Phiếu lương :p', ['p' => $slip->period->label]) }}</title>
<style>
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { font-size: 11px; color: #1F2937; margin: 0; }
    .header { border-bottom: 3px solid #FF6B00; padding-bottom: 12px; margin-bottom: 18px; }
    .company { font-size: 16px; font-weight: bold; color: #FF6B00; }
    .muted { color: #6B7280; }
    .title { font-size: 20px; font-weight: bold; text-transform: uppercase; text-align: right; }
    table { width: 100%; border-collapse: collapse; }
    .right { text-align: right; }
    .info td { padding: 3px 6px; }
    .lines td { padding: 7px 6px; border-bottom: 1px solid #E5E7EB; }
    .subtotal td { font-weight: bold; background: #F8FAFC; }
    .total td { font-size: 14px; font-weight: bold; color: #E65C00; border-top: 2px solid #FF6B00; }
    .deduction { color: #B91C1C; }
    .sign td { width: 50%; text-align: center; vertical-align: top; padding-top: 40px; }
    .draft { color: #B45309; font-weight: bold; text-align: center; border: 1px dashed #F59E0B; padding: 4px; margin-bottom: 10px; }
</style>
</head>
<body>
@if (! $slip->period->isPublished())<div class="draft">{{ __('BẢN NHÁP — KỲ LƯƠNG CHƯA ĐƯỢC DUYỆT') }}</div>@endif
<table class="header">
    <tr>
        <td style="width:60%">
            <div class="company">{{ $settings['company_name'] }}</div>
            @if ($settings['company_address'] ?? null)<div class="muted">{{ $settings['company_address'] }}</div>@endif
        </td>
        <td>
            <div class="title">{{ __('Phiếu lương') }}</div>
            <div class="right">{{ $slip->period->label }}</div>
        </td>
    </tr>
</table>

<table class="info" style="margin-bottom: 14px">
    <tr><td style="width:25%" class="muted">{{ __('Nhân viên') }}</td><td style="width:25%"><strong>{{ $slip->user->name }}</strong></td>
        <td style="width:25%" class="muted">{{ __('Mã nhân viên') }}</td><td>{{ $profile?->employee_code ?? '—' }}</td></tr>
    <tr><td class="muted">{{ __('Chức danh') }}</td><td>{{ $profile?->job_title ?? '—' }}</td>
        <td class="muted">{{ __('Đơn giá giờ') }}</td><td>{{ money($slip->hourly_rate) }}</td></tr>
    <tr><td class="muted">{{ __('Ngày công') }}</td><td>{{ $slip->work_days }} ({{ $slip->worked_hours }} {{ __('giờ') }})</td>
        <td class="muted">{{ __('Giờ tăng ca') }}</td><td>{{ (float) $slip->ot_hours }}</td></tr>
</table>

<table class="lines">
    @foreach (App\Services\PayrollService::lines($slip) as [$label, $amount, $detail, $kind])
        <tr class="{{ $kind }}">
            <td>{{ $label }}@if ($detail)<div class="muted" style="font-size: 9px">{{ $detail }}</div>@endif</td>
            <td class="right {{ $kind === 'deduction' && (float) $amount ? 'deduction' : '' }}">{{ $kind === 'deduction' && (float) $amount ? '- ' : '' }}{{ money($amount) }}</td>
        </tr>
    @endforeach
</table>

<table class="sign">
    <tr><td style="width:50%"><strong>{{ __('Người lập') }}</strong></td><td><strong>{{ __('Người nhận') }}</strong></td></tr>
</table>
<p class="muted" style="margin-top: 50px; font-size: 9px">{{ __('Phiếu lương được tạo từ TOP CRM lúc :t. Thông tin lương là bảo mật.', ['t' => vn_date(now(), true)]) }}</p>
</body>
</html>
