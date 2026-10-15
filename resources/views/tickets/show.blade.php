@extends('layouts.app')
@section('title', $ticket->subject)
@section('content')
    <x-page-header :title="$ticket->subject" :breadcrumbs="['Hỗ trợ' => route('tickets.index'), $ticket->code => null]">
        @can('update', $ticket)<a href="{{ route('tickets.edit', $ticket) }}" class="btn btn-light"><i class="bi bi-pencil me-1"></i>Chỉnh sửa</a>@endcan
        @can('delete', $ticket)<x-delete-button :action="route('tickets.destroy', $ticket)" class="btn btn-light text-danger" label="Xóa" />@endcan
    </x-page-header>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4"><div class="card-body">
                <div class="d-flex gap-2 mb-3">{!! badge(App\Models\SupportTicket::STATUSES, $ticket->status) !!} {!! badge(App\Models\SupportTicket::PRIORITIES, $ticket->priority) !!}</div>
                <div style="white-space: pre-line">{{ $ticket->description }}</div>
                <div class="small text-muted mt-3">Tạo bởi {{ $ticket->creator?->name ?? '—' }} · {{ vn_date($ticket->created_at, true) }}</div>
            </div></div>

            <div class="card">
                <div class="card-header">Lịch sử phản hồi ({{ $ticket->replies->count() }})</div>
                <div class="card-body">
                    @foreach ($ticket->replies as $r)
                        <div class="d-flex gap-2 mb-3">
                            <x-avatar :user="$r->user" size="sm" />
                            <div class="rounded-3 p-2 px-3 flex-grow-1 {{ $r->is_internal ? 'bg-orange-light border border-warning-subtle' : 'bg-light' }}">
                                <div class="small"><strong>{{ $r->user?->name ?? 'Hệ thống' }}</strong>
                                    @if ($r->is_internal)<span class="badge badge-soft-warning ms-1"><i class="bi bi-lock"></i> Ghi chú nội bộ</span>@endif
                                    <span class="text-muted">· {{ vn_date($r->created_at, true) }}</span></div>
                                <div class="small mt-1" style="white-space: pre-line">{{ $r->body }}</div>
                            </div>
                        </div>
                    @endforeach

                    @can('update', $ticket)
                        <form method="POST" action="{{ route('tickets.reply', $ticket) }}" class="mt-4 needs-validation" novalidate>@csrf
                            <textarea name="body" class="form-control mb-2" rows="3" placeholder="Nội dung phản hồi hoặc ghi chú..." required maxlength="10000"></textarea>
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_internal" value="1" id="is_internal"><label class="form-check-label small" for="is_internal">Ghi chú nội bộ (khách hàng không thấy)</label></div>
                                <div class="d-flex gap-2">
                                    <select name="status" class="form-select form-select-sm" aria-label="Đổi trạng thái"><option value="">Giữ trạng thái</option>
                                        @foreach (App\Models\SupportTicket::STATUSES as $k => $s)<option value="{{ $k }}">→ {{ $s[0] }}</option>@endforeach</select>
                                    <button class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-send"></i> Gửi</button>
                                </div>
                            </div>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <dl class="detail-list">
                    <dt>Mã ticket</dt><dd>{{ $ticket->code }}</dd>
                    <dt>Khách hàng</dt><dd>@if ($ticket->customer)<a href="{{ route('customers.show', $ticket->customer) }}">{{ $ticket->customer->name }}</a>@endif</dd>
                    <dt>Người liên hệ</dt><dd>{{ $ticket->contact?->full_name ?? '—' }}</dd>
                    <dt>Phụ trách</dt><dd>{{ $ticket->assignee?->name ?? 'Chưa giao' }}</dd>
                    <dt>Tạo lúc</dt><dd>{{ vn_date($ticket->created_at, true) }}</dd>
                    <dt>Giải quyết lúc</dt><dd>{{ vn_date($ticket->resolved_at, true) }}</dd>
                    <dt>Đóng lúc</dt><dd>{{ vn_date($ticket->closed_at, true) }}</dd>
                    @if ($ticket->resolved_at)<dt>Thời gian xử lý</dt><dd>{{ $ticket->created_at->diffForHumans($ticket->resolved_at, true) }}</dd>@endif
                </dl>
            </div></div>
        </div>
    </div>
@endsection
