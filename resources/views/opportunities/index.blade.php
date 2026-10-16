@extends('layouts.app')
@section('title', __('Pipeline bán hàng'))
@section('content')
    <x-page-header :title="$pipeline?->label ?? __('Pipeline bán hàng')" :breadcrumbs="['Pipeline' => null]" :subtitle="__('Kéo thả thẻ giữa các cột để cập nhật giai đoạn')">
        <a href="{{ route('opportunities.forecast') }}" class="btn btn-light"><i class="bi bi-graph-up-arrow me-1"></i>{{ __('Dự báo') }}</a>
        <a href="{{ route('opportunities.list', request()->query()) }}" class="btn btn-light"><i class="bi bi-list-ul me-1"></i>{{ __('Dạng danh sách') }}</a>
        @can('settings.manage')<a href="{{ route('settings.pipelines') }}" class="btn btn-light"><i class="bi bi-sliders me-1"></i>{{ __('Cấu hình pipeline') }}</a>@endcan
        @can('export', App\Models\Opportunity::class)<a href="{{ route('opportunities.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>{{ __('Xuất Excel') }}</a>@endcan
        @can('create', App\Models\Opportunity::class)<a href="{{ route('opportunities.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo cơ hội') }}</a>@endcan
    </x-page-header>

    @if ($pipelines->count() > 1)
        <ul class="nav nav-tabs-crm mb-3" role="tablist">
            @foreach ($pipelines as $p)
                <li class="nav-item"><a class="nav-link {{ $pipeline?->id === $p->id ? 'active' : '' }}" href="{{ route('opportunities.index', ['pipeline' => $p->id] + request()->except('pipeline')) }}">{{ $p->label }}</a></li>
            @endforeach
        </ul>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-4"><x-stat-card icon="bi-kanban" :label="__('Giá trị pipeline đang mở')" :value="money($openValue)" /></div>
        <div class="col-md-4"><x-stat-card icon="bi-graph-up-arrow" tone="blue" :label="__('Giá trị có trọng số (× xác suất)')" :value="money($weighted)" /></div>
        <div class="col-md-4">
            <div class="card h-100"><form method="GET" class="p-3 d-flex flex-wrap gap-2 align-items-center h-100" data-auto-submit>
                @if ($pipeline)<input type="hidden" name="pipeline" value="{{ $pipeline->id }}">@endif
                <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="flex:1;min-width:140px" placeholder="{{ __('Tìm cơ hội, khách hàng...') }}">
                @can('opportunities.view_all')
                    <select name="assigned_to" class="form-select form-select-sm w-auto"><option value="">{{ __('Mọi nhân viên') }}</option>
                        @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach
                    </select>
                @endcan
                <button class="btn btn-soft-primary btn-sm"><i class="bi bi-search"></i></button>
            </form></div>
        </div>
    </div>

    @php($readonly = ! auth()->user()->can('opportunities.update'))
    <div class="kanban" data-kanban data-readonly="{{ $readonly ? '1' : '0' }}">
        @foreach ($stages as $stage)
            @php($items = $opportunities->get($stage->id, collect()))
            <div class="kanban-col" style="--stage-color: {{ $stage->color }}">
                <div class="kanban-col-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="title">{{ $stage->label }}</span>
                        <span class="badge badge-soft-secondary col-count">{{ $items->count() }}</span>
                    </div>
                    <div class="meta"><span class="col-total">{{ money($items->sum('value')) }}</span> · {{ $stage->probability }}%</div>
                </div>
                <div class="kanban-list" data-stage="{{ $stage->id }}" data-lost="{{ $stage->is_lost ? '1' : '0' }}">
                    @foreach ($items as $o)
                        <div class="kanban-card" data-id="{{ $o->id }}" data-value="{{ (float) $o->value }}" data-move-url="{{ route('opportunities.move', $o) }}">
                            <a href="{{ route('opportunities.show', $o) }}" class="title">{{ $o->title }}</a>
                            <div class="small text-muted text-truncate mb-2"><i class="bi bi-building me-1"></i>{{ $o->customer?->name ?? '—' }}</div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="value">{{ money($o->value) }}</span>
                                <span class="badge badge-soft-orange card-prob">{{ $o->probability }}%</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                                <span class="{{ $o->expected_close_date && $o->expected_close_date->isPast() && ! $stage->isClosed() ? 'text-danger' : '' }}"><i class="bi bi-calendar-event me-1"></i>{{ vn_date($o->expected_close_date) }}</span>
                                @if ($o->assignee)<x-avatar :user="$o->assignee" size="sm" />@endif
                            </div>
                        </div>
                    @endforeach
                    <div class="kanban-empty text-center text-muted small py-4 {{ $items->isNotEmpty() ? 'd-none' : '' }}" style="pointer-events:none"><i class="bi bi-inbox d-block fs-4 mb-1"></i>{{ __('Kéo thẻ vào đây') }}</div>
                </div>
                @can('create', App\Models\Opportunity::class)
                    <a href="{{ route('opportunities.create', ['stage' => $stage->id]) }}" class="d-block text-center small py-2 text-muted border-top"><i class="bi bi-plus"></i> {{ __('Thêm cơ hội') }}</a>
                @endcan
            </div>
        @endforeach
    </div>

    {{-- Kéo thẻ vào cột thất bại: bắt buộc chọn lý do --}}
    <div class="modal fade" id="lostReasonModal" tabindex="-1" aria-labelledby="lostReasonTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="lostReasonTitle">{{ __('Lý do thất bại') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
            <div class="modal-body">
                <label class="form-label" for="lostReasonSelect">{{ __('Vì sao cơ hội này thất bại?') }} <span class="req">*</span></label>
                <select id="lostReasonSelect" class="form-select mb-3"><option value="">{{ __('— Chọn lý do —') }}</option>@foreach ($lostReasons as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                <label class="form-label" for="lostReasonNote">{{ __('Ghi chú (không bắt buộc)') }}</label>
                <input type="text" id="lostReasonNote" class="form-control" maxlength="500">
                <div class="invalid-feedback d-block d-none" id="lostReasonError">{{ __('Vui lòng chọn lý do thất bại.') }}</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button type="button" class="btn btn-danger" id="lostReasonOk">{{ __('Xác nhận thất bại') }}</button></div>
        </div></div>
    </div>
@endsection
