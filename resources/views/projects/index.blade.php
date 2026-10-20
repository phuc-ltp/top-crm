@extends('layouts.app')
@section('title', __('Dự án'))
@section('content')
    <x-page-header :title="__('Dự án')" :breadcrumbs="['Dự án' => null]" :subtitle="__('Gom công việc theo dự án, theo dõi tiến độ và thành viên')">
        @can('export', App\Models\Project::class)
            <a href="{{ route('projects.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>{{ __('Xuất Excel') }}</a>
        @endcan
        @can('create', App\Models\Project::class)
            <a href="{{ route('projects.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo dự án') }}</a>
        @endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><x-stat-card icon="bi-kanban" :label="__('Đang thực hiện')" :value="$counts['open']" :href="route('projects.index', ['status' => 'open'])" /></div>
        <div class="col-md-4"><x-stat-card icon="bi-calendar-x" tone="red" :label="__('Quá ngày kết thúc')" :value="$counts['late']" /></div>
        <div class="col-md-4"><x-stat-card icon="bi-check2-circle" tone="green" :label="__('Đã hoàn thành')" :value="$counts['completed']" :href="route('projects.index', ['status' => 'completed'])" /></div>
    </div>

    <div class="card mb-3">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên hoặc mã dự án...') }}">
            </div>
            <select name="status" class="form-select" aria-label="{{ __('Trạng thái') }}">
                <option value="">{{ __('Mọi trạng thái') }}</option>
                <option value="open" @selected(request('status') === 'open')>{{ __('Chưa kết thúc') }}</option>
                @foreach (App\Models\Project::STATUSES as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($l) }}</option>@endforeach
            </select>
            <select name="manager_id" class="form-select" aria-label="{{ __('Người quản lý') }}">
                <option value="">{{ __('Mọi người quản lý') }}</option>
                @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('manager_id') == $id)>{{ $name }}</option>@endforeach
            </select>
            @if (request()->hasAny(['q', 'status', 'manager_id']))<a href="{{ route('projects.index') }}" class="btn btn-link text-muted">{{ __('Xóa lọc') }}</a>@endif
        </form>
    </div>

    @if ($projects->isEmpty())
        <div class="card"><x-empty-state icon="bi-kanban" :title="__('Chưa có dự án')" /></div>
    @else
        <div class="row g-3">
            @foreach ($projects as $p)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                <span class="small text-muted">{{ $p->code }}</span>
                                {!! badge(App\Models\Project::STATUSES, $p->status) !!}
                            </div>
                            <a href="{{ route('projects.show', $p) }}" class="fw-semibold fs-6 row-link mb-1">{{ $p->name }}</a>
                            <div class="small text-muted mb-3 text-truncate"><i class="bi bi-building me-1"></i>{{ $p->customer?->name ?? __('Dự án nội bộ') }}</div>
                            <div class="d-flex justify-content-between small mb-1">
                                <span>{{ __('Tiến độ') }}</span><span class="fw-semibold">{{ $p->progress }}%</span>
                            </div>
                            <div class="progress mb-2" style="height:6px" role="progressbar" aria-label="{{ __('Tiến độ') }}" aria-valuenow="{{ $p->progress }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar {{ $p->progress === 100 ? 'bg-success' : '' }}" style="width: {{ $p->progress }}%"></div>
                            </div>
                            <div class="small text-muted mb-3">
                                {{ __(':done/:total việc hoàn thành', ['done' => $p->tasks_done_count, 'total' => $p->tasks_active_count]) }}
                                @if ($p->tasks_late_count)<span class="text-danger ms-1">· {{ __(':count việc trễ', ['count' => $p->tasks_late_count]) }}</span>@endif
                            </div>
                            <div class="mt-auto d-flex justify-content-between align-items-center">
                                <div class="avatar-stack">
                                    @foreach ($p->members->take(4) as $m)<x-avatar :user="$m" size="sm" />@endforeach
                                    @if ($p->members->count() > 4)<span class="avatar avatar-sm tone-gray">+{{ $p->members->count() - 4 }}</span>@endif
                                </div>
                                <span class="small {{ $p->is_late ? 'text-danger fw-semibold' : 'text-muted' }}"><i class="bi bi-calendar-event me-1"></i>{{ vn_date($p->end_date) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="card mt-3"><x-pagination-bar :paginator="$projects" /></div>
    @endif
@endsection
