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
                    <dt>{{ __('Hạn chót') }}</dt><dd class="{{ $task->is_late ? 'text-danger' : '' }}">{{ vn_date($task->due_date, true) }}</dd>
                    <dt>Giao cho</dt><dd>{{ $task->assignee?->name ?? '—' }}</dd>
                    <dt>{{ __('Người tạo') }}</dt><dd>{{ $task->creator?->name ?? '—' }}</dd>
                    <dt>{{ __('Liên quan') }}</dt><dd>@include('tasks._related')</dd>
                    <dt>{{ __('Hoàn thành lúc') }}</dt><dd>{{ vn_date($task->completed_at, true) }}</dd>
                    <dt>{{ __('Ngày tạo') }}</dt><dd>{{ vn_date($task->created_at, true) }}</dd>
                </dl>
                @can('update', $task)
                    <hr>
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
