@extends('layouts.app')
@section('title', __('Tăng ca'))
@section('content')
    @php($L = App\Models\OvertimeRequest::LEVELS)
    <x-page-header :title="__('Tăng ca tháng :m/:y', ['m' => $month->month, 'y' => $month->year])" :breadcrumbs="['Chấm công' => route('attendance.index'), 'Tăng ca' => null]"
                   :subtitle="__('Khung tăng ca :a–:b. Đơn giá theo mức độ công việc, không thấp hơn 150% lương giờ (Điều 98 BLLĐ 2019), chốt khi được duyệt.', ['a' => $window[0], 'b' => $window[1]])">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#otModal"><i class="bi bi-plus-lg me-1"></i>{{ __('Đề nghị tăng ca') }}</button>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-clock-history" tone="blue" :label="__('Giờ đã duyệt')" :value="(float) $totals['hours']" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-cash-coin" tone="green" :label="__('Tiền tăng ca đã duyệt')" :value="money($totals['amount'])" /></div>
        <div class="col-12 col-lg-6"><div class="card h-100"><div class="card-body">
            <div class="small text-muted mb-2">{{ __('Đơn giá tăng ca tối thiểu theo mức độ') }}</div>
            <div class="d-flex flex-wrap gap-3">
                @foreach ($levelRates as $k => $rate)<div>{!! badge($L, $k) !!} <span class="fw-semibold">{{ money($rate) }}/h</span></div>@endforeach
            </div>
        </div></div></div>
    </div>

    <ul class="nav nav-tabs-crm mb-3">
        <li class="nav-item"><a class="nav-link {{ $tab === 'mine' ? 'active' : '' }}" href="{{ route('overtime.index', ['tab' => 'mine', 'month' => $month->format('Y-m')]) }}">{{ __('Của tôi') }}</a></li>
        @if ($canApprove)<li class="nav-item"><a class="nav-link {{ $tab === 'approve' ? 'active' : '' }}" href="{{ route('overtime.index', ['tab' => 'approve', 'month' => $month->format('Y-m')]) }}">{{ __('Chờ tôi duyệt') }} @if ($pendingForMe)<span class="badge bg-danger ms-1">{{ $pendingForMe }}</span>@endif</a></li>@endif
        @if ($canAll)<li class="nav-item"><a class="nav-link {{ $tab === 'all' ? 'active' : '' }}" href="{{ route('overtime.index', ['tab' => 'all', 'month' => $month->format('Y-m')]) }}">{{ __('Tất cả') }}</a></li>@endif
    </ul>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2" data-auto-submit>
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col-6 col-md-3"><input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control" aria-label="{{ __('Tháng') }}"></div>
                <div class="col-6 col-md-3"><select name="status" class="form-select" aria-label="{{ __('Trạng thái') }}"><option value="">{{ __('Mọi trạng thái') }}</option>
                    @foreach (App\Models\OvertimeRequest::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($s[0]) }}</option>@endforeach</select></div>
                @if ($tab === 'all')
                    <div class="col-12 col-md-4"><select name="user_id" class="form-select" aria-label="{{ __('Nhân viên') }}"><option value="">{{ __('Mọi nhân viên') }}</option>
                        @foreach ($users as $id => $name)<option value="{{ $id }}" @selected((int) request('user_id') === (int) $id)>{{ $name }}</option>@endforeach</select></div>
                @endif
            </form>
        </div>
        @if ($items->isEmpty())
            <x-empty-state icon="bi-moon-stars" :title="__('Chưa có đơn tăng ca trong tháng này')" />
        @else
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr>
                    @if ($tab !== 'mine')<th>{{ __('Nhân viên') }}</th>@endif
                    <th>{{ __('Ngày') }}</th><th>{{ __('Giờ') }}</th><th>{{ __('Mức độ') }}</th><th>{{ __('Lý do') }}</th><th>{{ __('Trạng thái') }}</th><th class="text-end">{{ __('Thành tiền') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($items as $ot)
                    <tr>
                        @if ($tab !== 'mine')<td class="text-nowrap"><div class="d-flex align-items-center gap-2"><x-avatar :user="$ot->user" size="sm" /><span>{{ $ot->user?->name }}</span></div></td>@endif
                        <td class="text-nowrap">{{ vn_date($ot->work_date) }}</td>
                        <td class="text-nowrap">{{ substr($ot->start_time, 0, 5) }}–{{ substr($ot->end_time, 0, 5) }} <span class="text-muted small">({{ (float) $ot->hours }}h)</span></td>
                        <td>{!! badge($L, $ot->level) !!}</td>
                        <td class="small" style="max-width: 280px">{{ $ot->reason }}
                            @if ($ot->decision_note)<div class="text-muted"><i class="bi bi-chat-left-text me-1"></i>{{ $ot->approver?->name }}: {{ $ot->decision_note }}</div>@endif</td>
                        <td>{!! badge(App\Models\OvertimeRequest::STATUSES, $ot->status) !!}</td>
                        <td class="text-end text-nowrap">@if ($ot->amount !== null){{ money($ot->amount) }}<div class="text-muted small">{{ money($ot->rate) }}/h</div>@else — @endif</td>
                        <td class="text-end text-nowrap">
                            @if ($ot->status === 'pending' && $ot->user_id === auth()->id())
                                <form method="POST" action="{{ route('overtime.cancel', $ot) }}" class="d-inline" data-confirm="{{ __('Hủy đơn tăng ca này?') }}" data-confirm-button="{{ __('Hủy đơn') }}">@csrf<button class="btn btn-sm btn-light">{{ __('Hủy đơn') }}</button></form>
                            @elseif ($ot->status === 'pending' && $tab !== 'mine' && $ot->user_id !== auth()->id())
                                <form method="POST" action="{{ route('overtime.approve', $ot) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> {{ __('Duyệt') }}</button></form>
                                <div class="dropdown d-inline">
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">{{ __('Từ chối') }}</button>
                                    <form method="POST" action="{{ route('overtime.reject', $ot) }}" class="dropdown-menu dropdown-menu-end p-3" style="min-width: 260px">@csrf
                                        <label class="form-label small" for="note{{ $ot->id }}">{{ __('Lý do từ chối') }} <span class="req">*</span></label>
                                        <textarea name="decision_note" id="note{{ $ot->id }}" rows="2" class="form-control form-control-sm mb-2" required maxlength="500"></textarea>
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
    </div>

    <div class="modal fade" id="otModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('overtime.store') }}" class="needs-validation" novalidate>@csrf
            <div class="modal-header"><h5 class="modal-title">{{ __('Đề nghị tăng ca') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
            <div class="modal-body">
                <x-input name="work_date" type="date" :label="__('Ngày tăng ca')" required :value="now()->toDateString()" :min="now()->subDays(31)->toDateString()" :max="now()->addDays(60)->toDateString()" />
                <div class="row">
                    <x-input name="start_time" type="time" :label="__('Từ')" required :value="$window[0]" :min="$window[0]" :max="$window[1]" col="col-6 mb-3" />
                    <x-input name="end_time" type="time" :label="__('Đến')" required :value="$window[1]" :min="$window[0]" :max="$window[1]" col="col-6 mb-3" />
                </div>
                <x-select name="level" :label="__('Mức độ công việc')" required :options="collect($L)->map(fn ($v, $k) => __($v[0]).' — '.money($levelRates[$k]).'/h')->all()" :translate="false" value="medium" />
                <x-textarea name="reason" :label="__('Lý do / nội dung công việc')" required maxlength="1000" col="mb-0" />
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Gửi đề nghị') }}</button></div>
        </form>
    </div></div></div>
@endsection
