@extends('layouts.app')
@section('title', $role->exists ? __('Quyền: :name', ['name' => __($role->name)]) : __('Tạo vai trò'))
@section('content')
    @php($isDefault = in_array($role->name, App\Support\PermissionCatalog::ORG_ROLES, true))
    @php($assigned = old('permissions', $assigned))
    <x-page-header :title="$role->exists ? __('Phân quyền: :name', ['name' => __($role->name)]) : __('Tạo vai trò mới')" :breadcrumbs="['Vai trò & quyền' => route('roles.index'), ($role->exists ? $role->name : 'Tạo mới') => null]" />
    <form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}" class="needs-validation" novalidate>
        @csrf @if ($role->exists) @method('PUT') @endif
        @unless ($isDefault)
            <div class="card mb-4" style="max-width: 500px"><div class="card-body"><x-input name="name" :label="__('Tên vai trò')" :value="$role->name" required maxlength="100" col="mb-0" /></div></div>
        @endunless
        <div class="card mb-4">
            <div class="card-header"><span>{{ __('Ma trận quyền') }}</span><span class="small text-muted">{{ __('"Xem tất cả" = xem dữ liệu của mọi nhân viên; nếu không chỉ thấy dữ liệu mình phụ trách') }}</span></div>
            <div class="table-responsive">
                <table class="table permission-table table-hover">
                    <thead><tr><th>{{ __('Module') }}</th>@foreach ($labels as $action => $label)<th>{{ __($label) }}</th>@endforeach<th>{{ __('Tất cả') }}</th></tr></thead>
                    <tbody>
                    @foreach ($catalog as $module => $def)
                        <tr data-row>
                            <td class="fw-semibold">{{ __($def['label']) }}</td>
                            @foreach ($labels as $action => $label)
                                <td>@if (in_array($action, $def['actions']))
                                        <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $module }}.{{ $action }}" @checked(in_array("$module.$action", $assigned)) aria-label="{{ __($def['label']) }} - {{ __($label) }}">
                                    @else<span class="text-muted">—</span>@endif</td>
                            @endforeach
                            <td><input type="checkbox" class="form-check-input row-all" aria-label="{{ __('Chọn cả dòng') }}"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Lưu phân quyền') }}</button><a href="{{ route('roles.index') }}" class="btn btn-light">{{ __('Hủy') }}</a></div>
    </form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-row]').forEach(row => {
        const all = row.querySelector('.row-all');
        const boxes = row.querySelectorAll('input[name="permissions[]"]');
        const sync = () => all.checked = Array.from(boxes).every(b => b.checked);
        all.addEventListener('change', () => boxes.forEach(b => b.checked = all.checked));
        boxes.forEach(b => b.addEventListener('change', sync)); sync();
    });
});
</script>
@endpush
