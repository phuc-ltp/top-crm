@extends('layouts.app')
@section('title', __('Bảng công tháng'))
@section('content')
    @php($fmtH = fn ($m) => round($m / 60, 1))
    @php($codes = ['present' => ['✓', 'text-success'], 'late' => ['T', 'text-warning-emphasis'], 'incomplete' => ['?', 'text-info'], 'absent' => ['—', 'text-muted']])
    <x-page-header :title="__('Bảng công tháng :m/:y', ['m' => $month->month, 'y' => $month->year])" :breadcrumbs="['Chấm công' => route('attendance.index'), 'Bảng công' => null]"
                   :subtitle="__('✓ đủ công · T trễ / về sớm · ? chưa chấm ra · P nghỉ phép · L ngày lễ · ô trống: không có công · ô xám: ngày nghỉ')">
        <form method="GET" class="d-flex gap-2" data-auto-submit><input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control" aria-label="{{ __('Tháng') }}"></form>
        @can('payroll.manage')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#manualModal"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm công thủ công') }}</button>@endcan
    </x-page-header>

    <div class="card"><div class="table-responsive"><table class="table table-bordered table-sm mb-0 align-middle attendance-board">
        <thead><tr><th class="sticky-col">{{ __('Nhân viên') }}</th>
            @foreach ($days as $d)<th class="text-center small {{ $d['work'] && ! $holidays->has($d['date']->toDateString()) ? '' : 'bg-body-tertiary text-muted' }}">{{ $d['date']->day }}</th>@endforeach
            <th class="text-end small">{{ __('Ngày công') }}</th><th class="text-end small">{{ __('Giờ') }}</th><th class="text-end small">{{ __('Trễ (phút)') }}</th><th class="text-end small">{{ __('Phép (ngày)') }}</th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            @php($mine = $records->get($u->id, collect()))
            @php($leaveDays = 0)
            <tr>
                <td class="sticky-col small fw-semibold text-nowrap"><a href="{{ route('employees.show', $u->id) }}">{{ $u->name }}</a></td>
                @foreach ($days as $d)
                    @php($r = $mine->get($d['date']->toDateString()))
                    @php($hol = $holidays->get($d['date']->toDateString()))
                    @php($lv = $hol || ! $d['work'] ? null : $leaves->get($u->id, collect())->first(fn ($l) => $l->start_date->lte($d['date']) && $l->end_date->gte($d['date'])))
                    @php($leaveDays += $lv ? ($lv->part === 'full' ? 1 : 0.5) : 0)
                    <td class="text-center small {{ $d['work'] && ! $hol ? '' : 'bg-body-tertiary' }}">
                        @if ($hol && ! $r)<span class="text-primary fw-semibold" title="{{ $hol->name }}">L</span>@elseif ($lv && ! $r)<span class="text-info fw-semibold" title="{{ label_of(App\Models\LeaveRequest::TYPES, $lv->type) }}">P{{ $lv->part === 'full' ? '' : '½' }}</span>@endif
                        @if ($r)<a href="{{ route('attendance.records.show', $r) }}" class="{{ $codes[$r->status][1] }} fw-semibold text-decoration-none" title="{{ vn_date($r->work_date) }} — {{ label_of(App\Models\AttendanceRecord::STATUSES, $r->status) }}">{{ $codes[$r->status][0] }}@if ($r->needs_review)<sup class="text-danger">!</sup>@endif</a>@endif
                    </td>
                @endforeach
                <td class="text-end small">{{ $mine->whereNotNull('check_in_at')->count() }}</td>
                <td class="text-end small">{{ $fmtH($mine->sum('worked_minutes')) }}</td>
                <td class="text-end small {{ $mine->sum('late_minutes') ? 'text-danger' : '' }}">{{ $mine->sum('late_minutes') }}</td>
                <td class="text-end small">{{ $leaveDays ?: '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="card-body small text-muted border-top"><sup class="text-danger">!</sup> {{ __('chấm công ngoài khu vực văn phòng — cần xem lại. Bấm vào ô để xem ảnh, vị trí và điều chỉnh.') }}</div></div>

    @can('payroll.manage')
        <div class="modal fade" id="manualModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('attendance.records.store') }}" class="needs-validation" novalidate>@csrf
                <div class="modal-header"><h5 class="modal-title">{{ __('Thêm công thủ công') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
                <div class="modal-body">
                    <x-select name="user_id" :label="__('Nhân viên')" :options="$users->pluck('name', 'id')->all()" required :translate="false" />
                    <x-input name="work_date" type="date" :label="__('Ngày')" required :max="now()->toDateString()" />
                    <div class="row"><x-input name="check_in_time" type="time" :label="__('Giờ vào')" required col="col-6 mb-3" /><x-input name="check_out_time" type="time" :label="__('Giờ ra')" required col="col-6 mb-3" /></div>
                    <x-input name="reason" :label="__('Lý do điều chỉnh')" required maxlength="500" col="mb-0" />
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Lưu') }}</button></div>
            </form>
        </div></div></div>
    @endcan
@endsection
