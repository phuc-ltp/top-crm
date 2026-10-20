@extends('layouts.app')
@section('title', __('Sửa hồ sơ nhân viên'))
@section('content')
    <x-page-header :title="__('Sửa hồ sơ: :name', ['name' => $user->name])" :breadcrumbs="['Nhân viên' => route('employees.index'), $user->name => route('employees.show', $user->id), 'Sửa hồ sơ' => null]" />
    <form method="POST" action="{{ route('employees.update', $user->id) }}" class="needs-validation" novalidate>
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-{{ $editSensitive ? '6' : '8' }}">
                <div class="card mb-4"><div class="card-header">{{ __('Thông tin công việc') }}</div><div class="card-body">
                    <div class="row">
                        <x-input name="employee_code" :label="__('Mã nhân viên')" :value="$profile->employee_code" maxlength="30" pattern="[A-Za-z0-9_\-]+" col="col-md-5 mb-3" />
                        <x-input name="job_title" :label="__('Chức danh')" :value="$profile->job_title" maxlength="150" col="col-md-7 mb-3" />
                    </div>
                    <x-select name="manager_id" :label="__('Quản lý trực tiếp')" :options="$managers" :value="$profile->manager_id" :placeholder="__('— Không có —')" :translate="false" />
                    <x-select name="branch_id" :label="__('Chi nhánh')" :options="$branches" :value="$profile->branch_id" :placeholder="__('— Không có —')" :translate="false" />
                    <div class="row">
                        <x-input name="hire_date" type="date" :label="__('Ngày vào làm')" :value="optional($profile->hire_date)->format('Y-m-d')" col="col-md-6 mb-3" />
                        <x-select name="employment_status" :label="__('Trạng thái làm việc')" :options="App\Models\EmployeeProfile::STATUSES" :value="$profile->employment_status" required col="col-md-6 mb-3" />
                    </div>
                    <x-input name="work_phone" :label="__('Điện thoại công việc')" :value="$profile->work_phone" maxlength="30" />
                    <div class="form-text">{{ __('Phòng ban và vai trò được chỉnh ở trang Người dùng.') }}</div>
                </div></div>
            </div>
            @if ($editSensitive)
                <div class="col-lg-6">
                    <div class="card mb-4"><div class="card-header"><span><i class="bi bi-shield-lock me-1"></i>{{ __('Thông tin cá nhân (nhạy cảm)') }}</span></div><div class="card-body">
                        <div class="row">
                            <x-input name="date_of_birth" type="date" :label="__('Ngày sinh')" :value="$profile->date_of_birth" col="col-md-6 mb-3" />
                            <x-input name="id_number" :label="__('Số CCCD / hộ chiếu')" :value="$profile->id_number" maxlength="30" col="col-md-6 mb-3" />
                        </div>
                        <div class="row">
                            <x-input name="personal_phone" :label="__('Điện thoại cá nhân')" :value="$profile->personal_phone" maxlength="30" col="col-md-6 mb-3" />
                            <x-input name="personal_email" type="email" :label="__('Email cá nhân')" :value="$profile->personal_email" maxlength="255" col="col-md-6 mb-3" />
                        </div>
                        <x-input name="home_address" :label="__('Địa chỉ')" :value="$profile->home_address" maxlength="500" />
                        <x-input name="emergency_contact" :label="__('Liên hệ khẩn cấp')" :value="$profile->emergency_contact" maxlength="255" />
                        <x-textarea name="hr_notes" :label="__('Ghi chú nhân sự')" :value="$profile->hr_notes" rows="3" maxlength="5000" col="mb-0" />
                        <div class="form-text">{{ __('Các trường này được mã hóa và chỉ người có quyền xem thông tin nhạy cảm mới thấy.') }}</div>
                    </div></div>
                </div>
            @endif
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Lưu hồ sơ') }}</button>
            <a href="{{ route('employees.show', $user->id) }}" class="btn btn-light">{{ __('Hủy') }}</a>
        </div>
    </form>
@endsection
