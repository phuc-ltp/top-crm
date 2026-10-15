@extends('layouts.app')
@section('title', $customer->name)
@section('content')
    <x-page-header :title="$customer->name" :breadcrumbs="['Khách hàng' => route('customers.index'), $customer->code => null]">
        @can('create', App\Models\Opportunity::class)<a href="{{ route('opportunities.create', ['customer_id' => $customer->id]) }}" class="btn btn-light"><i class="bi bi-kanban me-1"></i>{{ __('Tạo cơ hội') }}</a>@endcan
        @can('create', App\Models\Quotation::class)<a href="{{ route('quotations.create', ['customer_id' => $customer->id]) }}" class="btn btn-light"><i class="bi bi-file-earmark-text me-1"></i>{{ __('Tạo báo giá') }}</a>@endcan
        @can('update', $customer)<a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>@endcan
        @can('delete', $customer)<x-delete-button :action="route('customers.destroy', $customer)" :message="__('Lưu trữ khách hàng này?')" class="btn btn-light text-danger" :label="__('Lưu trữ')" />@endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><x-stat-card icon="bi-cash-coin" tone="green" :label="__('Đã thanh toán')" :value="money($stats['revenue'])" /></div>
        <div class="col-md-4"><x-stat-card icon="bi-kanban" tone="orange" :label="__('Cơ hội đang mở')" :value="money($stats['open_deals'])" /></div>
        <div class="col-md-4"><x-stat-card icon="bi-headset" tone="blue" :label="__('Ticket đang xử lý')" :value="$stats['tickets_open']" /></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar avatar-lg tone-orange"><i class="bi {{ $customer->type === 'business' ? 'bi-building' : 'bi-person' }}"></i></span>
                        <div class="min-w-0">
                            <div class="text-muted small">{{ $customer->code }} · {{ __(App\Models\Customer::TYPES[$customer->type]) }}</div>
                            <div class="fw-bold">{{ $customer->name }}</div>
                            {!! badge(App\Models\Customer::STATUSES, $customer->status) !!}
                        </div>
                    </div>
                    <dl class="detail-list">
                        <dt>{{ __('Điện thoại') }}</dt><dd>{{ $customer->phone ?? '—' }}</dd>
                        <dt>Email</dt><dd>@if ($customer->email)<a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>@else — @endif</dd>
                        <dt>{{ __('Công ty') }}</dt><dd>{{ $customer->company ?? '—' }}</dd>
                        <dt>{{ __('Địa chỉ') }}</dt><dd>{{ $customer->address ?? '—' }}</dd>
                        <dt>{{ __('Nguồn') }}</dt><dd>{{ $customer->source?->label ?? "—" }}</dd>
                        <dt>{{ __('Phụ trách') }}</dt><dd>{{ $customer->assignee?->name ?? __('Chưa phân công') }}</dd>
                        <dt>{{ __('Thẻ') }}</dt><dd>@forelse ($customer->tags as $tag)<span class="tag-chip me-1" style="background: {{ $tag->color }}">{{ $tag->name }}</span>@empty — @endforelse</dd>
                        <dt>{{ __('Người tạo') }}</dt><dd>{{ $customer->creator?->name ?? '—' }}</dd>
                        <dt>{{ __('Ngày tạo') }}</dt><dd>{{ vn_date($customer->created_at, true) }}</dd>
                        <dt>{{ __('Cập nhật') }}</dt><dd>{{ vn_date($customer->updated_at, true) }}</dd>
                    </dl>
                    @if ($customer->notes)<hr><div class="small" style="white-space: pre-line">{{ $customer->notes }}</div>@endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span>{{ __('Người liên hệ (:count)', ['count' => $customer->contacts->count()]) }}</span>
                    @can('create', App\Models\Contact::class)<a href="{{ route('contacts.create', ['customer_id' => $customer->id]) }}" class="btn btn-soft-primary btn-sm"><i class="bi bi-plus"></i></a>@endcan
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($customer->contacts as $ct)
                        <a href="{{ route('contacts.show', $ct) }}" class="list-group-item list-group-item-action">
                            <div class="fw-semibold small">{{ $ct->full_name }}</div>
                            <div class="small text-muted">{{ $ct->position }} · {{ $ct->phone }}</div>
                        </a>
                    @empty
                        <div class="p-3 small text-muted">{{ __('Chưa có người liên hệ.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <ul class="nav nav-tabs-crm mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-activity" type="button">{{ __('Tương tác') }}</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-deals" type="button">{{ __('Cơ hội (:count)', ['count' => $customer->opportunities->count()]) }}</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-invoices" type="button">{{ __('Hóa đơn (:count)', ['count' => $customer->invoices->count()]) }}</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tickets" type="button">{{ __('Hỗ trợ (:count)', ['count' => $customer->tickets->count()]) }}</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tasks" type="button">{{ __('Công việc (:count)', ['count' => $customer->tasks->count()]) }}</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-activity">
                    @include('partials.interactions', ['subject' => $customer, 'type' => 'customer', 'canAdd' => auth()->user()->can('update', $customer)])
                </div>
                <div class="tab-pane fade" id="tab-deals">
                    <div class="card"><div class="table-responsive"><table class="table table-hover">
                        <thead><tr><th>{{ __('Cơ hội') }}</th><th>{{ __('Giai đoạn') }}</th><th class="text-end">{{ __('Giá trị') }}</th><th>{{ __('Dự kiến chốt') }}</th></tr></thead>
                        <tbody>
                        @forelse ($customer->opportunities as $o)
                            <tr><td><a href="{{ route('opportunities.show', $o) }}" class="row-link">{{ $o->title }}</a></td>
                                <td><span class="badge" style="background: {{ $o->stage->color }}">{{ $o->stage->label }}</span></td>
                                <td class="text-end">{{ money($o->value) }}</td><td>{{ vn_date($o->expected_close_date) }}</td></tr>
                        @empty
                            <tr><td colspan="4"><x-empty-state icon="bi-kanban" :title="__('Chưa có cơ hội')" /></td></tr>
                        @endforelse
                        </tbody></table></div></div>
                </div>
                <div class="tab-pane fade" id="tab-invoices">
                    <div class="card"><div class="table-responsive"><table class="table table-hover">
                        <thead><tr><th>{{ __('Số HĐ') }}</th><th>{{ __('Ngày') }}</th><th class="text-end">{{ __('Tổng tiền') }}</th><th>{{ __('Thanh toán') }}</th></tr></thead>
                        <tbody>
                        @forelse ($customer->invoices as $inv)
                            <tr><td><a href="{{ route('invoices.show', $inv) }}" class="row-link">{{ $inv->number }}</a></td><td>{{ vn_date($inv->issue_date) }}</td>
                                <td class="text-end">{{ money($inv->total) }}</td><td>{!! badge(App\Models\Invoice::PAYMENT_STATUSES, $inv->payment_status) !!}</td></tr>
                        @empty
                            <tr><td colspan="4"><x-empty-state icon="bi-receipt" :title="__('Chưa có hóa đơn')" /></td></tr>
                        @endforelse
                        </tbody></table></div></div>
                </div>
                <div class="tab-pane fade" id="tab-tickets">
                    <div class="card"><div class="table-responsive"><table class="table table-hover">
                        <thead><tr><th>{{ __('Mã') }}</th><th>{{ __('Chủ đề') }}</th><th>{{ __('Trạng thái') }}</th><th>{{ __('Ngày tạo') }}</th></tr></thead>
                        <tbody>
                        @forelse ($customer->tickets as $t)
                            <tr><td class="small text-muted">{{ $t->code }}</td><td><a href="{{ route('tickets.show', $t) }}" class="row-link">{{ $t->subject }}</a></td>
                                <td>{!! badge(App\Models\SupportTicket::STATUSES, $t->status) !!}</td><td>{{ vn_date($t->created_at) }}</td></tr>
                        @empty
                            <tr><td colspan="4"><x-empty-state icon="bi-headset" :title="__('Chưa có ticket')" /></td></tr>
                        @endforelse
                        </tbody></table></div></div>
                </div>
                <div class="tab-pane fade" id="tab-tasks">
                    <div class="card"><div class="table-responsive"><table class="table table-hover">
                        <thead><tr><th>{{ __('labels.task') }}</th><th>{{ __('Phụ trách') }}</th><th>{{ __('Hạn') }}</th><th>{{ __('Trạng thái') }}</th></tr></thead>
                        <tbody>
                        @forelse ($customer->tasks as $t)
                            <tr><td><a href="{{ route('tasks.show', $t) }}" class="row-link">{{ $t->title }}</a></td><td class="small">{{ $t->assignee?->name }}</td>
                                <td class="small">{{ vn_date($t->due_date, true) }}</td><td>{!! badge(App\Models\Task::STATUSES, $t->status) !!}</td></tr>
                        @empty
                            <tr><td colspan="4"><x-empty-state icon="bi-check2-square" :title="__('Chưa có công việc')" /></td></tr>
                        @endforelse
                        </tbody></table></div></div>
                </div>
            </div>
        </div>
    </div>
@endsection
