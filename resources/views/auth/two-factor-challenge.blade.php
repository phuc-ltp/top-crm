@extends('layouts.auth')
@section('title', __('Xác thực 2 lớp'))
@section('content')
    <a href="{{ route('login') }}" class="ta-back"><i class="bi bi-arrow-left"></i> {{ __('Quay lại đăng nhập') }}</a>
    @include('auth._card-brand')
    <h2>{{ __('Xác thực 2 lớp') }}</h2>
    <p class="sub">{{ __('Mở ứng dụng xác thực trên điện thoại và nhập mã 6 số đang hiển thị.') }}</p>

    <form method="POST" action="{{ route('two-factor.verify') }}" class="needs-validation" novalidate>
        @csrf
        <x-auth-field name="code" icon="bi-shield-lock" :placeholder="__('Mã xác thực 6 số')" required autofocus
                      inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" />
        <button type="submit" class="ta-btn" data-loading-text="{{ __('Đang kiểm tra...') }}"><i class="bi bi-check2-circle"></i> {{ __('Xác nhận') }}</button>
    </form>

    <details class="mt-4" @if ($errors->has('recovery_code')) open @endif>
        <summary class="small fw-semibold" style="cursor:pointer">{{ __('Mất điện thoại? Dùng mã khôi phục') }}</summary>
        <form method="POST" action="{{ route('two-factor.verify') }}" class="needs-validation mt-3" novalidate>
            @csrf
            <x-auth-field name="recovery_code" icon="bi-key" :placeholder="__('Mã khôi phục (VD: ABCDE-23456)')" required maxlength="30" autocomplete="off" />
            <button type="submit" class="ta-btn" data-loading-text="{{ __('Đang kiểm tra...') }}"><i class="bi bi-unlock"></i> {{ __('Dùng mã khôi phục') }}</button>
        </form>
    </details>

    <p class="ta-note">{{ __('Không còn mã khôi phục? Liên hệ quản trị viên để được tắt xác thực 2 lớp.') }}</p>
@endsection
