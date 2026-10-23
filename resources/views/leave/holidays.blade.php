@extends('layouts.app')
@section('title', __('Ngày lễ & quỹ phép'))
@section('content')
    <x-page-header :title="__('Ngày lễ & quỹ phép năm :y', ['y' => $year])" :breadcrumbs="['Chấm công & lương' => route('attendance.index'), 'Ngày lễ & quỹ phép' => null]"
                   :subtitle="__('Ngày lễ không tính vào số ngày nghỉ phép; ngày lễ có lương rơi vào ngày làm việc được trả theo giờ chuẩn trong bảng lương.')">
        <form method="GET" data-auto-submit><select name="year" class="form-select" aria-label="{{ __('Năm') }}">@for ($y = now()->year + 1; $y >= now()->year - 3; $y--)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endfor</select></form>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-8"><div class="card">
            @if ($holidays->isEmpty())
                <x-empty-state icon="bi-calendar-heart" :title="__('Chưa có ngày lễ cho năm :y', ['y' => $year])" :message="__('Bấm “Thêm ngày lễ dương lịch” rồi bổ sung Tết Âm lịch, Giỗ Tổ theo thông báo của năm.')" />
            @else
                <div class="table-responsive"><table class="table align-middle mb-0">
                    <thead><tr><th>{{ __('Ngày') }}</th><th>{{ __('Tên ngày lễ') }}</th><th>{{ __('Trả lương') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($holidays as $h)
                        <tr>
                            <td class="text-nowrap">{{ vn_date($h->date) }} <span class="text-muted small">({{ __(['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'][$h->date->dayOfWeek]) }})</span></td>
                            <td>{{ $h->name }}</td>
                            <td>@if ($h->is_paid)<span class="badge badge-soft-success">{{ __('Có lương') }}</span>@else<span class="badge badge-soft-secondary">{{ __('Không lương') }}</span>@endif</td>
                            <td class="text-end"><x-delete-button :action="route('holidays.destroy', $h)" :message="__('Xóa ngày lễ này?')" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div></div>

        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body">
                <h6 class="mb-3">{{ __('Thêm ngày lễ') }}</h6>
                <form method="POST" action="{{ route('holidays.store') }}" class="needs-validation" novalidate>@csrf
                    <x-input name="date" type="date" :label="__('Ngày')" required :value="$year.'-01-01'" />
                    <x-input name="name" :label="__('Tên ngày lễ')" required maxlength="150" :placeholder="__('VD: Tết Nguyên đán (mùng 1)')" />
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_paid" value="1" id="is_paid" checked><label class="form-check-label" for="is_paid">{{ __('Nghỉ có lương') }}</label></div>
                    <button class="btn btn-primary w-100">{{ __('Thêm') }}</button>
                </form>
                <hr>
                <form method="POST" action="{{ route('holidays.fixed') }}">@csrf<input type="hidden" name="year" value="{{ $year }}">
                    <button class="btn btn-light w-100"><i class="bi bi-magic me-1"></i>{{ __('Thêm ngày lễ dương lịch :y', ['y' => $year]) }}</button></form>
                <div class="small text-muted mt-2">{{ collect($fixed)->map(fn ($f) => \Illuminate\Support\Carbon::parse($f[0])->format('d/m'))->join(', ') }}. {{ __('Tết Âm lịch, Giỗ Tổ và ngày nghỉ liền kề Quốc khánh đổi theo năm — nhập tay.') }}</div>
            </div></div>

            <div class="card"><div class="card-body">
                <h6 class="mb-3">{{ __('Quỹ phép năm mặc định') }}</h6>
                <form method="POST" action="{{ route('holidays.settings') }}" class="needs-validation" novalidate>@csrf @method('PUT')
                    <x-input name="leave_annual_days" type="number" :label="__('Số ngày phép / năm')" :value="(float) $annualDays" required min="0" max="60" step="0.5"
                             :help="__('Luật: 12 ngày (Điều 113 BLLĐ 2019), +1 ngày mỗi 5 năm làm việc — chỉnh riêng từng người trong hồ sơ nhân viên.')" />
                    <button class="btn btn-primary w-100">{{ __('Lưu') }}</button>
                </form>
            </div></div>
        </div>
    </div>
@endsection
