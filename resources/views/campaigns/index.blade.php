@extends('layouts.app')
@section('title', 'Marketing')
@section('content')
    <x-page-header title="Chiến dịch Marketing" :breadcrumbs="['Marketing' => null]" subtitle="Ngân sách, lead thu được và hiệu quả chuyển đổi">
        @can('export', App\Models\Campaign::class)<a href="{{ route('campaigns.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>Xuất Excel</a>@endcan
        @can('create', App\Models\Campaign::class)<a href="{{ route('campaigns.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tạo chiến dịch</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-megaphone" label="Đang chạy" :value="$summary['active']" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-wallet2" tone="blue" label="Tổng ngân sách" :value="money($summary['budget'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-funnel" tone="purple" label="Lead thu được" :value="$summary['leads']" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-arrow-repeat" tone="green" label="Lead chuyển đổi" :value="$summary['converted']" :sub="$summary['leads'] ? round($summary['converted'] / $summary['leads'] * 100, 1).'% tỷ lệ chuyển đổi' : null" /></div>
    </div>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên chiến dịch..."></div>
            <select name="type" class="form-select"><option value="">Mọi loại</option>@foreach (App\Models\Campaign::TYPES as $k => $t)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $t }}</option>@endforeach</select>
            <select name="status" class="form-select"><option value="">Mọi trạng thái</option>@foreach (App\Models\Campaign::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $s[0] }}</option>@endforeach</select>
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>Lọc</button>
        </form>
        @if ($campaigns->isEmpty())
            <x-empty-state icon="bi-megaphone" title="Chưa có chiến dịch" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th><x-sort-link column="name" label="Chiến dịch" /></th><th><x-sort-link column="start_date" label="Thời gian" /></th><th class="text-end"><x-sort-link column="budget" label="Ngân sách" /></th>
                    <th style="min-width:160px">Lead (thực tế / mục tiêu)</th><th>Chuyển đổi</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                <tbody>
                @foreach ($campaigns as $c)
                    @php($pct = $c->target_leads ? min(100, round($c->actual_leads / $c->target_leads * 100)) : 0)
                    <tr>
                        <td><a href="{{ route('campaigns.show', $c) }}" class="row-link">{{ $c->name }}</a><div class="small text-muted">{{ App\Models\Campaign::TYPES[$c->type] }} · {{ $c->owner?->name }}</div></td>
                        <td class="small">{{ vn_date($c->start_date) }} → {{ vn_date($c->end_date) }}</td>
                        <td class="text-end">{{ money($c->budget) }}</td>
                        <td><div class="small mb-1">{{ $c->actual_leads }} / {{ $c->target_leads }}</div><div class="progress" style="height:6px"><div class="progress-bar" style="width: {{ $pct }}%"></div></div></td>
                        <td class="small">{{ $c->converted_leads }} ({{ $c->conversion_rate }}%)</td>
                        <td>{!! badge(App\Models\Campaign::STATUSES, $c->status) !!}</td>
                        <td class="table-actions">
                            @can('update', $c)<a href="{{ route('campaigns.edit', $c) }}" class="btn btn-light btn-icon btn-sm"><i class="bi bi-pencil"></i></a>@endcan
                            @can('delete', $c)<x-delete-button :action="route('campaigns.destroy', $c)" />@endcan
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$campaigns" />
        @endif
    </div>
@endsection
