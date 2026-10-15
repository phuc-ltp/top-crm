@extends('layouts.app')
@section('title', __('Vai trò & quyền'))
@section('content')
    <x-page-header :title="__('Vai trò & quyền')" :breadcrumbs="['Vai trò & quyền' => null]" :subtitle="__('Phân quyền theo hành động: Xem, Tạo, Sửa, Xóa, Xuất, Phân công, Duyệt, Quản trị')">
        <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo vai trò') }}</a>
    </x-page-header>
    <div class="row g-3">
        @foreach ($roles as $role)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><div class="fw-bold fs-6"><i class="bi bi-shield-lock text-orange me-1"></i>{{ __($role->name) }}</div>
                            <div class="small text-muted">{{ __(':count người dùng', ['count' => $role->users_count]) }} · {{ in_array($role->name, App\Support\PermissionCatalog::PROTECTED_ROLES, true) ? __('Toàn quyền') : __(':count quyền', ['count' => $role->permissions_count]) }}</div></div>
                        @if (in_array($role->name, $defaults))<span class="badge badge-soft-secondary">{{ __('Mặc định') }}</span>@endif
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        @if (! in_array($role->name, App\Support\PermissionCatalog::PROTECTED_ROLES, true))<a href="{{ route('roles.edit', $role) }}" class="btn btn-soft-primary btn-sm"><i class="bi bi-sliders me-1"></i>{{ __('Chỉnh quyền') }}</a>@endif
                        <a href="{{ route('users.index', ['role' => $role->name]) }}" class="btn btn-light btn-sm">{{ __('Xem người dùng') }}</a>
                        @if (! in_array($role->name, $defaults))<x-delete-button :action="route('roles.destroy', $role)" :message="__('Xóa vai trò :name?', ['name' => $role->name])" />@endif
                    </div>
                </div></div>
            </div>
        @endforeach
    </div>
@endsection
