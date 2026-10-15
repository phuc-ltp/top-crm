@extends('layouts.app')
@section('title', $opportunity->title)
@section('content')
    <x-page-header :title="$opportunity->title" :breadcrumbs="['Pipeline' => route('opportunities.index'), $opportunity->code => null]">
        @can('create', App\Models\Quotation::class)<a href="{{ route('quotations.create', ['customer_id' => $opportunity->customer_id, 'opportunity_id' => $opportunity->id]) }}" class="btn btn-light"><i class="bi bi-file-earmark-text me-1"></i>Tạo báo giá</a>@endcan
        @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', ['related_type' => 'opportunity', 'related_id' => $opportunity->id]) }}" class="btn btn-light"><i class="bi bi-check2-square me-1"></i>Tạo công việc</a>@endcan
        @can('update', $opportunity)<a href="{{ route('opportunities.edit', $opportunity) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Chỉnh sửa</a>@endcan
        @can('delete', $opportunity)<x-delete-button :action="route('opportunities.destroy', $opportunity)" class="btn btn-light text-danger" label="Xóa" />@endcan
    </x-page-header>

    {{-- Thanh tiến trình giai đoạn --}}
    <div class="card mb-4"><div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2">
            @foreach ($stages as $s)
                @php($active = $s->id === $opportunity->pipeline_stage_id)
                <span class="badge px-3 py-2 {{ $active ? '' : 'badge-soft-secondary' }}" style="{{ $active ? 'background:'.$s->color : '' }}; font-size:.8rem">
                    @if ($s->position < $opportunity->stage->position && ! $opportunity->stage->is_lost)<i class="bi bi-check2 me-1"></i>@endif{{ $s->name }}
                </span>
            @endforeach
        </div>
    </div></div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body">
                <div class="text-center mb-3">
                    <div class="text-muted small">Giá trị cơ hội</div>
                    <div class="fs-3 fw-bold text-orange">{{ money($opportunity->value) }}</div>
                    <div class="small text-muted">Xác suất {{ $opportunity->probability }}% · Trọng số {{ money($opportunity->weighted_value) }}</div>
                </div>
                <dl class="detail-list">
                    <dt>Mã</dt><dd>{{ $opportunity->code }}</dd>
                    <dt>Khách hàng</dt><dd>@if ($opportunity->customer)<a href="{{ route('customers.show', $opportunity->customer) }}">{{ $opportunity->customer->name }}</a>@else — @endif</dd>
                    <dt>Người liên hệ</dt><dd>{{ $opportunity->contact?->full_name ?? '—' }}</dd>
                    <dt>Từ lead</dt><dd>@if ($opportunity->lead)<a href="{{ route('leads.show', $opportunity->lead) }}">{{ $opportunity->lead->code }}</a>@else — @endif</dd>
                    <dt>Dự kiến chốt</dt><dd>{{ vn_date($opportunity->expected_close_date) }}</dd>
                    <dt>Ngày đóng</dt><dd>{{ vn_date($opportunity->closed_at, true) }}</dd>
                    @if ($opportunity->lost_reason)<dt>Lý do thất bại</dt><dd class="text-danger">{{ $opportunity->lost_reason }}</dd>@endif
                    <dt>Phụ trách</dt><dd>{{ $opportunity->assignee?->name ?? '—' }}</dd>
                    <dt>Người tạo</dt><dd>{{ $opportunity->creator?->name ?? '—' }}</dd>
                </dl>
                @if ($opportunity->notes)<hr><div class="small" style="white-space: pre-line">{{ $opportunity->notes }}</div>@endif
            </div></div>

            <div class="card">
                <div class="card-header">Lịch sử giai đoạn</div>
                <div class="card-body">
                    <ul class="timeline">
                        @foreach ($opportunity->stageHistories as $h)
                            <li><span class="tl-icon"><i class="bi bi-arrow-right"></i></span>
                                <div class="small fw-semibold">{{ $h->fromStage?->name ?? 'Tạo mới' }} → {{ $h->toStage?->name }}</div>
                                <div class="small text-muted">{{ $h->user?->name ?? 'Hệ thống' }} · {{ vn_date($h->created_at, true) }}</div></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            @include('partials.interactions', ['subject' => $opportunity, 'type' => 'opportunity', 'canAdd' => auth()->user()->can('update', $opportunity)])

            <div class="card mt-4">
                <div class="card-header">Báo giá liên quan</div>
                <div class="table-responsive"><table class="table">
                    <thead><tr><th>Số</th><th>Ngày</th><th class="text-end">Tổng</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    @forelse ($opportunity->quotations as $q)
                        <tr><td><a href="{{ route('quotations.show', $q) }}" class="row-link">{{ $q->number }}</a></td><td>{{ vn_date($q->issue_date) }}</td><td class="text-end">{{ money($q->total) }}</td><td>{!! badge(App\Models\Quotation::STATUSES, $q->status) !!}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small text-center py-3">Chưa có báo giá.</td></tr>
                    @endforelse
                    </tbody></table></div>
            </div>
            <div class="card mt-4">
                <div class="card-header">Công việc liên quan</div>
                <div class="table-responsive"><table class="table">
                    <thead><tr><th>Công việc</th><th>Phụ trách</th><th>Hạn</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    @forelse ($opportunity->tasks as $t)
                        <tr><td><a href="{{ route('tasks.show', $t) }}" class="row-link">{{ $t->title }}</a></td><td class="small">{{ $t->assignee?->name }}</td><td class="small">{{ vn_date($t->due_date, true) }}</td><td>{!! badge(App\Models\Task::STATUSES, $t->status) !!}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small text-center py-3">Chưa có công việc.</td></tr>
                    @endforelse
                    </tbody></table></div>
            </div>
        </div>
    </div>
@endsection
