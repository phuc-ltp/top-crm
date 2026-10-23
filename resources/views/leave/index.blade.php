@extends('layouts.app')
@section('title', __('Nghỉ phép'))
@section('content')
    @php($T = App\Models\LeaveRequest::TYPES)
    @php($remaining = (float) $balance['quota'] - (float) $balance['used'])
    <x-page-header :title="__('Nghỉ phép năm :y', ['y' => $year])" :breadcrumbs="['Chấm công & lương' => route('attendance.index'), 'Nghỉ phép' => null]"
                   :subtitle="__('Số ngày nghỉ tự trừ cuối tuần và ngày lễ. Phép năm và việc riêng có lương được trả lương; nghỉ ốm (BHXH chi trả) và nghỉ không lương thì không.')">
        <form method="GET" class="d-flex gap-2" data-auto-submit><input type="hidden" name="tab" value="{{ $tab }}">
            <select name="year" class="form-select" aria-label="{{ __('Năm') }}">@for ($y = now()->year + 1; $y >= now()->year - 3; $y--)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endfor</select></form>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveModal"><i class="bi bi-plus-lg me-1"></i>{{ __('Xin nghỉ phép') }}</button>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-calendar2-heart" tone="blue" :label="__('Quỹ phép năm')" :value="(float) $balance['quota'].' '.__('ngày')" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-calendar-check" :label="__('Đã nghỉ (đã duyệt)')" :value="(float) $balance['used'].' '.__('ngày')" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-hourglass-split" tone="orange" :label="__('Đang chờ duyệt')" :value="(float) $balance['pending'].' '.__('ngày')" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-calendar2-plus" tone="green" :label="__('Còn lại')" :value="$remaining.' '.__('ngày')" /></div>
    </div>

    <ul class="nav nav-tabs-crm mb-3">
        <li class="nav-item"><a class="nav-link {{ $tab === 'mine' ? 'active' : '' }}" href="{{ route('leave.index', ['tab' => 'mine', 'year' => $year]) }}">{{ __('Của tôi') }}</a></li>
        @if ($canApprove)<li class="nav-item"><a class="nav-link {{ $tab === 'approve' ? 'active' : '' }}" href="{{ route('leave.index', ['tab' => 'approve', 'year' => $year]) }}">{{ __('Chờ tôi duyệt') }} @if ($pendingForMe)<span class="badge bg-danger ms-1">{{ $pendingForMe }}</span>@endif</a></li>@endif
        @if ($canAll)<li class="nav-item"><a class="nav-link {{ $tab === 'all' ? 'active' : '' }}" href="{{ route('leave.index', ['tab' => 'all', 'year' => $year]) }}">{{ __('Tất cả') }}</a></li>@endif
    </ul>

    <div class="row g-3">
        <div class="col-12"><div class="card">
            <div class="card-body border-bottom">
                <form method="GET" class="row g-2" data-auto-submit>
                    <input type="hidden" name="tab" value="{{ $tab }}"><input type="hidden" name="year" value="{{ $year }}">
                    <div class="col-6 col-md-4"><select name="status" class="form-select" aria-label="{{ __('Trạng thái') }}"><option value="">{{ __('Mọi trạng thái') }}</option>
                        @foreach (App\Models\LeaveRequest::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($s[0]) }}</option>@endforeach</select></div>
                    @if ($tab === 'all')
                        <div class="col-6 col-md-5"><select name="user_id" class="form-select" aria-label="{{ __('Nhân viên') }}"><option value="">{{ __('Mọi nhân viên') }}</option>
                            @foreach ($users as $id => $name)<option value="{{ $id }}" @selected((int) request('user_id') === (int) $id)>{{ $name }}</option>@endforeach</select></div>
                    @endif
                </form>
            </div>
            @if ($items->isEmpty())
                <x-empty-state icon="bi-calendar-x" :title="__('Chưa có đơn nghỉ phép')" />
            @else
                <div class="table-responsive"><table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead><tr>@if ($tab !== 'mine')<th>{{ __('Nhân viên') }}</th>@endif<th>{{ __('Thời gian') }}</th><th>{{ __('Loại') }}</th><th class="text-end">{{ __('Số ngày') }}</th><th>{{ __('Lý do') }}</th><th>{{ __('Trạng thái') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($items as $l)
                        <tr>
                            @if ($tab !== 'mine')<td class="text-nowrap"><div class="d-flex align-items-center gap-2"><x-avatar :user="$l->user" size="sm" /><span>{{ $l->user?->name }}</span></div></td>@endif
                            <td class="text-nowrap">{{ vn_date($l->start_date) }}@if (! $l->start_date->eq($l->end_date)) – {{ vn_date($l->end_date) }}@endif
                                @if ($l->part !== 'full')<div class="small text-muted">{{ label_of(App\Models\LeaveRequest::PARTS, $l->part) }}</div>@endif</td>
                            <td>{!! badge($T, $l->type) !!}</td>
                            <td class="text-end">{{ (float) $l->days }}</td>
                            <td class="small" style="min-width: 200px; max-width: 320px; white-space: normal">{{ $l->reason }}@if ($l->decision_note)<div class="text-muted"><i class="bi bi-chat-left-text me-1"></i>{{ $l->approver?->name }}: {{ $l->decision_note }}</div>@endif</td>
                            <td>{!! badge(App\Models\LeaveRequest::STATUSES, $l->status) !!}</td>
                            <td class="text-end text-nowrap">
                                @if ($l->status === 'pending' && $l->user_id === auth()->id())
                                    <form method="POST" action="{{ route('leave.cancel', $l) }}" class="d-inline" data-confirm="{{ __('Hủy đơn nghỉ này?') }}" data-confirm-button="{{ __('Hủy đơn') }}">@csrf<button class="btn btn-sm btn-light">{{ __('Hủy đơn') }}</button></form>
                                @elseif ($l->status === 'pending' && $tab !== 'mine' && $l->user_id !== auth()->id())
                                    <form method="POST" action="{{ route('leave.approve', $l) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> {{ __('Duyệt') }}</button></form>
                                    <div class="dropdown d-inline">
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">{{ __('Từ chối') }}</button>
                                        <form method="POST" action="{{ route('leave.reject', $l) }}" class="dropdown-menu dropdown-menu-end p-3" style="min-width: 260px">@csrf
                                            <label class="form-label small" for="ln{{ $l->id }}">{{ __('Lý do từ chối') }} <span class="req">*</span></label>
                                            <textarea name="decision_note" id="ln{{ $l->id }}" rows="2" class="form-control form-control-sm mb-2" required maxlength="500"></textarea>
                                            <button class="btn btn-sm btn-danger w-100">{{ __('Xác nhận từ chối') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <x-pagination-bar :paginator="$items" />
            @endif
        </div></div>

        <div class="col-12"><div class="card"><div class="card-body d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold me-1">{{ __('Ngày lễ :y', ['y' => $year]) }}:</span>
            @forelse ($holidays as $h)
                <span class="badge badge-soft-primary">{{ $h->date->format('d/m') }} · {{ $h->name }}</span>
            @empty
                <span class="small text-muted">{{ __('Chưa khai báo ngày lễ.') }}</span>
            @endforelse
            @can('payroll.manage')<a href="{{ route('holidays.index', ['year' => $year]) }}" class="btn btn-sm btn-light ms-auto">{{ __('Quản lý ngày lễ') }}</a>@endcan
        </div></div></div>
    </div>

    <div class="modal fade" id="leaveModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('leave.store') }}" class="needs-validation" novalidate>@csrf
            <div class="modal-header"><h5 class="modal-title">{{ __('Xin nghỉ phép') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
            <div class="modal-body">
                <x-select name="type" :label="__('Loại nghỉ')" :options="$T" required value="annual" />
                <div class="row">
                    <x-input name="start_date" type="date" :label="__('Từ ngày')" required :value="now()->addDay()->toDateString()" :min="now()->subDays(31)->toDateString()" col="col-6 mb-3" />
                    <x-input name="end_date" type="date" :label="__('Đến ngày')" required :value="now()->addDay()->toDateString()" :min="now()->subDays(31)->toDateString()" col="col-6 mb-3" />
                </div>
                <x-select name="part" :label="__('Buổi')" :options="App\Models\LeaveRequest::PARTS" required value="full" :help="__('Nửa ngày chỉ dùng khi nghỉ trong một ngày.')" />
                <x-textarea name="reason" :label="__('Lý do')" required maxlength="1000" rows="2" col="mb-0" />
                <div class="small text-muted mt-2">{{ __('Phép năm còn lại: :n ngày (đã trừ các đơn đang chờ).', ['n' => $remaining - (float) $balance['pending']]) }}</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Gửi đơn') }}</button></div>
        </form>
    </div></div></div>
@endsection
