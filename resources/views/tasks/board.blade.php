@extends('layouts.app')
@section('title', __('Bảng Kanban công việc'))
@section('content')
    <x-page-header :title="__('Bảng Kanban công việc')" :breadcrumbs="['Công việc' => route('tasks.index'), 'Kanban' => null]" :subtitle="__('Kéo thả thẻ giữa các cột để đổi trạng thái')">
        <a href="{{ route('tasks.index', request()->query()) }}" class="btn btn-light"><i class="bi bi-list-ul me-1"></i>{{ __('Dạng danh sách') }}</a>
        @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', array_filter(['project_id' => request('project_id')])) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo công việc') }}</a>@endcan
    </x-page-header>

    <div class="card mb-3">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tìm công việc...') }}"></div>
            <select name="project_id" class="form-select" aria-label="{{ __('Dự án') }}"><option value="">{{ __('Mọi dự án') }}</option><option value="none" @selected(request('project_id') === 'none')>{{ __('Không thuộc dự án') }}</option>
                @foreach ($projects as $id => $name)<option value="{{ $id }}" @selected(request('project_id') == $id)>{{ $name }}</option>@endforeach
            </select>
            @can('tasks.view_all')
                <select name="assigned_to" class="form-select" aria-label="{{ __('Nhân viên') }}"><option value="">{{ __('Mọi nhân viên') }}</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach
                </select>
            @endcan
            <div class="form-check ms-1"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" @checked(request('mine') === '1') onchange="this.form.requestSubmit()"><label class="form-check-label small" for="mine">{{ __('Việc của tôi') }}</label></div>
        </form>
    </div>

    @php($colors = ['pending' => '#94a3b8', 'in_progress' => '#3b82f6', 'overdue' => '#ef4444', 'completed' => '#22c55e', 'cancelled' => '#475569'])
    <div class="kanban" data-task-board>
        @foreach (['pending', 'in_progress', 'overdue', 'completed', 'cancelled'] as $status)
            @php($label = App\Models\Task::STATUSES[$status][0])
            @php($items = $tasks->get($status, collect()))
            <div class="kanban-col" style="--stage-color: {{ $colors[$status] }}">
                <div class="kanban-col-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="title">{{ __($label) }}</span>
                        <span class="badge badge-soft-secondary col-count">{{ $items->count() }}</span>
                    </div>
                    @if (in_array($status, ['completed', 'cancelled'], true))<div class="meta">{{ __('Cập nhật trong 30 ngày') }}</div>@endif
                </div>
                <div class="kanban-list" data-status="{{ $status }}">
                    @foreach ($items as $t)
                        @php($movable = auth()->user()->can('update', $t))
                        <div class="kanban-card {{ $movable ? '' : 'kanban-locked' }}" data-move-url="{{ route('tasks.status', $t) }}">
                            <a href="{{ route('tasks.show', $t) }}" class="title">{{ $t->title }}</a>
                            @if ($t->project)<div class="small text-muted text-truncate mb-1"><i class="bi bi-kanban me-1"></i>{{ $t->project->name }}</div>@endif
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                {!! badge(App\Models\Task::PRIORITIES, $t->priority) !!}
                                @if ($t->checklist_count)<span class="badge badge-soft-secondary"><i class="bi bi-check2-square"></i> {{ $t->checklist_done_count }}/{{ $t->checklist_count }}</span>@endif
                                @if ($t->subtasks_count)<span class="badge badge-soft-secondary"><i class="bi bi-diagram-2"></i> {{ $t->subtasks_count }}</span>@endif
                            </div>
                            <div class="d-flex justify-content-between align-items-center small text-muted">
                                <span class="{{ $t->is_late ? 'text-danger fw-semibold' : '' }}"><i class="bi bi-calendar-event me-1"></i>{{ vn_date($t->due_date, true) }}</span>
                                @if ($t->assignee)<x-avatar :user="$t->assignee" size="sm" />@endif
                            </div>
                        </div>
                    @endforeach
                    <div class="kanban-empty text-center text-muted small py-4 {{ $items->isNotEmpty() ? 'd-none' : '' }}" style="pointer-events:none"><i class="bi bi-inbox d-block fs-4 mb-1"></i>{{ __('Kéo thẻ vào đây') }}</div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
