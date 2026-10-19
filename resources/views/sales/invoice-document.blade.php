{{-- Hóa đơn trình bày như chứng từ thật: người bán / người mua / dòng hàng / tổng / thanh toán / chuyển khoản --}}
@php
    $s = App\Models\Setting::allCached();
    $logo = $s['company_logo'] ? Storage::disk('public')->url($s['company_logo']) : null;
    $payable = max(0, (float) $doc->total - (float) $doc->credited_amount);
    $paidPct = $payable > 0 ? min(100, round((float) $doc->amount_paid / $payable * 100)) : 100;
    $overdueDays = $doc->is_overdue ? (int) $doc->due_date->diffInDays(today()) : 0;
    $steps = [
        ['draft', __('Nháp'), 'bi-pencil'],
        ['issued', __('Đã phát hành'), 'bi-send-check'],
        ['partial', __('Thanh toán một phần'), 'bi-hourglass-split'],
        ['paid', __('Đã thanh toán'), 'bi-check2-circle'],
    ];
    $current = $doc->status === 'cancelled' ? 'cancelled' : ($doc->status === 'draft' ? 'draft' : ($doc->payment_status === 'paid' ? 'paid' : ($doc->payment_status === 'partial' ? 'partial' : 'issued')));
    $reached = array_search($current, array_column($steps, 0), true);
@endphp

{{-- Các bước trạng thái --}}
@if ($current === 'cancelled')
    <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bi bi-x-octagon"></i>{{ __('Hóa đơn đã bị hủy — không còn giá trị theo dõi công nợ.') }}</div>
@else
    <div class="invoice-steps mb-3" role="list" aria-label="{{ __('Tiến trình hóa đơn') }}">
        @foreach ($steps as $i => [$key, $label, $icon])
            <div class="invoice-step {{ $i < $reached ? 'done' : ($i === $reached ? 'current' : '') }}" role="listitem">
                <span class="dot"><i class="bi {{ $i < $reached ? 'bi-check-lg' : $icon }}"></i></span><span class="lbl">{{ $label }}</span>
            </div>
        @endforeach
    </div>
@endif

