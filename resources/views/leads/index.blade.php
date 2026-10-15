@extends('layouts.app')
@section('title', 'Leads')
@section('content')
    <x-page-header title="Leads" :breadcrumbs="['Leads' => null]" subtitle="Khách hàng tiềm năng — chấm điểm, phân công và chuyển đổi">
        @can('export', App\Models\Lead::class)
            <a href="{{ route('leads.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>Xuất Excel</a>
        @endcan
        @can('create', App\Models\Lead::class)
            <a href="{{ route('leads.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tạo lead</a>
        @endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        @foreach (App\Models\Lead::STATUSES as $key => [$label, $color])
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('leads.index', ['status' => $key]) }}" class="card card-hover p-3 d-block text-reset {{ request('status') === $key ? 'border-warning' : '' }}">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fs-4 fw-bold">{{ $summary[$key] ?? 0 }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên, mã, email, công ty...">
            </div>
            <select name="status" class="form-select"><option value="">Mọi trạng thái</option>
                @foreach (App\Models\Lead::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $s[0] }}</option>@endforeach
            </select>
            <select name="source" class="form-select"><option value="">Mọi nguồn</option>
                @foreach ($sources as $id => $name)<option value="{{ $id }}" @selected(request('source') == $id)>{{ $name }}</option>@endforeach
            </select>
            <select name="score" class="form-select"><option value="">Mọi mức điểm</option>
                <option value="hot" @selected(request('score') === 'hot')>Nóng (≥70)</option>
                <option value="warm" @selected(request('score') === 'warm')>Ấm (40–69)</option>
                <option value="cold" @selected(request('score') === 'cold')>Lạnh (&lt;40)</option>
            </select>
            <select name="campaign" class="form-select"><option value="">Mọi chiến dịch</option>
                @foreach ($campaigns as $id => $name)<option value="{{ $id }}" @selected(request('campaign') == $id)>{{ $name }}</option>@endforeach
            </select>
            @can('leads.view_all')
                <select name="assigned_to" class="form-select"><option value="">Mọi nhân viên</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach
                </select>
            @endcan
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>Lọc</button>
            @if (request()->hasAny(['q', 'status', 'source', 'score', 'campaign', 'assigned_to']))<a href="{{ route('leads.index') }}" class="btn btn-link text-muted">Xóa lọc</a>@endif
        </form>

        @if ($leads->isEmpty())
            <x-empty-state icon="bi-funnel" title="Không có lead nào" message="Thay đổi bộ lọc hoặc tạo lead mới." />
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr>
                        <th><x-sort-link column="code" label="Mã" /></th>
                        <th><x-sort-link column="name" label="Lead" /></th>
                        <th>Nguồn</th>
                        <th><x-sort-link column="score" label="Điểm" /></th>
                        <th class="text-end"><x-sort-link column="estimated_value" label="Giá trị ước tính" /></th>
                        <th><x-sort-link column="status" label="Trạng thái" /></th>
                        <th>Phụ trách</th>
                        <th class="text-end">Thao tác</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($leads as $l)
                        <tr>
                            <td class="small text-muted">{{ $l->code }}</td>
                            <td><a href="{{ route('leads.show', $l) }}" class="row-link">{{ $l->name }}</a><div class="small text-muted">{{ $l->company }}</div></td>
                            <td class="small">{{ $l->source?->name ?? '—' }}</td>
                            <td style="min-width:110px">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar" style="width: {{ $l->score }}%"></div></div>
                                    <span class="small fw-semibold">{{ $l->score }}</span>
                                </div>
                                <span class="badge badge-soft-{{ $l->score_level[1] }} mt-1">{{ $l->score_level[0] }}</span>
                            </td>
                            <td class="text-end small fw-semibold">{{ money($l->estimated_value) }}</td>
                            <td>{!! badge(App\Models\Lead::STATUSES, $l->status) !!}</td>
                            <td class="small">{{ $l->assignee?->name ?? '—' }}</td>
                            <td class="table-actions">
                                @if (! $l->isConverted())
                                    @can('update', $l)<a href="{{ route('leads.convert', $l) }}" class="btn btn-soft-primary btn-sm" title="Chuyển đổi"><i class="bi bi-arrow-repeat"></i></a>@endcan
                                @endif
                                @can('update', $l)<a href="{{ route('leads.edit', $l) }}" class="btn btn-light btn-icon btn-sm" title="Sửa"><i class="bi bi-pencil"></i></a>@endcan
                                @can('delete', $l)<x-delete-button :action="route('leads.destroy', $l)" />@endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination-bar :paginator="$leads" />
        @endif
    </div>
@endsection
