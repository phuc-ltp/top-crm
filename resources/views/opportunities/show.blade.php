@extends('layouts.app')
@section('title', $opportunity->title)
@section('content')
    <x-page-header :title="$opportunity->title" :breadcrumbs="['Pipeline' => route('opportunities.index'), $opportunity->code => null]">
        @can('create', App\Models\Quotation::class)<a href="{{ route('quotations.create', ['customer_id' => $opportunity->customer_id, 'opportunity_id' => $opportunity->id]) }}" class="btn btn-light"><i class="bi bi-file-earmark-text me-1"></i>{{ __('Tạo báo giá') }}</a>@endcan
        @can('create', App\Models\Contract::class)<a href="{{ route('contracts.create', ['opportunity_id' => $opportunity->id]) }}" class="btn btn-light"><i class="bi bi-file-earmark-check me-1"></i>{{ __('Tạo hợp đồng') }}</a>@endcan
        @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', ['related_type' => 'opportunity', 'related_id' => $opportunity->id]) }}" class="btn btn-light"><i class="bi bi-check2-square me-1"></i>{{ __('Tạo công việc') }}</a>@endcan
        @can('update', $opportunity)<a href="{{ route('opportunities.edit', $opportunity) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>@endcan
        @can('delete', $opportunity)<x-delete-button :action="route('opportunities.destroy', $opportunity)" class="btn btn-light text-danger" :label="__('Xóa')" />@endcan
    </x-page-header>

    {{-- Thanh tiến trình giai đoạn --}}
    <div class="card mb-4"><div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2">
            @foreach ($stages as $s)
                @php($active = $s->id === $opportunity->pipeline_stage_id)
                <span class="badge px-3 py-2 {{ $active ? '' : 'badge-soft-secondary' }}" style="{{ $active ? 'background:'.$s->color : '' }}; font-size:.8rem">
                    @if ($s->position < $opportunity->stage->position && ! $opportunity->stage->is_lost)<i class="bi bi-check2 me-1"></i>@endif{{ $s->label }}
                </span>
            @endforeach
        </div>
    </div></div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body">
                <div class="text-center mb-3">
                    <div class="text-muted small">{{ __('Giá trị cơ hội') }}</div>
                    <div class="fs-3 fw-bold text-orange">{{ money($opportunity->value) }}</div>
                    <div class="small text-muted">{{ __('Xác suất :p% · Trọng số :value', ['p' => $opportunity->probability, 'value' => money($opportunity->weighted_value)]) }}</div>
                </div>
                <dl class="detail-list">
                    <dt>{{ __('Mã') }}</dt><dd>{{ $opportunity->code }}</dd>
                    <dt>{{ __('Pipeline') }}</dt><dd>@if ($opportunity->pipeline)<a href="{{ route('opportunities.index', ['pipeline' => $opportunity->pipeline_id]) }}">{{ $opportunity->pipeline->label }}</a>@else — @endif</dd>
                    <dt>{{ __('labels.customer') }}</dt><dd>@if ($opportunity->customer)<a href="{{ route('customers.show', $opportunity->customer) }}">{{ $opportunity->customer->name }}</a>@else — @endif</dd>
                    <dt>{{ __('Người liên hệ') }}</dt><dd>{{ $opportunity->contact?->full_name ?? '—' }}</dd>
                    <dt>{{ __('Từ lead') }}</dt><dd>@if ($opportunity->lead)<a href="{{ route('leads.show', $opportunity->lead) }}">{{ $opportunity->lead->code }}</a>@else — @endif</dd>
                    <dt>{{ __('Dự kiến chốt') }}</dt><dd>{{ vn_date($opportunity->expected_close_date) }}</dd>
                    <dt>{{ __('Ngày đóng') }}</dt><dd>{{ vn_date($opportunity->closed_at, true) }}</dd>
                    @if ($opportunity->lostReasonRef || $opportunity->lost_reason)<dt>{{ __('Lý do thất bại') }}</dt><dd class="text-danger">{{ $opportunity->lostReasonRef?->name }}@if ($opportunity->lostReasonRef && $opportunity->lost_reason) — @endif{{ $opportunity->lost_reason }}</dd>@endif
                    <dt>{{ __('Phụ trách') }}</dt><dd>{{ $opportunity->assignee?->name ?? '—' }}</dd>
                    <dt>{{ __('Người tạo') }}</dt><dd>{{ $opportunity->creator?->name ?? '—' }}</dd>
                </dl>
                @if ($opportunity->notes)<hr><div class="small" style="white-space: pre-line">{{ $opportunity->notes }}</div>@endif
            </div></div>

            <div class="card mb-4">
                <div class="card-header"><span><i class="bi bi-paperclip me-1 text-orange"></i>{{ __('Tệp đính kèm (:count)', ['count' => $opportunity->attachments->count()]) }}</span></div>
                <div class="card-body">
                    @forelse ($opportunity->attachments as $a)
                        <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                            <i class="bi bi-file-earmark text-orange"></i>
                            <div class="min-w-0 flex-grow-1">
                                <a href="{{ route('opportunities.attachments.download', [$opportunity, $a]) }}" class="small d-block text-truncate">{{ $a->original_name }}</a>
                                <span class="small-xs text-muted">{{ $a->human_size }} · {{ $a->user?->name }} · {{ vn_date($a->created_at) }}</span>
                            </div>
                            @can('update', $opportunity)<x-delete-button :action="route('opportunities.attachments.destroy', [$opportunity, $a])" :message="__('Xóa tệp này?')" />@endcan
                        </div>
                    @empty
                        <div class="small text-muted">{{ __('Chưa có tệp nào.') }}</div>
                    @endforelse
                    @can('update', $opportunity)
                        <form method="POST" action="{{ route('opportunities.attachments.store', $opportunity) }}" enctype="multipart/form-data" class="d-flex gap-2 mt-3 needs-validation" novalidate>@csrf
                            <input type="file" name="file" class="form-control form-control-sm" required aria-label="{{ __('Chọn tệp') }}">
                            <button class="btn btn-soft-primary btn-sm text-nowrap"><i class="bi bi-upload"></i> {{ __('Tải lên') }}</button>
                        </form>
                    @endcan
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Lịch sử giai đoạn') }}</div>
                <div class="card-body">
                    <ul class="timeline">
                        @foreach ($opportunity->stageHistories as $h)
                            <li><span class="tl-icon"><i class="bi bi-arrow-right"></i></span>
                                <div class="small fw-semibold">{{ $h->fromStage ? __($h->fromStage->name) : __('Tạo mới') }} → {{ $h->toStage ? __($h->toStage->name) : '' }}</div>
                                <div class="small text-muted">{{ $h->user?->name ?? __('Hệ thống') }} · {{ vn_date($h->created_at, true) }}</div></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            @if ($opportunity->items->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header"><span><i class="bi bi-box-seam me-2 text-orange"></i>{{ __('Sản phẩm / dịch vụ dự kiến') }}</span></div>
                    <div class="table-responsive"><table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Sản phẩm / dịch vụ') }}</th><th class="text-end">{{ __('SL') }}</th><th class="text-end">{{ __('Đơn giá') }}</th><th class="text-end">{{ __('CK %') }}</th><th class="text-end">{{ __('Thành tiền') }}</th></tr></thead>
                        <tbody>
                        @foreach ($opportunity->items as $it)
                            <tr><td>{{ $it->name }}@if ($it->product)<div class="small-xs text-muted">{{ $it->product->sku }}</div>@endif</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $it->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="text-end">{{ money($it->unit_price) }}</td><td class="text-end">{{ (float) $it->discount_percent }}%</td><td class="text-end fw-semibold">{{ money($it->line_total) }}</td></tr>
                        @endforeach
                        </tbody>
                        <tfoot><tr><td colspan="4" class="text-end fw-semibold">{{ __('Tổng') }}</td><td class="text-end fw-bold">{{ money($opportunity->items->sum('line_total')) }}</td></tr></tfoot>
                    </table></div>
                </div>
            @endif
            @include('partials.interactions', ['subject' => $opportunity, 'type' => 'opportunity', 'canAdd' => auth()->user()->can('update', $opportunity)])

            <div class="card mt-4">
                <div class="card-header">{{ __('Báo giá liên quan') }}</div>
                <div class="table-responsive"><table class="table">
                    <thead><tr><th>{{ __('Số') }}</th><th>{{ __('Ngày') }}</th><th class="text-end">{{ __('Tổng') }}</th><th>{{ __('Trạng thái') }}</th></tr></thead>
                    <tbody>
                    @forelse ($opportunity->quotations as $q)
                        <tr><td><a href="{{ route('quotations.show', $q) }}" class="row-link">{{ $q->number }}</a></td><td>{{ vn_date($q->issue_date) }}</td><td class="text-end">{{ money($q->total) }}</td><td>{!! badge(App\Models\Quotation::STATUSES, $q->status) !!}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small text-center py-3">{{ __('Chưa có báo giá.') }}</td></tr>
                    @endforelse
                    </tbody></table></div>
            </div>
            <div class="card mt-4">
                <div class="card-header">{{ __('Công việc liên quan') }}</div>
                <div class="table-responsive"><table class="table">
                    <thead><tr><th>{{ __('labels.task') }}</th><th>{{ __('Phụ trách') }}</th><th>{{ __('Hạn') }}</th><th>{{ __('Trạng thái') }}</th></tr></thead>
                    <tbody>
                    @forelse ($opportunity->tasks as $t)
                        <tr><td><a href="{{ route('tasks.show', $t) }}" class="row-link">{{ $t->title }}</a></td><td class="small">{{ $t->assignee?->name }}</td><td class="small">{{ vn_date($t->due_date, true) }}</td><td>{!! badge(App\Models\Task::STATUSES, $t->status) !!}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small text-center py-3">{{ __('Chưa có công việc.') }}</td></tr>
                    @endforelse
                    </tbody></table></div>
            </div>
        </div>
    </div>
@endsection
