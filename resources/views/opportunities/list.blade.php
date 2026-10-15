@extends('layouts.app')
@section('title', 'Danh sách cơ hội')
@section('content')
    <x-page-header title="Danh sách cơ hội" :breadcrumbs="['Pipeline' => route('opportunities.index'), 'Danh sách' => null]">
        <a href="{{ route('opportunities.index') }}" class="btn btn-light"><i class="bi bi-kanban me-1"></i>Dạng Kanban</a>
        @can('create', App\Models\Opportunity::class)<a href="{{ route('opportunities.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tạo cơ hội</a>@endcan
    </x-page-header>
    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm cơ hội, khách hàng..."></div>
            <select name="stage" class="form-select"><option value="">Mọi giai đoạn</option>
                @foreach ($stages as $id => $name)<option value="{{ $id }}" @selected(request('stage') == $id)>{{ $name }}</option>@endforeach
            </select>
            @can('opportunities.view_all')
                <select name="assigned_to" class="form-select"><option value="">Mọi nhân viên</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach
                </select>
            @endcan
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>Lọc</button>
        </form>
        @if ($opportunities->isEmpty())
            <x-empty-state icon="bi-kanban" title="Không có cơ hội nào" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th><x-sort-link column="code" label="Mã" /></th><th><x-sort-link column="title" label="Cơ hội" /></th><th>Giai đoạn</th>
                    <th class="text-end"><x-sort-link column="value" label="Giá trị" /></th><th>Xác suất</th><th><x-sort-link column="expected_close_date" label="Dự kiến chốt" /></th><th>Phụ trách</th><th class="text-end">Thao tác</th></tr></thead>
                <tbody>
                @foreach ($opportunities as $o)
                    <tr>
                        <td class="small text-muted">{{ $o->code }}</td>
                        <td><a href="{{ route('opportunities.show', $o) }}" class="row-link">{{ $o->title }}</a><div class="small text-muted">{{ $o->customer?->name }}</div></td>
                        <td><span class="badge" style="background: {{ $o->stage->color }}">{{ $o->stage->name }}</span></td>
                        <td class="text-end fw-semibold">{{ money($o->value) }}</td>
                        <td>{{ $o->probability }}%</td>
                        <td class="small">{{ vn_date($o->expected_close_date) }}</td>
                        <td class="small">{{ $o->assignee?->name ?? '—' }}</td>
                        <td class="table-actions">
                            @can('update', $o)<a href="{{ route('opportunities.edit', $o) }}" class="btn btn-light btn-icon btn-sm"><i class="bi bi-pencil"></i></a>@endcan
                            @can('delete', $o)<x-delete-button :action="route('opportunities.destroy', $o)" />@endcan
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$opportunities" />
        @endif
    </div>
@endsection
