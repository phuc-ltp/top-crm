@extends('layouts.app')
@section('title', __('Đối soát thanh toán'))
@section('content')
    <x-page-header :title="__('Đối soát thanh toán')" :breadcrumbs="['Hóa đơn' => route('invoices.index'), 'Đối soát thanh toán' => null]"
                   :subtitle="__('Đánh dấu các khoản đã đối chiếu với sao kê ngân hàng / sổ quỹ. Khoản đã đối soát không xóa được.')" />
    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Số hóa đơn, mã giao dịch...') }}"></div>
            <select name="state" class="form-select" aria-label="{{ __('Trạng thái đối soát') }}">
                <option value="open" @selected(request('state', 'open') === 'open')>{{ __('Chưa đối soát') }}</option>
                <option value="done" @selected(request('state') === 'done')>{{ __('Đã đối soát') }}</option>
            </select>
            <select name="method" class="form-select" aria-label="{{ __('Phương thức') }}"><option value="">{{ __('Mọi phương thức') }}</option>
                @foreach (App\Models\Payment::METHODS as $k => $m)<option value="{{ $k }}" @selected(request('method') === $k)>{{ __($m) }}</option>@endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="{{ __('Từ ngày') }}" style="max-width:160px">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="{{ __('Đến ngày') }}" style="max-width:160px">
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
        </form>
        @if ($payments->isEmpty())
            <x-empty-state icon="bi-check2-all" :title="__('Không có khoản nào')" />
        @else
            <form method="POST" action="{{ route('payments.reconcile') }}" id="reconcileForm">@csrf
                @if (request('state') === 'done')<input type="hidden" name="undo" value="1">@endif
            </form>
            <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
                <thead><tr><th style="width:36px"><input type="checkbox" class="form-check-input" data-check-all aria-label="{{ __('Chọn tất cả') }}"></th>
                    <th>{{ __('Ngày') }}</th><th>{{ __('labels.invoice') }}</th><th>{{ __('labels.customer') }}</th><th>{{ __('Loại') }}</th><th class="text-end">{{ __('Số tiền') }}</th>
                    <th>{{ __('Phương thức') }}</th><th>{{ __('Mã GD') }}</th><th>{{ __('Người ghi nhận') }}</th>@if (request('state') === 'done')<th>{{ __('Đối soát') }}</th>@endif</tr></thead>
                <tbody>
                @foreach ($payments as $p)
                    <tr>
                        <td><input form="reconcileForm" type="checkbox" name="ids[]" value="{{ $p->id }}" class="form-check-input" data-check-item aria-label="{{ __('Chọn') }}"></td>
                        <td class="small text-nowrap">{{ vn_date($p->payment_date) }}</td>
                        <td>@if ($p->invoice)<a href="{{ route('invoices.show', $p->invoice) }}">{{ $p->invoice->number }}</a>@endif</td>
                        <td class="small">{{ $p->invoice?->customer?->name }}</td>
                        <td>@if ($p->type === 'refund')<span class="badge badge-soft-danger">{{ __('Hoàn tiền') }}</span>@else<span class="badge badge-soft-success">{{ __('Thanh toán') }}</span>@endif</td>
                        <td class="text-end fw-semibold {{ (float) $p->amount < 0 ? 'text-danger' : '' }}">{{ money($p->amount) }}</td>
                        <td class="small">{{ __(App\Models\Payment::METHODS[$p->method] ?? $p->method) }}</td>
                        <td class="small">{{ $p->reference ?? '—' }}</td>
                        <td class="small text-muted">{{ $p->recorder?->name }}</td>
                        @if (request('state') === 'done')<td class="small text-muted">{{ $p->reconciler?->name }} · {{ vn_date($p->reconciled_at, true) }}</td>@endif
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="5" class="text-end small text-muted">{{ __('Tổng theo bộ lọc') }}</td><td class="text-end fw-bold">{{ money($total) }}</td><td colspan="{{ request('state') === 'done' ? 4 : 3 }}"></td></tr></tfoot>
            </table></div>
            <div class="d-flex justify-content-between align-items-center p-3 gap-2 flex-wrap">
                <button form="reconcileForm" class="btn {{ request('state') === 'done' ? 'btn-light' : 'btn-primary' }}">
                    <i class="bi {{ request('state') === 'done' ? 'bi-arrow-counterclockwise' : 'bi-check2-all' }} me-1"></i>{{ request('state') === 'done' ? __('Bỏ đối soát các khoản đã chọn') : __('Đánh dấu đã đối soát') }}</button>
                <x-pagination-bar :paginator="$payments" />
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
document.querySelector('[data-check-all]')?.addEventListener('change', (e) => document.querySelectorAll('[data-check-item]').forEach((c) => { c.checked = e.target.checked; }));
</script>
@endpush
