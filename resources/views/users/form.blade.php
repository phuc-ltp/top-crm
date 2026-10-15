@extends('layouts.app')
@section('title', $user->exists ? __('Sửa tài khoản') : __('Tạo tài khoản'))
@section('content')
    <x-page-header :title="$user->exists ? 'Sửa: '.$user->name : 'Tạo tài khoản nhân viên'" :breadcrumbs="['Người dùng' => route('users.index'), ($user->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]" />
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="needs-validation" novalidate>
        @csrf @if ($user->exists) @method('PUT') @endif
        <div class="card" style="max-width: 900px"><div class="card-body"><div class="row">
            <x-input name="name" :label="__('Họ tên')" :value="$user->name" required maxlength="255" col="col-md-6 mb-3" />
            <x-input name="email" type="email" :label="__('Email đăng nhập')" :value="$user->email" required maxlength="255" col="col-md-6 mb-3" autocomplete="off" />
            <x-input name="username" :label="__('Tên đăng nhập (không bắt buộc)')" :value="$user->username" maxlength="50" pattern="[A-Za-z0-9._\-]{3,50}" col="col-md-6 mb-3" autocomplete="off" :help="__('Có thể đăng nhập bằng email hoặc tên này. Chữ không dấu, số, . _ -')" />
            <x-input name="phone" :label="__('Số điện thoại')" :value="$user->phone" maxlength="30" col="col-md-6 mb-3" />
            <x-input name="position" :label="__('Chức vụ')" :value="$user->position" maxlength="100" col="col-md-6 mb-3" />
            <x-select name="department_id" :label="__('Phòng ban')" :options="$departments" :value="$user->department_id" :placeholder="__('— Chọn —')" col="col-md-4 mb-3" />
            <x-select name="role" :label="__('Vai trò')" :options="$roles" :value="$user->getRoleNames()->first()" required :placeholder="__('— Chọn vai trò —')" col="col-md-4 mb-3" />
            <x-select name="status" :label="__('Trạng thái')" :options="App\Models\User::STATUSES" :value="$user->status" required col="col-md-4 mb-3" />
            @unless ($user->exists)
                <x-input name="password" type="password" :label="__('Mật khẩu ban đầu')" required minlength="8" col="col-md-6 mb-3" autocomplete="new-password" :help="__('Tối thiểu 8 ký tự gồm chữ và số. Nhân viên nên đổi sau lần đăng nhập đầu.')" />
                <x-input name="password_confirmation" type="password" :label="__('Nhập lại mật khẩu')" required minlength="8" col="col-md-6 mb-3" autocomplete="new-password" />
            @endunless
        </div></div>
        <div class="card-footer bg-transparent d-flex justify-content-end gap-2 py-3">
            <a href="{{ $user->exists ? route('users.show', $user) : route('users.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Lưu') }}</button>
        </div></div>
    </form>
@endsection
