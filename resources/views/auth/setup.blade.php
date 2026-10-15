@extends('layouts.auth')
@section('title', __('Khởi tạo hệ thống'))
@section('content')
    @include('auth._card-brand')
    <h2>{{ __('Tạo tài khoản quản trị') }}</h2>
    <p class="sub">{{ __('Thiết lập lần đầu — tự đặt mật khẩu cho tài khoản quản trị cao nhất.') }}</p>
    <div class="ta-alert info ta-optional"><i class="bi bi-shield-lock me-1"></i> {{ __('Trang này tự khóa vĩnh viễn ngay sau khi tài khoản Super Admin được tạo.') }}</div>

    <form method="POST" action="{{ route('setup.store') }}" class="needs-validation" novalidate>
        @csrf
        <x-auth-field name="name" icon="bi-person" :placeholder="__('Họ và tên')" required autofocus maxlength="255" />
        <x-auth-field name="email" type="email" icon="bi-envelope" placeholder="Email" required autocomplete="email" maxlength="255" />
        <x-auth-field name="username" icon="bi-at" :placeholder="__('Tên đăng nhập (không bắt buộc)')" autocomplete="username" maxlength="50" pattern="[A-Za-z0-9._\-]{3,50}" />
        <div class="ta-row-2">
            <x-auth-field name="password" type="password" icon="bi-lock" :placeholder="__('Mật khẩu')" required minlength="10" autocomplete="new-password" />
            <x-auth-field name="password_confirmation" type="password" icon="bi-lock" :placeholder="__('Nhập lại')" required minlength="10" autocomplete="new-password" />
        </div>
        <div class="ta-help">{{ __('Mật khẩu tối thiểu 10 ký tự, có chữ hoa, chữ thường và chữ số.') }}</div>
        <button type="submit" class="ta-btn mt-2" data-loading-text="{{ __('Đang tạo...') }}"><i class="bi bi-shield-check"></i> {{ __('Tạo tài khoản & bắt đầu') }}</button>
    </form>
@endsection
