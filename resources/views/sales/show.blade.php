@extends('layouts.app')
@section('title', $cfg['label'].' '.$doc->number)
@section('content')
    @php($r = $cfg['route'])
    <x-page-header :title="$cfg['label'].' '.$doc->number" :breadcrumbs="[$cfg['label'] => route($r.'.index'), $doc->number => null]">
        @include('sales.actions.'.$r)
        <a href="{{ route($r.'.pdf', $doc) }}" class="btn btn-light"><i class="bi bi-file-earmark-pdf me-1"></i>Tải PDF</a>
        @if ($editable)
            @can('update', $doc)<a href="{{ route($r.'.edit', $doc) }}" class="btn btn-light"><i class="bi bi-pencil me-1"></i>Sửa</a>@endcan
            @can('delete', $doc)<x-delete-button :action="route($r.'.destroy', $doc)" class="btn btn-light text-danger" label="Xóa" />@endcan
        @endif
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                        <div>
                            <div class="text-muted small">Khách hàng</div>
                            <a href="{{ route('customers.show', $doc->customer_id) }}" class="fw-bold fs-5">{{ $doc->customer?->name }}</a>
                            <div class="small text-muted">{{ $doc->customer?->address }}</div>
                            <div class="small text-muted">{{ $doc->customer?->phone }} {{ $doc->customer?->email ? '· '.$doc->customer->email : '' }}</div>
                        </div>
                        <div class="text-md-end">
                            <div>{!! badge($cfg['statuses'], $doc->status) !!}
                                @if ($r === 'invoices') {!! badge(App\Models\Invoice::PAYMENT_STATUSES, $doc->payment_status) !!} @if ($doc->is_overdue)<span class="badge badge-soft-danger">Quá hạn</span>@endif @endif</div>
                            <div class="small text-muted mt-2">Ngày: <strong>{{ vn_date($doc->{$cfg['dateField']}) }}</strong></div>
                            @if ($r === 'quotations')<div class="small text-muted">Hiệu lực đến: <strong>{{ vn_date($doc->valid_until) }}</strong></div>@endif
                            @if ($r === 'invoices')<div class="small text-muted">Hạn thanh toán: <strong>{{ vn_date($doc->due_date) }}</strong></div>@endif
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>#</th><th>Sản phẩm / dịch vụ</th><th class="text-end">SL</th><th class="text-end">Đơn giá</th><th class="text-end">Giảm</th><th class="text-end">Thành tiền</th></tr></thead>
                            <tbody>
                            @foreach ($doc->items as $i => $item)
                                <tr><td>{{ $i + 1 }}</td><td>{{ $item->description }}@if ($item->product)<div class="small text-muted">{{ $item->product->sku }}</div>@endif</td>
                                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="text-end">{{ money($item->unit_price) }}</td><td class="text-end">{{ (float) $item->discount_percent }}%</td>
                                    <td class="text-end fw-semibold">{{ money($item->line_total) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="row justify-content-end"><div class="col-md-6">
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Tạm tính</span><span>{{ money($doc->subtotal) }}</span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Chiết khấu</span><span>- {{ money($doc->discount_amount) }}</span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Thuế VAT ({{ (float) $doc->tax_rate }}%)</span><span>{{ money($doc->tax_amount) }}</span></div>
                        <div class="d-flex justify-content-between py-2 border-top fs-5 fw-bold"><span>Tổng cộng</span><span class="text-orange">{{ money($doc->total) }}</span></div>
                        @if ($r === 'invoices')
                            <div class="d-flex justify-content-between py-1"><span class="text-muted">Đã thanh toán</span><span class="text-success">{{ money($doc->amount_paid) }}</span></div>
                            <div class="d-flex justify-content-between py-1 fw-semibold"><span>Còn phải thu</span><span class="text-danger">{{ money($doc->balance) }}</span></div>
                        @endif
                    </div></div>
                    @if ($doc->notes)<hr><div class="small" style="white-space: pre-line">{{ $doc->notes }}</div>@endif
                </div>
            </div>

            @if ($r === 'invoices')
                @include('sales.payments')
            @endif
        </div>
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <dl class="detail-list">
                    <dt>Số chứng từ</dt><dd>{{ $doc->number }}</dd>
                    @if ($r === 'quotations')
                        <dt>Cơ hội</dt><dd>@if ($doc->opportunity)<a href="{{ route('opportunities.show', $doc->opportunity) }}">{{ $doc->opportunity->title }}</a>@else — @endif</dd>
                        <dt>Đơn hàng</dt><dd>@forelse ($doc->salesOrders as $o)<a href="{{ route('orders.show', $o) }}" class="d-block">{{ $o->number }}</a>@empty — @endforelse</dd>
                    @elseif ($r === 'orders')
                        <dt>Từ báo giá</dt><dd>@if ($doc->quotation)<a href="{{ route('quotations.show', $doc->quotation) }}">{{ $doc->quotation->number }}</a>@else — @endif</dd>
                        <dt>Người duyệt</dt><dd>{{ $doc->approver?->name ?? '—' }} @if ($doc->approved_at)<div class="small text-muted">{{ vn_date($doc->approved_at, true) }}</div>@endif</dd>
                        <dt>Hóa đơn</dt><dd>@forelse ($doc->invoices as $inv)<a href="{{ route('invoices.show', $inv) }}" class="d-block">{{ $inv->number }}</a>@empty — @endforelse</dd>
                    @else
                        <dt>Đơn hàng</dt><dd>@if ($doc->salesOrder)<a href="{{ route('orders.show', $doc->salesOrder) }}">{{ $doc->salesOrder->number }}</a>@else — @endif</dd>
                    @endif
                    <dt>Phụ trách</dt><dd>{{ $doc->assignee?->name ?? '—' }}</dd>
                    <dt>Người tạo</dt><dd>{{ $doc->creator?->name ?? '—' }}</dd>
                    <dt>Ngày tạo</dt><dd>{{ vn_date($doc->created_at, true) }}</dd>
                    <dt>Cập nhật</dt><dd>{{ vn_date($doc->updated_at, true) }}</dd>
                </dl>
            </div></div>
        </div>
    </div>
@endsection
