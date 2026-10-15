@extends('layouts.app')
@section('title', $cfg['label'])
@section('content')
    @php($class = $cfg['class'])
    <x-page-header :title="$cfg['label']" :breadcrumbs="[$cfg['label'] => null]">
        @can('export', $class)
            <div class="dropdown">
                <button class="btn btn-light" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Xuất</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route($cfg['route'].'.export', request()->query() + ['format' => 'xlsx']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel</a></li>
                    <li><a class="dropdown-item" href="{{ route($cfg['route'].'.export', request()->query() + ['format' => 'csv']) }}"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                </ul>
            </div>
        @endcan
        @can('create', $class)<a href="{{ route($cfg['route'].'.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tạo {{ mb_strtolower($cfg['label']) }}</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-md-6"><x-stat-card icon="bi-files" :label="'Số '.mb_strtolower($cfg['label']).' (theo bộ lọc)'" :value="number_format($totals->cnt, 0, ',', '.')" /></div>
        <div class="col-md-6"><x-stat-card icon="bi-cash-stack" tone="green" label="Tổng giá trị (theo bộ lọc)" :value="money($totals->total)" /></div>
    </div>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Số chứng từ, tên khách hàng..."></div>
            <select name="status" class="form-select"><option value="">Mọi trạng thái</option>
                @foreach ($cfg['statuses'] as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $s[0] }}</option>@endforeach
            </select>
            @if ($cfg['route'] === 'invoices')
                <select name="payment_status" class="form-select"><option value="">Mọi tình trạng thanh toán</option>
                    @foreach (App\Models\Invoice::PAYMENT_STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('payment_status') === $k)>{{ $s[0] }}</option>@endforeach
                </select>
            @endif
            @can($class::permissionModule().'.view_all')
                <select name="assigned_to" class="form-select"><option value="">Mọi nhân viên</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach
                </select>
            @endcan
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="Từ ngày" title="Từ ngày">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="Đến ngày" title="Đến ngày">
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>Lọc</button>
            @if (request()->hasAny(['q', 'status', 'payment_status', 'assigned_to', 'from', 'to']))<a href="{{ route($cfg['route'].'.index') }}" class="btn btn-link text-muted">Xóa lọc</a>@endif
        </form>

        @if ($docs->isEmpty())
            <x-empty-state icon="bi-file-earmark-text" :title="'Chưa có '.mb_strtolower($cfg['label'])" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr>
                    <th><x-sort-link column="number" label="Số" /></th>
                    <th><x-sort-link :column="$cfg['dateField']" label="Ngày" /></th>
                    <th>Khách hàng</th>
                    <th class="text-end"><x-sort-link column="total" label="Tổng cộng" /></th>
                    <th><x-sort-link column="status" label="Trạng thái" /></th>
                    @if ($cfg['route'] === 'invoices')<th>Thanh toán</th>@endif
                    <th>Phụ trách</th>
                    <th class="text-end">Thao tác</th>
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
                            <td>{!! badge(App\Models\Invoice::PAYMENT_STATUSES, $d->payment_status) !!}
                                @if ($d->is_overdue)<span class="badge badge-soft-danger">Quá hạn</span>@endif</td>
                        @endif
                        <td class="small">{{ $d->assignee?->name ?? '—' }}</td>
                        <td class="table-actions">
                            @if (Route::has($cfg['route'].'.pdf'))<a href="{{ route($cfg['route'].'.pdf', $d) }}" class="btn btn-light btn-icon btn-sm" title="Tải PDF"><i class="bi bi-file-earmark-pdf"></i></a>@endif
                            <a href="{{ route($cfg['route'].'.show', $d) }}" class="btn btn-light btn-icon btn-sm" title="Xem"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$docs" />
        @endif
    </div>
@endsection
