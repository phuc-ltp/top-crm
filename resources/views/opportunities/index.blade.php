@extends('layouts.app')
@section('title', 'Pipeline bán hàng')
@section('content')
    <x-page-header title="Pipeline bán hàng" :breadcrumbs="['Pipeline' => null]" subtitle="Kéo thả thẻ giữa các cột để cập nhật giai đoạn">
        <a href="{{ route('opportunities.list', request()->query()) }}" class="btn btn-light"><i class="bi bi-list-ul me-1"></i>Dạng danh sách</a>
        @can('export', App\Models\Opportunity::class)<a href="{{ route('opportunities.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>Xuất Excel</a>@endcan
        @can('create', App\Models\Opportunity::class)<a href="{{ route('opportunities.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tạo cơ hội</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><x-stat-card icon="bi-kanban" label="Giá trị pipeline đang mở" :value="money($openValue)" /></div>
        <div class="col-md-4"><x-stat-card icon="bi-graph-up-arrow" tone="blue" label="Giá trị có trọng số (× xác suất)" :value="money($weighted)" /></div>
        <div class="col-md-4">
            <div class="card h-100"><form method="GET" class="p-3 d-flex flex-wrap gap-2 align-items-center h-100" data-auto-submit>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="flex:1;min-width:140px" placeholder="Tìm cơ hội, khách hàng...">
                @can('opportunities.view_all')
                    <select name="assigned_to" class="form-select form-select-sm w-auto"><option value="">Mọi nhân viên</option>
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
                        <span class="title">{{ $stage->name }}</span>
                        <span class="badge badge-soft-secondary col-count">{{ $items->count() }}</span>
                    </div>
                    <div class="meta"><span class="col-total">{{ money($items->sum('value')) }}</span> · {{ $stage->probability }}%</div>
                </div>
                <div class="kanban-list" data-stage="{{ $stage->id }}">
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
                    <div class="kanban-empty text-center text-muted small py-4 {{ $items->isNotEmpty() ? 'd-none' : '' }}" style="pointer-events:none"><i class="bi bi-inbox d-block fs-4 mb-1"></i>Kéo thẻ vào đây</div>
                </div>
                @can('create', App\Models\Opportunity::class)
                    <a href="{{ route('opportunities.create', ['stage' => $stage->id]) }}" class="d-block text-center small py-2 text-muted border-top"><i class="bi bi-plus"></i> Thêm cơ hội</a>
                @endcan
            </div>
        @endforeach
    </div>
@endsection
