@extends('layouts.app')
@section('title', __('Vai trò & quyền'))
@section('content')
    <x-page-header :title="__('Vai trò & quyền')" :breadcrumbs="['Vai trò & quyền' => null]" :subtitle="__('Phân quyền theo hành động: Xem, Tạo, Sửa, Xóa, Xuất, Phân công, Duyệt, Quản trị')">
        <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo vai trò') }}</a>
    </x-page-header>
    <div class="alert alert-light border small mb-3"><i class="bi bi-diagram-3 me-1 text-orange"></i>{{ __('Cấp bậc: chỉ quản lý được tài khoản, cấp vai trò và chỉnh quyền cho cấp THẤP HƠN mình. Chủ sở hữu quản lý mọi cấp; Quản trị viên không tạo thêm hay khóa Quản trị viên khác.') }}</div>
    <div class="row g-3">
        @foreach ($roles as $role)
            @php($lv = App\Support\PermissionCatalog::level($role->name))
            @if ($loop->first || App\Support\PermissionCatalog::level($roles[$loop->index - 1]->name) !== $lv)
                <div class="col-12 mt-3"><h6 class="text-uppercase small fw-bold text-muted mb-0">{{ __('Cấp :n', ['n' => $lv]) }} · {{ __(App\Support\PermissionCatalog::levels()[$lv] ?? '') }}</h6></div>
            @endif
            <div class="col-md-6 col-xl-4">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><div class="fw-bold fs-6"><i class="bi bi-shield-lock text-orange me-1"></i>{{ __($role->name) }}</div>
                            <div class="small text-muted">{{ __(':count người dùng', ['count' => $role->users_count]) }} · {{ in_array($role->name, App\Support\PermissionCatalog::PROTECTED_ROLES, true) ? __('Toàn quyền') : __(':count quyền', ['count' => $role->permissions_count]) }}</div></div>
                        @if (in_array($role->name, $defaults))<span class="badge badge-soft-secondary">{{ __('Mặc định') }}</span>@endif
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        @if ($editable($role))<a href="{{ route('roles.edit', $role) }}" class="btn btn-soft-primary btn-sm"><i class="bi bi-sliders me-1"></i>{{ __('Chỉnh quyền') }}</a>@endif
                        <a href="{{ route('users.index', ['role' => $role->name]) }}" class="btn btn-light btn-sm">{{ __('Xem người dùng') }}</a>
                        @if (! in_array($role->name, $defaults))<x-delete-button :action="route('roles.destroy', $role)" :message="__('Xóa vai trò :name?', ['name' => $role->name])" />@endif
                    </div>
                </div></div>
            </div>
        @endforeach
    </div>
@endsection
