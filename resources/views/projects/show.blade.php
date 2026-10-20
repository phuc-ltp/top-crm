@extends('layouts.app')
@section('title', $project->name)
@section('content')
    <x-page-header :title="$project->name" :breadcrumbs="['Dự án' => route('projects.index'), 'Chi tiết' => null]" :subtitle="$project->code">
        <a href="{{ route('tasks.board', ['project_id' => $project->id]) }}" class="btn btn-light"><i class="bi bi-kanban me-1"></i>{{ __('Bảng Kanban') }}</a>
        @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', ['project_id' => $project->id]) }}" class="btn btn-light"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm công việc') }}</a>@endcan
        @can('update', $project)<a href="{{ route('projects.edit', $project) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>@endcan
        @can('delete', $project)<x-delete-button :action="route('projects.destroy', $project)" class="btn btn-light text-danger" :label="__('Xóa')" />@endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-graph-up" :label="__('Tiến độ')" :value="$project->progress.'%'" :sub="__(':done/:total việc hoàn thành', ['done' => $project->tasks_done_count, 'total' => $project->tasks_active_count])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-exclamation-octagon" tone="red" :label="__('Việc trễ hạn')" :value="$project->tasks_late_count" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-hourglass-split" tone="blue" :label="__('Giờ ước tính còn lại')" :value="rtrim(rtrim(number_format($hours['remaining'], 1, ',', '.'), '0'), ',')" :sub="__('Tổng: :hours giờ', ['hours' => rtrim(rtrim(number_format($hours['estimated'], 1, ',', '.'), '0'), ',')])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-people" tone="purple" :label="__('Thành viên')" :value="$project->members->count()" /></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between small mb-1"><span>{{ __('Tiến độ') }}</span><span class="fw-semibold">{{ $project->progress }}%</span></div>
                    <div class="progress mb-3" style="height:8px" role="progressbar" aria-label="{{ __('Tiến độ') }}" aria-valuenow="{{ $project->progress }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $project->progress === 100 ? 'bg-success' : '' }}" style="width: {{ $project->progress }}%"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach (App\Models\Task::STATUSES as $k => [$label, $tone])
                            <a href="{{ route('tasks.index', ['project_id' => $project->id, 'status' => $k]) }}" class="badge badge-soft-{{ $tone }} text-decoration-none">{{ __($label) }}: {{ $byStatus[$k] ?? 0 }}</a>
                        @endforeach
                    </div>
                    @if ($project->description)<hr><div class="small" style="white-space: pre-line">{{ $project->description }}</div>@endif
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Công việc (:count)', ['count' => $tasks->count()]) }}</span>
                    <a href="{{ route('tasks.index', ['project_id' => $project->id]) }}" class="small">{{ __('Xem dạng danh sách') }}</a>
                </div>
                @if ($tasks->isEmpty())
                    <x-empty-state icon="bi-check2-square" :title="__('Dự án chưa có công việc')" />
                @else
                    <div class="table-responsive"><table class="table table-hover mb-0">
                        <thead><tr><th>{{ __('labels.task') }}</th><th>{{ __('Hạn chót') }}</th><th>{{ __('Trạng thái') }}</th><th>{{ __('Phụ trách') }}</th></tr></thead>
                        <tbody>
                        @foreach ($tasks as $t)
                            <tr>
                                <td>
                                    @if ($t->parent_id)<i class="bi bi-arrow-return-right text-muted me-1" title="{{ __('Việc con') }}"></i>@endif
                                    <a href="{{ route('tasks.show', $t) }}" class="row-link">{{ $t->title }}</a>
                                    <div class="small text-muted">
                                        {!! badge(App\Models\Task::PRIORITIES, $t->priority) !!}
                                        @if ($t->subtasks_count)<span class="ms-1"><i class="bi bi-diagram-2"></i> {{ $t->subtasks_count }}</span>@endif
                                        @if ($t->blockers_count)<span class="ms-1 text-warning-emphasis" title="{{ __('Đang chờ việc khác xong') }}"><i class="bi bi-lock"></i> {{ __('Đang bị chặn') }}</span>@endif
                                    </div>
                                </td>
                                <td class="small {{ $t->is_late ? 'text-danger fw-semibold' : '' }}">{{ vn_date($t->due_date, true) }}</td>
                                <td>{!! badge(App\Models\Task::STATUSES, $t->status) !!}</td>
                                <td class="small">{{ $t->assignee?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body">
                <dl class="detail-list">
                    <dt>{{ __('Trạng thái') }}</dt><dd>{!! badge(App\Models\Project::STATUSES, $project->status) !!}</dd>
                    <dt>{{ __('Mức ưu tiên') }}</dt><dd>{!! badge(App\Models\Task::PRIORITIES, $project->priority) !!}</dd>
                    <dt>{{ __('Khách hàng') }}</dt><dd>@if ($project->customer)<a href="{{ route('customers.show', $project->customer) }}">{{ $project->customer->name }}</a>@else {{ __('Dự án nội bộ') }} @endif</dd>
                    <dt>{{ __('Người quản lý') }}</dt><dd>{{ $project->manager?->name ?? '—' }}</dd>
                    <dt>{{ __('Thời gian') }}</dt><dd class="{{ $project->is_late ? 'text-danger' : '' }}">{{ vn_date($project->start_date) }} → {{ vn_date($project->end_date) }}</dd>
                    <dt>{{ __('Ngân sách tham khảo') }}</dt><dd>{{ $project->budget !== null ? money($project->budget) : '—' }}</dd>
                    <dt>{{ __('Người tạo') }}</dt><dd>{{ $project->creator?->name ?? '—' }}</dd>
                </dl>
            </div></div>
            <div class="card mb-4">
                <div class="card-header">{{ __('Thành viên (:count)', ['count' => $project->members->count()]) }}</div>
                <div class="card-body">
                    @forelse ($project->members as $m)
                        <div class="d-flex align-items-center gap-2 py-1"><x-avatar :user="$m" size="sm" /><span class="small">{{ $m->name }}</span>
                            @if ($m->id === $project->manager_id)<span class="badge badge-soft-orange ms-auto">{{ __('Quản lý') }}</span>@endif</div>
                    @empty
                        <div class="small text-muted">{{ __('Chưa có thành viên.') }}</div>
                    @endforelse
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Lịch sử cập nhật') }}</div>
                <div class="card-body">
                    <ul class="timeline">
                        @forelse ($history as $h)
                            <li><span class="tl-icon"><i class="bi bi-clock-history"></i></span>
                                <div class="small fw-semibold">{{ $h->description }}</div>
                                <div class="small text-muted">{{ $h->user?->name ?? __('Hệ thống') }} · {{ vn_date($h->created_at, true) }}</div></li>
                        @empty
                            <li class="ps-0 small text-muted">{{ __('Chưa có lịch sử.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