<div class="card mb-4 invoice-paper">
    <div class="card-body p-4 p-lg-5">
        @if ($doc->is_demo)<div class="alert alert-warning py-1 small text-center mb-3">{{ __('DỮ LIỆU DEMO — KHÔNG CÓ GIÁ TRỊ THỰC TẾ') }}</div>@endif
        <div class="d-flex flex-wrap justify-content-between gap-4 pb-4 mb-4 invoice-head">
            <div class="d-flex gap-3 align-items-start min-w-0">
                @if ($logo)<img src="{{ $logo }}" alt="" class="invoice-logo">@endif
                <div class="min-w-0">
                    <div class="fw-bold fs-5 text-orange">{{ $s['company_name'] }}</div>
                    @if ($s['company_address'] ?? null)<div class="small text-muted">{{ $s['company_address'] }}</div>@endif
                    <div class="small text-muted">{{ collect([$s['company_phone'] ?? null, $s['company_email'] ?? null])->filter()->implode(' · ') }}</div>
                    @if ($s['company_tax_code'] ?? null)<div class="small text-muted">{{ __('MST') }}: {{ $s['company_tax_code'] }}</div>@endif
                </div>
            </div>
            <div class="text-md-end">
                <div class="invoice-title">{{ __('HÓA ĐƠN') }}</div>
                <div class="small text-muted">{{ __('Số') }} <strong class="text-body">{{ $doc->number }}</strong></div>
                <div class="small text-muted">{{ __('Ngày lập') }} <strong class="text-body">{{ vn_date($doc->issue_date) }}</strong></div>
                <div class="small text-muted">{{ __('Hạn thanh toán') }} <strong class="{{ $doc->is_overdue ? 'text-danger' : 'text-body' }}">{{ $doc->due_date ? vn_date($doc->due_date) : '—' }}</strong></div>
                <div class="mt-2">{!! badge($cfg['statuses'], $doc->status) !!} {!! badge(App\Models\Invoice::PAYMENT_STATUSES, $doc->payment_status) !!}
                    @if ($doc->is_overdue)<span class="badge badge-soft-danger">{{ __('Quá hạn :days ngày', ['days' => $overdueDays]) }}</span>@endif</div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="small text-uppercase text-muted fw-semibold mb-1">{{ __('Bên mua') }}</div>
                <a href="{{ route('customers.show', $doc->customer_id) }}" class="fw-bold">{{ $doc->customer?->name }}</a> <span class="small text-muted">{{ $doc->customer?->code }}</span>
                @if ($doc->customer?->address)<div class="small text-muted">{{ $doc->customer->address }}</div>@endif
                <div class="small text-muted">{{ collect([$doc->customer?->phone, $doc->customer?->email])->filter()->implode(' · ') }}</div>
            </div>
            <div class="col-md-6">
                <div class="small text-uppercase text-muted fw-semibold mb-1">{{ __('Thông tin khác') }}</div>
                <div class="small">{{ __('Đơn hàng') }}: @if ($doc->salesOrder)<a href="{{ route('orders.show', $doc->salesOrder) }}">{{ $doc->salesOrder->number }}</a>@else — @endif</div>
                <div class="small">{{ __('Phụ trách') }}: {{ $doc->assignee?->name ?? '—' }}</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table invoice-items">
                <thead><tr><th>#</th><th>{{ __('Sản phẩm / dịch vụ') }}</th><th class="text-end">{{ __('SL') }}</th><th class="text-end">{{ __('Đơn giá') }}</th><th class="text-end">{{ __('Giảm') }}</th><th class="text-end">{{ __('Thành tiền') }}</th></tr></thead>
                <tbody>
                @foreach ($doc->items as $i => $item)
                    <tr><td class="text-muted">{{ $i + 1 }}</td><td>{{ $item->description }}@if ($item->product)<div class="small text-muted">{{ $item->product->sku }}</div>@endif</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="text-end">{{ money($item->unit_price) }}</td><td class="text-end">{{ (float) $item->discount_percent }}%</td>
                        <td class="text-end fw-semibold">{{ money($item->line_total) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-md-6 order-2 order-md-1">
                @if (($s['bank_account_number'] ?? null) && $doc->status === 'issued' && (float) $doc->balance > 0)
                    <div class="invoice-bank">
                        <div class="small text-uppercase text-muted fw-semibold mb-1"><i class="bi bi-bank me-1"></i>{{ __('Thông tin chuyển khoản') }}</div>
                        <div class="small">{{ __('Ngân hàng') }}: <strong>{{ $s['bank_name'] }}</strong></div>
                        <div class="small">{{ __('Số tài khoản') }}: <strong>{{ $s['bank_account_number'] }}</strong></div>
                        <div class="small">{{ __('Chủ tài khoản') }}: <strong>{{ $s['bank_account_name'] }}</strong></div>
                        <div class="small">{{ __('Nội dung') }}: <strong>{{ $doc->number }}</strong></div>
                    </div>
                @endif
                @if ($doc->notes)<div class="small mt-3" style="white-space: pre-line"><span class="text-muted">{{ __('Ghi chú:') }}</span> {{ $doc->notes }}</div>@endif
                @if ($s['invoice_terms'] ?? null)<div class="small mt-2 text-muted" style="white-space: pre-line">{{ $s['invoice_terms'] }}</div>@endif
            </div>
            <div class="col-md-6 order-1 order-md-2">
                <div class="d-flex justify-content-between py-1"><span class="text-muted">{{ __('Tạm tính') }}</span><span>{{ money($doc->subtotal) }}</span></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">{{ __('Chiết khấu') }}</span><span>− {{ money($doc->discount_amount) }}</span></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">{{ __('Thuế VAT (:rate%)', ['rate' => (float) $doc->tax_rate]) }}</span><span>{{ money($doc->tax_amount) }}</span></div>
                <div class="d-flex justify-content-between py-2 border-top fs-5 fw-bold"><span>{{ __('Tổng cộng') }}</span><span class="text-orange">{{ money($doc->total) }}</span></div>
                @if ((float) $doc->credited_amount > 0)<div class="d-flex justify-content-between py-1"><span class="text-muted">{{ __('Giảm trừ') }}</span><span class="text-danger">− {{ money($doc->credited_amount) }}</span></div>@endif
                <div class="d-flex justify-content-between py-1"><span class="text-muted">{{ __('Đã thanh toán') }}</span><span class="text-success">{{ money($doc->amount_paid) }}</span></div>
                <div class="d-flex justify-content-between py-1 fw-semibold"><span>{{ __('Còn phải thu') }}</span><span class="{{ (float) $doc->balance > 0 ? 'text-danger' : 'text-success' }}">{{ money($doc->balance) }}</span></div>
                @if ($doc->status === 'issued')
                    <div class="mt-2" title="{{ __('Đã thu / (tổng − giảm trừ)') }}">
                        <div class="d-flex justify-content-between small text-muted mb-1"><span>{{ __('Tiến độ thanh toán') }}</span><span>{{ $paidPct }}%</span></div>
                        <div class="progress" style="height: 8px" role="progressbar" aria-valuenow="{{ $paidPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Tiến độ thanh toán') }}">
                            <div class="progress-bar {{ $paidPct >= 100 ? 'bg-success' : '' }}" style="width: {{ $paidPct }}%"></div></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
