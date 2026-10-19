@extends('layouts.app')
@section('title', $cfg['label'])
@section('content')
    @php($class = $cfg['class'])
    <x-page-header :title="$cfg['label']" :breadcrumbs="[$cfg['label'] => null]">
        @can('export', $class)
            <div class="dropdown">
                <button class="btn btn-light" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>{{ __('Xuất') }}</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route($cfg['route'].'.export', request()->query() + ['format' => 'xlsx']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel</a></li>
                    <li><a class="dropdown-item" href="{{ route($cfg['route'].'.export', request()->query() + ['format' => 'csv']) }}"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                </ul>
            </div>
        @endcan
        @can('create', $class)<a href="{{ route($cfg['route'].'.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo :doc', ['doc' => mb_strtolower($cfg['doc'])]) }}</a>@endcan
    </x-page-header>

    @if ($cfg['route'] === 'invoices' && isset($invoiceSummary))
        @php($sum = $invoiceSummary)
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3"><x-stat-card icon="bi-wallet2" :label="__('Còn phải thu (:count hóa đơn)', ['count' => $sum['open_count']])" :value="money_short($sum['receivable'])" :href="route('invoices.index', ['status' => 'issued'])" /></div>
            <div class="col-sm-6 col-xl-3"><x-stat-card icon="bi-exclamation-triangle" tone="red" :label="__('Quá hạn (:count hóa đơn)', ['count' => $sum['overdue_count']])" :value="money_short($sum['overdue'])" :href="route('invoices.index', ['due' => 'overdue'])" /></div>
            <div class="col-sm-6 col-xl-3"><x-stat-card icon="bi-calendar-event" tone="yellow" :label="__('Đến hạn trong 7 ngày (:count)', ['count' => $sum['soon_count']])" :value="money_short($sum['soon'])" :href="route('invoices.index', ['due' => 'soon'])" /></div>
            <div class="col-sm-6 col-xl-3"><x-stat-card icon="bi-cash-coin" tone="green" :label="__('Đã thu tháng này')" :value="money_short($sum['paid_month'])" /></div>
        </div>
    @else
    <div class="row g-3 mb-3">
        <div class="col-md-6"><x-stat-card icon="bi-files" :label="__('Số lượng (theo bộ lọc)')" :value="number_format($totals->cnt, 0, ',', '.')" /></div>
        <div class="col-md-6"><x-stat-card icon="bi-cash-stack" tone="green" :label="__('Tổng giá trị (theo bộ lọc)')" :value="money($totals->total)" /></div>
    </div>
    @endif

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Số chứng từ, tên khách hàng...') }}"></div>
            <select name="status" class="form-select"><option value="">{{ __('Mọi trạng thái') }}</option>
                @foreach ($cfg['statuses'] as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $s[0] }}</option>@endforeach
            </select>
            @if ($cfg['route'] === 'invoices')
                <select name="payment_status" class="form-select"><option value="">{{ __('Mọi tình trạng thanh toán') }}</option>
                    @foreach (App\Models\Invoice::PAYMENT_STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('payment_status') === $k)>{{ __($s[0]) }}</option>@endforeach
                </select>
                <select name="due" class="form-select" aria-label="{{ __('Hạn thanh toán') }}"><option value="">{{ __('Mọi hạn thanh toán') }}</option>
                    <option value="overdue" @selected(request('due') === 'overdue')>{{ __('Quá hạn') }}</option>
                    <option value="soon" @selected(request('due') === 'soon')>{{ __('Đến hạn trong 7 ngày') }}</option>
                </select>
            @endif
            @can($class::permissionModule().'.view_all')
                <select name="assigned_to" class="form-select"><option value="">{{ __('Mọi nhân viên') }}</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach
                </select>
            @endcan
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="{{ __('Từ ngày') }}" title="{{ __('Từ ngày') }}">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="{{ __('Đến ngày') }}" title="{{ __('Đến ngày') }}">
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
            @if (request()->hasAny(['q', 'status', 'payment_status', 'assigned_to', 'from', 'to', 'due']))<a href="{{ route($cfg['route'].'.index') }}" class="btn btn-link text-muted">{{ __('Xóa lọc') }}</a>@endif
        </form>

        @if ($docs->isEmpty())
            <x-empty-state icon="bi-file-earmark-text" :title="__('Chưa có :doc nào', ['doc' => mb_strtolower($cfg['doc'])])" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr>
                    <th><x-sort-link column="number" :label="__('Số')" /></th>
                    <th><x-sort-link :column="$cfg['dateField']" :label="__('Ngày')" /></th>
                    <th>{{ __('labels.customer') }}</th>
                    <th class="text-end"><x-sort-link column="total" :label="__('Tổng cộng')" /></th>
                    <th><x-sort-link column="status" :label="__('Trạng thái')" /></th>
                    @if ($cfg['route'] === 'invoices')<th style="min-width:170px">{{ __('Thanh toán') }}</th><th class="text-end">{{ __('Còn phải thu') }}</th>@endif
                    <th>{{ __('Phụ trách') }}</th>
                    <th class="text-end">{{ __('Thao tác') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($docs as $d)
                    <tr>
                        <td><a href="{{ route($cfg['route'].'.show', $d) }}" class="row-link">{{ $d->number }}</a></td>
                        <td class="small">{{ vn_date($d->{$cfg['dateField']}) }}</td>
                        <td class="small">{{ $d->customer?->name }}</td>
                        <td class="text-end fw-semibold">{{ money($d->total) }}</td>
                        <td>{!! badge($cfg['statuses'], $d->status) !!}</td>
                        @if ($cfg['route'] === 'invoices')
                            @php($payable = max(0, (float) $d->total - (float) $d->credited_amount))
                            @php($pct = $payable > 0 ? min(100, round((float) $d->amount_paid / $payable * 100)) : 100)
                            <td>{!! badge(App\Models\Invoice::PAYMENT_STATUSES, $d->payment_status) !!}
                                @if ($d->is_overdue)<span class="badge badge-soft-danger">{{ __('Quá hạn :days ngày', ['days' => (int) $d->due_date->diffInDays(today())]) }}</span>@endif
                                @if ($d->status === 'issued')<div class="progress mt-1" style="height:4px" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Tiến độ thanh toán') }}"><div class="progress-bar {{ $pct >= 100 ? 'bg-success' : '' }}" style="width: {{ $pct }}%"></div></div>@endif
                                @if ($d->due_date && $d->status === 'issued')<div class="small-xs text-muted">{{ __('Hạn') }} {{ vn_date($d->due_date) }}</div>@endif</td>
                            <td class="text-end small {{ (float) $d->balance > 0 && $d->status === 'issued' ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $d->status === 'issued' ? money($d->balance) : '—' }}</td>
                        @endif
                        <td class="small">{{ $d->assignee?->name ?? '—' }}</td>
                        <td class="table-actions">
                            @if (Route::has($cfg['route'].'.pdf'))<a href="{{ route($cfg['route'].'.pdf', $d) }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Tải PDF') }}"><i class="bi bi-file-earmark-pdf"></i></a>@endif
                            <a href="{{ route($cfg['route'].'.show', $d) }}" class="btn btn-light btn-icon btn-sm" title="Xem"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$docs" />
        @endif
    </div>
@endsection
