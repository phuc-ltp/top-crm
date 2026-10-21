@extends('layouts.app')
@section('title', $user->name)
@section('content')
    <x-page-header :title="$user->name" :breadcrumbs="['Nhân viên' => route('employees.index'), 'Hồ sơ' => null]" :subtitle="$profile?->job_title">
        @can('users.view')<a href="{{ route('users.show', $user) }}" class="btn btn-light"><i class="bi bi-person-gear me-1"></i>{{ __('Tài khoản') }}</a>@endcan
        @can('employees.update')<a href="{{ route('employees.edit', $user->id) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Sửa hồ sơ') }}</a>@endcan
    </x-page-header>

    @if ($temp = session('temp_password'))
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" role="alert">
            <i class="bi bi-key fs-5"></i>
            <div class="flex-grow-1">{{ __('Tài khoản :email đã được tạo. Mật khẩu tạm (chỉ hiện một lần — hãy gửi cho nhân viên qua kênh an toàn):', ['email' => $temp['email']]) }}
                <code class="fs-6 user-select-all ms-1" id="tempPassword">{{ $temp['password'] }}</code></div>
            <button type="button" class="btn btn-sm btn-light" onclick="navigator.clipboard.writeText(document.getElementById('tempPassword').textContent); this.textContent = t('Đã chép')">{{ __('Chép') }}</button>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="h6 mb-0">{{ __('KPI') }} <span class="text-muted fw-normal small">{{ vn_date($from) }} → {{ vn_date($to) }}</span></h2>
        <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Kỳ') }}">
            @foreach (['month' => 'Tháng này', 'quarter' => 'Quý này', 'year' => 'Năm nay'] as $k => $l)
                <a href="{{ route('employees.show', [$user->id, 'period' => $k]) }}" class="btn {{ $period === $k ? 'btn-primary' : 'btn-light' }}">{{ __($l) }}</a>
            @endforeach
        </div>
    </div>
    @include('employees._kpi', ['k' => $kpi])

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="card mb-4"><div class="card-header">{{ __('Thông tin công việc') }}</div><div class="card-body">
                <dl class="detail-list">
                    <dt>{{ __('Mã nhân viên') }}</dt><dd>{{ $profile?->employee_code ?? '—' }}</dd>
                    <dt>{{ __('Chức danh') }}</dt><dd>{{ $profile?->job_title ?? '—' }}</dd>
                    <dt>{{ __('Phòng ban') }}</dt><dd>{{ $user->department?->name ?? '—' }}</dd>
                    <dt>{{ __('Chi nhánh') }}</dt><dd>{{ $profile?->branch?->name ?? '—' }}</dd>
                    <dt>{{ __('Quản lý trực tiếp') }}</dt><dd>@if ($profile?->manager)<a href="{{ route('employees.show', $profile->manager_id) }}">{{ $profile->manager->name }}</a>@else — @endif</dd>
                    <dt>{{ __('Ngày vào làm') }}</dt><dd>{{ vn_date($profile?->hire_date) }}</dd>
                    <dt>{{ __('Trạng thái làm việc') }}</dt><dd>{!! badge(App\Models\EmployeeProfile::STATUSES, $profile?->employment_status ?? 'active') !!}</dd>
                    <dt>Email</dt><dd><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></dd>
                    <dt>{{ __('Điện thoại công việc') }}</dt><dd>{{ $profile?->work_phone ?? $user->phone ?? '—' }}</dd>
                    @if ($hourlyRate !== null)<dt>{{ __('Lương theo giờ') }}</dt><dd>{{ money($hourlyRate) }}/h <span class="small text-muted">· {{ $rateSource }}@if ($profile?->payGrade) ({{ $profile->payGrade->name }})@endif</span></dd>@endif
                </dl>
            </div></div>
            @if ($showSensitive)
                <div class="card mb-4">
                    <div class="card-header"><span><i class="bi bi-shield-lock me-1"></i>{{ __('Thông tin cá nhân (nhạy cảm)') }}</span></div>
                    <div class="card-body">
                        <dl class="detail-list">
                            <dt>{{ __('Ngày sinh') }}</dt><dd>{{ $profile?->date_of_birth ? vn_date(\Illuminate\Support\Carbon::parse($profile->date_of_birth)) : '—' }}</dd>
                            <dt>{{ __('Điện thoại cá nhân') }}</dt><dd>{{ $profile?->personal_phone ?? '—' }}</dd>
                            <dt>{{ __('Email cá nhân') }}</dt><dd>{{ $profile?->personal_email ?? '—' }}</dd>
                            <dt>{{ __('Địa chỉ') }}</dt><dd>{{ $profile?->home_address ?? '—' }}</dd>
                            <dt>{{ __('Số CCCD / hộ chiếu') }}</dt><dd>{{ $profile?->id_number ?? '—' }}</dd>
                            <dt>{{ __('Liên hệ khẩn cấp') }}</dt><dd>{{ $profile?->emergency_contact ?? '—' }}</dd>
                            <dt>{{ __('Ghi chú nhân sự') }}</dt><dd style="white-space: pre-line">{{ $profile?->hr_notes ?? '—' }}</dd>
                        </dl>
                        <div class="form-text">{{ __('Chỉ người có quyền xem thông tin nhạy cảm mới thấy mục này. Dữ liệu được mã hóa trong cơ sở dữ liệu.') }}</div>
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">{{ __('Nhóm (:count)', ['count' => $user->teams->count()]) }}</div>
                <div class="card-body">
                    @forelse ($user->teams as $t)
                        <div class="d-flex justify-content-between py-1"><a href="{{ route('teams.show', $t) }}" class="small">{{ $t->name }}</a>
                            @if ($t->leader_id === $user->id)<span class="badge badge-soft-orange">{{ __('Trưởng nhóm') }}</span>@endif</div>
                    @empty
                        <div class="small text-muted">{{ __('Chưa thuộc nhóm nào.') }}</div>
                    @endforelse
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Cấp dưới trực tiếp (:count)', ['count' => $reports->count()]) }}</div>
                <div class="card-body">
                    @forelse ($reports as $r)
                        <div class="d-flex align-items-center gap-2 py-1"><x-avatar :user="$r" size="sm" /><a href="{{ route('employees.show', $r->id) }}" class="small">{{ $r->name }}</a></div>
                    @empty
                        <div class="small text-muted">{{ __('Không có.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
