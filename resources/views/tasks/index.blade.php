@extends('layouts.app')
@section('title', 'Công việc')
@section('content')
    <x-page-header title="Công việc" :breadcrumbs="['Công việc' => null]" subtitle="Giao việc, theo dõi hạn chót và tiến độ">
        <a href="{{ route('tasks.calendar') }}" class="btn btn-light"><i class="bi bi-calendar3 me-1"></i>Lịch công việc</a>
        @can('create', App\Models\Task::class)<a href="{{ route('tasks.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tạo công việc</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-hourglass-split" label="Chưa hoàn thành" :value="$counts['open']" :href="route('tasks.index', ['status' => 'open'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-exclamation-octagon" tone="red" label="Trễ hạn" :value="$counts['late']" :href="route('tasks.index', ['due' => 'late'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-calendar-day" tone="blue" label="Đến hạn hôm nay" :value="$counts['today']" :href="route('tasks.index', ['due' => 'today'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-check2-circle" tone="green" label="Đã hoàn thành" :value="$counts['completed']" :href="route('tasks.index', ['status' => 'completed'])" /></div>
    </div>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm công việc..."></div>
            <select name="status" class="form-select"><option value="">Mọi trạng thái</option><option value="open" @selected(request('status') === 'open')>Chưa hoàn thành</option>
                @foreach (App\Models\Task::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $s[0] }}</option>@endforeach
            </select>
            <select name="priority" class="form-select"><option value="">Mọi mức ưu tiên</option>
                @foreach (App\Models\Task::PRIORITIES as $k => $s)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ $s[0] }}</option>@endforeach
            </select>
            <select name="due" class="form-select"><option value="">Mọi thời hạn</option>
                <option value="today" @selected(request('due') === 'today')>Hôm nay</option><option value="week" @selected(request('due') === 'week')>Tuần này</option><option value="late" @selected(request('due') === 'late')>Trễ hạn</option>
            </select>
            @can('tasks.view_all')
                <select name="assigned_to" class="form-select"><option value="">Mọi nhân viên</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach
                </select>
            @endcan
            <div class="form-check ms-1"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" @checked(request('mine') === '1') onchange="this.form.requestSubmit()"><label class="form-check-label small" for="mine">Việc của tôi</label></div>
        </form>

        @if ($tasks->isEmpty())
            <x-empty-state icon="bi-check2-square" title="Không có công việc nào" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th><x-sort-link column="title" label="Công việc" /></th><th>Liên quan</th><th><x-sort-link column="priority" label="Ưu tiên" /></th>
                    <th><x-sort-link column="due_date" label="Hạn chót" /></th><th>Checklist</th><th><x-sort-link column="status" label="Trạng thái" /></th><th>Phụ trách</th><th class="text-end">Thao tác</th></tr></thead>
                <tbody>
                @foreach ($tasks as $t)
                    <tr>
                        <td><a href="{{ route('tasks.show', $t) }}" class="row-link">{{ $t->title }}</a></td>
                        <td class="small">@include('tasks._related', ['task' => $t])</td>
                        <td>{!! badge(App\Models\Task::PRIORITIES, $t->priority) !!}</td>
                        <td class="small {{ $t->is_late ? 'text-danger fw-semibold' : '' }}">{{ vn_date($t->due_date, true) }}@if ($t->is_late)<div class="small-xs">Trễ {{ $t->due_date->diffForHumans(null, true) }}</div>@endif</td>
                        <td class="small">@if ($t->checklist_count){{ $t->checklist_done_count }}/{{ $t->checklist_count }}@else — @endif</td>
                        <td>{!! badge(App\Models\Task::STATUSES, $t->status) !!}</td>
                        <td class="small">{{ $t->assignee?->name ?? '—' }}</td>
                        <td class="table-actions">
                            @can('update', $t)
                                @if (! in_array($t->status, ['completed', 'cancelled']))
                                    <form method="POST" action="{{ route('tasks.status', $t) }}" class="d-inline">@csrf @method('PATCH')
                                        <input type="hidden" name="status" value="completed"><button class="btn btn-light btn-icon btn-sm text-success" title="Đánh dấu hoàn thành"><i class="bi bi-check2"></i></button></form>
                                @endif
                                <a href="{{ route('tasks.edit', $t) }}" class="btn btn-light btn-icon btn-sm" title="Sửa"><i class="bi bi-pencil"></i></a>
                            @endcan
                            @can('delete', $t)<x-delete-button :action="route('tasks.destroy', $t)" />@endcan
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$tasks" />
        @endif
    </div>
@endsection
