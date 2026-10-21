@extends('layouts.app')
@section('title', __('Danh bạ nhân viên'))
@section('content')
    <x-page-header :title="__('Danh bạ nhân viên')" :breadcrumbs="['Nhân viên' => null]" :subtitle="__('Chức danh, phòng ban, nhóm, chi nhánh và liên hệ công việc')">
        <a href="{{ route('employees.chart') }}" class="btn btn-light"><i class="bi bi-diagram-3 me-1"></i>{{ __('Sơ đồ tổ chức') }}</a>
        @can('employees.export')<a href="{{ route('employees.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>{{ __('Xuất Excel') }}</a>@endcan
        @can('create', App\Models\User::class)<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newEmployeeModal"><i class="bi bi-person-plus me-1"></i>{{ __('Thêm nhân viên') }}</button>@endcan
    </x-page-header>

    @can('create', App\Models\User::class)
        <div class="modal fade" id="newEmployeeModal" tabindex="-1" aria-labelledby="newEmployeeTitle" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('employees.accounts.store') }}" class="needs-validation" novalidate>@csrf
                <div class="modal-header"><h5 class="modal-title" id="newEmployeeTitle">{{ __('Thêm nhân viên nhanh') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
                <div class="modal-body">
                    <x-input name="name" :label="__('Họ tên')" required maxlength="255" />
                    <div class="row">
                        <x-input name="email" type="email" label="Email" required maxlength="255" col="col-md-7 mb-3" />
                        <x-input name="phone" :label="__('Số điện thoại')" maxlength="30" col="col-md-5 mb-3" />
                    </div>
                    <div class="row">
                        <x-select name="role" :label="__('Vai trò')" :options="$assignableRoles" value="Employee" required :translate="false" col="col-md-6 mb-3" />
                        <x-select name="department_id" :label="__('Phòng ban')" :options="$departments" :placeholder="__('— Không —')" :translate="false" col="col-md-6 mb-3" />
                    </div>
                    <x-input name="job_title" :label="__('Chức danh')" maxlength="150" />
                    <div class="small text-muted"><i class="bi bi-shield-lock me-1"></i>{{ __('Hệ thống tạo mật khẩu tạm và chỉ hiện một lần sau khi lưu. Nhân viên phải đổi mật khẩu ở lần đăng nhập đầu. Lương theo giờ lấy theo bậc mặc định của vai trò (chỉnh trong hồ sơ).') }}</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Tạo tài khoản') }}</button></div>
            </form>
        </div></div></div>
        @if ($errors->hasAny(['name', 'email', 'phone', 'role', 'department_id', 'job_title']))
            @push('scripts')<script>document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('newEmployeeModal')).show());</script>@endpush
        @endif
    @endcan

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên, email, chức danh, mã nhân viên...') }}"></div>
            <select name="department_id" class="form-select" aria-label="{{ __('Phòng ban') }}"><option value="">{{ __('Mọi phòng ban') }}</option>
                @foreach ($departments as $id => $name)<option value="{{ $id }}" @selected(request('department_id') == $id)>{{ $name }}</option>@endforeach</select>
            @if ($teams)
                <select name="team_id" class="form-select" aria-label="{{ __('Nhóm') }}"><option value="">{{ __('Mọi nhóm') }}</option>
                    @foreach ($teams as $id => $name)<option value="{{ $id }}" @selected(request('team_id') == $id)>{{ $name }}</option>@endforeach</select>
            @endif
            @if ($branches)
                <select name="branch_id" class="form-select" aria-label="{{ __('Chi nhánh') }}"><option value="">{{ __('Mọi chi nhánh') }}</option>
                    @foreach ($branches as $id => $name)<option value="{{ $id }}" @selected(request('branch_id') == $id)>{{ $name }}</option>@endforeach</select>
            @endif
            <select name="employment_status" class="form-select" aria-label="{{ __('Trạng thái làm việc') }}"><option value="">{{ __('Mọi trạng thái') }}</option>
                @foreach (App\Models\EmployeeProfile::STATUSES as $k => [$l])<option value="{{ $k }}" @selected(request('employment_status') === $k)>{{ __($l) }}</option>@endforeach</select>
            @if (request()->hasAny(['q', 'department_id', 'team_id', 'branch_id', 'employment_status']))<a href="{{ route('employees.index') }}" class="btn btn-link text-muted">{{ __('Xóa lọc') }}</a>@endif
        </form>
        @if ($employees->isEmpty())
            <x-empty-state icon="bi-person-badge" :title="__('Không có nhân viên phù hợp')" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>{{ __('Nhân viên') }}</th><th>{{ __('Chức danh') }}</th><th>{{ __('Phòng ban') }}</th><th>{{ __('Nhóm') }}</th><th>{{ __('Quản lý trực tiếp') }}</th><th>{{ __('Liên hệ') }}</th><th>{{ __('Trạng thái') }}</th></tr></thead>
                <tbody>
                @foreach ($employees as $u)
                    @php($p = $u->employeeProfile)
                    <tr>
                        <td><div class="d-flex align-items-center gap-2"><x-avatar :user="$u" size="sm" />
                            <div><a href="{{ route('employees.show', $u->id) }}" class="row-link">{{ $u->name }}</a>@if ($p?->employee_code)<div class="small text-muted">{{ $p->employee_code }}</div>@endif</div></div></td>
                        <td class="small">{{ $p?->job_title ?? '—' }}</td>
                        <td class="small">{{ $u->department?->name ?? '—' }}</td>
                        <td class="small">{{ $u->teams->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="small">{{ $p?->manager?->name ?? '—' }}</td>
                        <td class="small"><a href="mailto:{{ $u->email }}">{{ $u->email }}</a>@if ($p?->work_phone)<div>{{ $p->work_phone }}</div>@endif</td>
                        <td>{!! badge(App\Models\EmployeeProfile::STATUSES, $p?->employment_status ?? 'active') !!}
                            @if ($u->organizations->first()?->pivot?->status === 'suspended')<span class="badge badge-soft-danger">{{ __('Tạm ngưng') }}</span>@endif</td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$employees" />
        @endif
    </div>
@endsection
