@extends('layouts.auth')
@section('title', 'Quên mật khẩu')
@section('content')
    <a href="{{ route('login') }}" class="ta-back"><i class="bi bi-arrow-left"></i> Quay lại đăng nhập</a>
    @include('auth._card-brand')
    <h2>Quên mật khẩu</h2>
    <p class="sub">Nhập email tài khoản, hệ thống sẽ gửi liên kết đặt lại mật khẩu.</p>
    <form method="POST" action="{{ route('password.email') }}" class="needs-validation" novalidate>
        @csrf
        <x-auth-field name="email" type="email" icon="bi-envelope" placeholder="Email" required autofocus autocomplete="email" />
        <button type="submit" class="ta-btn" data-loading-text="Đang gửi..."><i class="bi bi-send"></i> Gửi liên kết đặt lại</button>
    </form>
    @if (config('mail.default') === 'log')
        <div class="ta-alert warn mt-4 mb-0">
            <i class="bi bi-info-circle"></i> Máy chủ chưa cấu hình gửi email. Hãy liên hệ quản trị viên để được đặt lại mật khẩu trong mục <strong>Người dùng</strong>.
        </div>
    @endif
@endsection
