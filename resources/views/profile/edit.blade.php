@extends('layouts.app')
@section('title', __('Hồ sơ cá nhân'))
@section('content')
    <x-page-header :title="__('Hồ sơ cá nhân')" :breadcrumbs="['Hồ sơ cá nhân' => null]" />

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card text-center">
                <div class="card-body py-4">
                    <x-avatar :user="$user" size="lg" class="mb-3" />
                    <h5 class="fw-bold mb-0">{{ $user->name }}</h5>
                    <div class="text-muted small mb-2">{{ $user->email }}</div>
                    @foreach ($user->roles as $role)<span class="badge badge-soft-orange">{{ __($role->name) }}</span>@endforeach
                    <hr>
                    <dl class="detail-list text-start small">
                        <dt>{{ __('Phòng ban') }}</dt><dd>{{ $user->department?->name ?? '—' }}</dd>
                        <dt>{{ __('Chức vụ') }}</dt><dd>{{ $user->position ?? '—' }}</dd>
                        <dt>{{ __('Đăng nhập gần nhất') }}</dt><dd>{{ vn_date($user->last_login_at, true) }}</dd>
                        <dt>{{ __('IP gần nhất') }}</dt><dd>{{ $user->last_login_ip ?? '—' }}</dd>
                        <dt>{{ __('Xác thực 2 lớp') }}</dt><dd>{!! $user->hasTwoFactorEnabled() ? '<span class="badge badge-soft-success">'.e(__('Đang bật')).'</span>' : '<span class="badge badge-soft-secondary">'.e(__('Chưa bật')).'</span>' !!}</dd>
                        <dt>{{ __('Email') }}</dt><dd>{!! $user->hasVerifiedEmail() ? '<span class="badge badge-soft-success">'.e(__('Đã xác minh')).'</span>' : '<span class="badge badge-soft-warning">'.e(__('Chưa xác minh')).'</span>' !!}</dd>
                    </dl>
                    <a href="{{ route('profile.security') }}" class="btn btn-light w-100"><i class="bi bi-shield-lock me-1"></i>{{ __('Bảo mật tài khoản') }}</a>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><span><i class="bi bi-person me-2 text-orange"></i>{{ __('Thông tin cá nhân') }}</span></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
                        @csrf @method('PUT')
                        <div class="row">
                            <x-input name="name" :label="__('Họ và tên')" :value="$user->name" required col="col-md-6 mb-3" maxlength="255" />
                            <x-input name="email_display" :label="__('Email (không thể đổi)')" :value="$user->email" disabled col="col-md-6 mb-3" />
                            <x-input name="phone" :label="__('Số điện thoại')" :value="$user->phone" col="col-md-6 mb-3" maxlength="30" pattern="[0-9+\-\s().]{6,30}" />
                            <x-input name="position" :label="__('Chức vụ')" :value="$user->position" col="col-md-6 mb-3" maxlength="100" />
                            <x-input name="avatar" type="file" :label="__('Ảnh đại diện')" accept="image/png,image/jpeg,image/webp" col="col-md-8 mb-3" :help="__('JPG, PNG, WEBP — tối đa 2MB.')" />
                            @if ($user->avatar)
                                <div class="col-md-4 mb-3 d-flex align-items-end">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="remove_avatar"><label class="form-check-label" for="remove_avatar">{{ __('Xóa ảnh hiện tại') }}</label></div>
                                </div>
                            @endif
                        </div>
                        <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Lưu thay đổi') }}</button>
                    </form>
                </div>
            </div>

            <div class="card" id="password">
                <div class="card-header"><span><i class="bi bi-key me-2 text-orange"></i>{{ __('Đổi mật khẩu') }}</span></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}" class="needs-validation" novalidate>
                        @csrf @method('PUT')
                        <div class="row">
                            <x-input name="current_password" type="password" :label="__('Mật khẩu hiện tại')" required col="col-md-12 mb-3" autocomplete="current-password" />
                            <x-input name="password" type="password" :label="__('Mật khẩu mới')" required minlength="10" maxlength="128" col="col-md-6 mb-3" autocomplete="new-password" :help="__('Mật khẩu tối thiểu 10 ký tự, có chữ hoa, chữ thường và chữ số.')" />
                            <x-input name="password_confirmation" type="password" :label="__('Nhập lại mật khẩu mới')" required minlength="10" col="col-md-6 mb-3" autocomplete="new-password" />
                        </div>
                        <button class="btn btn-primary"><i class="bi bi-shield-lock me-1"></i>{{ __('Đổi mật khẩu') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
