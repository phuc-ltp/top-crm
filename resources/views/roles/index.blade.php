@extends('layouts.app')
@section('title', 'Vai trò & quyền')
@section('content')
    <x-page-header title="Vai trò & quyền" :breadcrumbs="['Vai trò & quyền' => null]" subtitle="Phân quyền theo hành động: Xem, Tạo, Sửa, Xóa, Xuất, Phân công, Duyệt, Quản trị">
        <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tạo vai trò</a>
    </x-page-header>
    <div class="row g-3">
        @foreach ($roles as $role)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><div class="fw-bold fs-6"><i class="bi bi-shield-lock text-orange me-1"></i>{{ $role->name }}</div>
                            <div class="small text-muted">{{ $role->users_count }} người dùng · {{ $role->name === 'Super Admin' ? 'Toàn quyền' : $role->permissions_count.' quyền' }}</div></div>
                        @if (in_array($role->name, $defaults))<span class="badge badge-soft-secondary">Mặc định</span>@endif
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        @if ($role->name !== 'Super Admin')<a href="{{ route('roles.edit', $role) }}" class="btn btn-soft-primary btn-sm"><i class="bi bi-sliders me-1"></i>Chỉnh quyền</a>@endif
                        <a href="{{ route('users.index', ['role' => $role->name]) }}" class="btn btn-light btn-sm">Xem người dùng</a>
                        @if (! in_array($role->name, $defaults))<x-delete-button :action="route('roles.destroy', $role)" message="Xóa vai trò {{ $role->name }}?" />@endif
                    </div>
                </div></div>
            </div>
        @endforeach
    </div>
@endsection
