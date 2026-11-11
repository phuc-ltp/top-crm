@extends('layouts.auth')
@section('title', __('Đã gửi yêu cầu'))
@section('content')
    @include('auth._card-brand')
    <div class="text-center mb-2"><i class="bi bi-hourglass-split text-orange" style="font-size: 2.6rem" aria-hidden="true"></i></div>
    <h2>{{ $already ? __('Yêu cầu đang chờ duyệt') : __('Đã gửi yêu cầu tham gia') }}</h2>
    <p class="sub">{{ $already
        ? __('Email :email đã gửi yêu cầu trước đó và đang chờ quản trị viên duyệt — không cần gửi lại.', ['email' => $maskedEmail])
        : __('Email :email đã được xác thực. Quản trị viên sẽ xem và duyệt yêu cầu của bạn.', ['email' => $maskedEmail]) }}</p>
    <div class="ta-alert mb-3"><i class="bi bi-envelope-paper"></i> {{ __('Khi được duyệt, bạn nhận email thông báo và đăng nhập bằng email + mật khẩu vừa đặt. Muốn nhanh hơn, hãy nhắn quản lý của bạn vào duyệt.') }}</div>
    <a href="{{ route('login') }}" class="ta-btn text-decoration-none"><i class="bi bi-box-arrow-in-right"></i> {{ __('Về trang đăng nhập') }}</a>
@endsection
