@extends('layouts.app')
@section('title', $task->title)
@section('content')
    <x-page-header :title="$task->title" :breadcrumbs="['Công việc' => route('tasks.index'), 'Chi tiết' => null]">
        @can('update', $task)<a href="{{ route('tasks.edit', $task) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>@endcan
        @can('delete', $task)<x-delete-button :action="route('tasks.destroy', $task)" class="btn btn-light text-danger" :label="__('Xóa')" />@endcan
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4"><div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    {!! badge(App\Models\Task::STATUSES, $task->status) !!} {!! badge(App\Models\Task::PRIORITIES, $task->priority) !!}
                    @if ($task->is_late)<span class="badge badge-soft-danger"><i class="bi bi-alarm"></i> {{ __('Trễ hạn') }}</span>@endif
                </div>
                <div style="white-space: pre-line">{{ $task->description ?: __('Không có mô tả.') }}</div>
            </div></div>

            @if ($task->dependencies->where('status', '!=', 'completed')->where('status', '!=', 'cancelled')->isNotEmpty())
                <div class="alert alert-warning small"><i class="bi bi-lock me-1"></i>{{ __('Việc này đang chờ :count việc khác hoàn thành nên chưa thể đánh dấu hoàn thành.', ['count' => $task->dependencies->whereNotIn('status', ['completed', 'cancelled'])->count()]) }}</div>
            @endif

            @unless ($task->parent_id)
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ __('Việc con (:done/:count)', ['done' => $task->subtasks->where('status', 'completed')->count(), 'count' => $task->subtasks->count()]) }}</span>
                        @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', ['parent_id' => $task->id]) }}" class="btn btn-soft-primary btn-sm"><i class="bi bi-plus"></i> {{ __('Thêm việc con') }}</a>@endcan
                    </div>
                    <div class="card-body">
                        @forelse ($task->subtasks as $s)
                            <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                                {!! badge(App\Models\Task::STATUSES, $s->status) !!}
                                <a href="{{ route('tasks.show', $s) }}" class="flex-grow-1 small {{ $s->status === 'completed' ? 'text-decoration-line-through text-muted' : '' }}">{{ $s->title }}</a>
                                <span class="small {{ $s->is_late ? 'text-danger' : 'text-muted' }}">{{ vn_date($s->due_date) }}</span>
                                @if ($s->assignee)<x-avatar :user="$s->assignee" size="sm" />@endif
                            </div>
                        @empty
                            <div class="small text-muted">{{ __('Chưa có việc con. Chia việc lớn thành các việc nhỏ để dễ giao và theo dõi.') }}</div>
                        @endforelse
                    </div>
                </div>
            @endunless

            <div class="card mb-4">
                <div class="card-header">{{ __('Phụ thuộc') }}</div>
                <div class="card-body">
                    <div class="small fw-semibold mb-1">{{ __('Cần xong trước') }}</div>
                    @forelse ($task->dependencies as $d)
                        <div class="d-flex align-items-center gap-2 py-1 border-bottom">
                            <i class="bi {{ in_array($d->status, ['completed', 'cancelled'], true) ? 'bi-unlock text-success' : 'bi-lock text-warning' }}"></i>
                            <a href="{{ route('tasks.show', $d) }}" class="flex-grow-1 small">{{ $d->title }}</a>
                            {!! badge(App\Models\Task::STATUSES, $d->status) !!}
                            @can('update', $task)
                                <form method="POST" action="{{ route('tasks.dependencies.destroy', [$task, $d->id]) }}">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-muted p-0" title="{{ __('Bỏ phụ thuộc') }}" aria-label="{{ __('Bỏ phụ thuộc') }}"><i class="bi bi-x-lg"></i></button></form>
                            @endcan
                        </div>
                    @empty
                        <div class="small text-muted mb-2">{{ __('Không phụ thuộc việc nào.') }}</div>
                    @endforelse
                    @can('update', $task)
                        @if ($dependencyOptions)
                            <form method="POST" action="{{ route('tasks.dependencies.store', $task) }}" class="d-flex gap-2 mt-2">@csrf
                                <select name="depends_on_id" class="form-select form-select-sm @error('depends_on_id') is-invalid @enderror" required aria-label="{{ __('Việc phải xong trước') }}">
                                    <option value="">{{ __('— Chọn việc phải xong trước —') }}</option>
                                    @foreach ($dependencyOptions as $id => $title)<option value="{{ $id }}">{{ $title }}</option>@endforeach
                                </select>
                                <button class="btn btn-soft-primary btn-sm text-nowrap"><i class="bi bi-plus"></i> {{ __('Thêm') }}</button>
                            </form>
                            @error('depends_on_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @endif
                    @endcan
                    @if ($task->dependents->isNotEmpty())
                        <div class="small fw-semibold mt-3 mb-1">{{ __('Đang chờ việc này') }}</div>
                        @foreach ($task->dependents as $d)
                            <div class="d-flex align-items-center gap-2 py-1"><i class="bi bi-hourglass text-muted"></i><a href="{{ route('tasks.show', $d) }}" class="flex-grow-1 small">{{ $d->title }}</a>{!! badge(App\Models\Task::STATUSES, $d->status) !!}</div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><span>Checklist ({{ $task->checklist->where('is_done', true)->count() }}/{{ $task->checklist->count() }})</span></div>
                <div class="card-body">
                    @if ($task->checklist->count())
                        <div class="progress mb-3" style="height:6px"><div class="progress-bar" style="width: {{ round($task->checklist->where('is_done', true)->count() / $task->checklist->count() * 100) }}%"></div></div>
                    @endif
                    @foreach ($task->checklist as $item)
                        <div class="d-flex align-items-center gap-2 py-1 border-bottom">
                            @can('update', $task)
                                <form method="POST" action="{{ route('tasks.checklist.toggle', [$task, $item]) }}" data-no-lock>@csrf @method('PATCH')
                                    <input type="checkbox" class="form-check-input" @checked($item->is_done) onchange="this.form.submit()" aria-label="{{ __('Hoàn thành') }}">
                                </form>
                            @else
                                <input type="checkbox" class="form-check-input" @checked($item->is_done) disabled>
                            @endcan
                            <span class="flex-grow-1 small {{ $item->is_done ? 'text-decoration-line-through text-muted' : '' }}">{{ $item->content }}</span>
                            @can('update', $task)
                                <form method="POST" action="{{ route('tasks.checklist.destroy', [$task, $item]) }}">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-muted p-0" title="{{ __('Xóa') }}"><i class="bi bi-x-lg"></i></button></form>
                            @endcan
                        </div>
                    @endforeach
                    @can('update', $task)
                        <form method="POST" action="{{ route('tasks.checklist.store', $task) }}" class="d-flex gap-2 mt-3 needs-validation" novalidate>@csrf
                            <input type="text" name="content" class="form-control form-control-sm" placeholder="{{ __('Thêm mục mới...') }}" required maxlength="255">
                            <button class="btn btn-soft-primary btn-sm text-nowrap"><i class="bi bi-plus"></i> {{ __('Thêm') }}</button>
                        </form>
                    @endcan
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">{{ __('Tệp đính kèm (:count)', ['count' => $task->attachments->count()]) }}</div>
                <div class="card-body">
                    @foreach ($task->attachments as $a)
                        <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                            <i class="bi bi-paperclip text-orange"></i>
                            <a href="{{ route('tasks.attachments.download', [$task, $a]) }}" class="flex-grow-1 small text-truncate">{{ $a->original_name }}</a>
                            <span class="small text-muted">{{ $a->human_size }} · {{ $a->user?->name }}</span>
                            @can('update', $task)<x-delete-button :action="route('tasks.attachments.destroy', [$task, $a])" :message="__('Xóa tệp này?')" />@endcan
                        </div>
                    @endforeach
                    @can('update', $task)
                        <form method="POST" action="{{ route('tasks.attachments.store', $task) }}" enctype="multipart/form-data" class="d-flex gap-2 mt-3 needs-validation" novalidate>@csrf
                            <input type="file" name="file" class="form-control form-control-sm" required aria-label="{{ __('Chọn tệp') }}">
                            <button class="btn btn-soft-primary btn-sm text-nowrap"><i class="bi bi-upload"></i> {{ __('Tải lên') }}</button>
                        </form>
                        <div class="form-text">{{ __('Cho phép: :types — tối đa :size MB.', ['types' => str_replace(',', ', ', config('crm.attachment_mimes')), 'size' => config('crm.attachment_max_kb') / 1024]) }}</div>
                    @endcan
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Bình luận (:count)', ['count' => $task->comments->count()]) }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="mb-4 needs-validation" novalidate>@csrf
                        <textarea name="body" class="form-control mb-2" rows="2" placeholder="{{ __('Viết bình luận...') }}" required maxlength="5000"></textarea>
                        <div class="text-end"><button class="btn btn-primary btn-sm">{{ __('Gửi bình luận') }}</button></div>
                    </form>
                    @foreach ($task->comments as $c)
                        <div class="d-flex gap-2 mb-3">
                            <x-avatar :user="$c->user" size="sm" />
                            <div class="bg-light rounded-3 p-2 px-3 flex-grow-1">
                                <div class="small"><strong>{{ $c->user?->name }}</strong> <span class="text-muted">· {{ $c->created_at->diffForHumans() }}</span></div>
                                <div class="small" style="white-space: pre-line">{{ $c->body }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body">
                <dl class="detail-list">
                    @if ($task->project)<dt>{{ __('Dự án') }}</dt><dd><a href="{{ route('projects.show', $task->project_id) }}">{{ $task->project->name }}</a></dd>@endif
                    @if ($task->parent)<dt>{{ __('Việc cha') }}</dt><dd><a href="{{ route('tasks.show', $task->parent) }}">{{ $task->parent->title }}</a></dd>@endif
                    @if ($task->start_date)<dt>{{ __('Ngày bắt đầu') }}</dt><dd>{{ vn_date($task->start_date) }}</dd>@endif
                    <dt>{{ __('Hạn chót') }}</dt><dd class="{{ $task->is_late ? 'text-danger' : '' }}">{{ vn_date($task->due_date, true) }}</dd>
                    @if ($task->estimated_hours !== null)<dt>{{ __('Giờ ước tính') }}</dt><dd>{{ rtrim(rtrim(number_format((float) $task->estimated_hours, 2, ',', '.'), '0'), ',') }} {{ __('giờ') }}</dd>@endif
                    <dt>Giao cho</dt><dd>{{ $task->assignee?->name ?? '—' }}</dd>
                    <dt>{{ __('Người tạo') }}</dt><dd>{{ $task->creator?->name ?? '—' }}</dd>
                    <dt>{{ __('Liên quan') }}</dt><dd>@include('tasks._related')</dd>
                    <dt>{{ __('Hoàn thành lúc') }}</dt><dd>{{ vn_date($task->completed_at, true) }}</dd>
                    <dt>{{ __('Ngày tạo') }}</dt><dd>{{ vn_date($task->created_at, true) }}</dd>
                </dl>
                @can('update', $task)
                    <hr>
                    @error('status')<div class="alert alert-danger small py-2">{{ $message }}</div>@enderror
                    <form method="POST" action="{{ route('tasks.status', $task) }}" class="d-flex gap-2">@csrf @method('PATCH')
                        <select name="status" class="form-select form-select-sm" aria-label="{{ __('Trạng thái') }}">
                            @foreach (App\Models\Task::STATUSES as $k => $s)<option value="{{ $k }}" @selected($task->status === $k)>{{ __($s[0]) }}</option>@endforeach
                        </select>
                        <button class="btn btn-primary btn-sm text-nowrap">{{ __('Cập nhật') }}</button>
                    </form>
                @endcan
            </div></div>
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
