@extends('layouts.app')
@section('title', __('Danh bạ nhân viên'))
@section('content')
    <x-page-header :title="__('Danh bạ nhân viên')" :breadcrumbs="['Nhân viên' => null]" :subtitle="__('Chức danh, phòng ban, nhóm, chi nhánh và liên hệ công việc')">
        <a href="{{ route('employees.chart') }}" class="btn btn-light"><i class="bi bi-diagram-3 me-1"></i>{{ __('Sơ đồ tổ chức') }}</a>
        @can('employees.export')<a href="{{ route('employees.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>{{ __('Xuất Excel') }}</a>@endcan
    </x-page-header>

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
