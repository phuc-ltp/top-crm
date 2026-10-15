@extends('layouts.auth')
@section('title', __('Đăng nhập'))
@section('content')
    @include('auth._card-brand')
    <h2>{{ __('Chào mừng trở lại') }}</h2>
    <p class="sub">{{ __('Đăng nhập để tiếp tục sử dụng :app', ['app' => $crmSettings['company_name']]) }}</p>

    <form method="POST" action="{{ route('login') }}" class="needs-validation" novalidate>
        @csrf
        <x-auth-field name="login" icon="bi-envelope" :placeholder="__('Email hoặc tên đăng nhập')" required autofocus autocomplete="username" maxlength="255" />
        <x-auth-field name="password" type="password" icon="bi-lock" :placeholder="__('Mật khẩu')" required autocomplete="current-password" maxlength="255" />

        <div class="ta-row-remember">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember', true))>
                <label class="form-check-label" for="remember">{{ __('Ghi nhớ đăng nhập') }}</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">{{ __('Quên mật khẩu?') }}</a>
        </div>

        <button type="submit" class="ta-btn" data-loading-text="{{ __('Đang đăng nhập...') }}"><i class="bi bi-box-arrow-in-right"></i> {{ __('Đăng nhập') }}</button>
    </form>

    <div class="ta-or">{{ __('Hoặc đăng nhập với') }}</div>
    <div class="ta-social">
        @foreach (['google' => 'Google', 'facebook' => 'Facebook'] as $provider => $label)
            @php($enabled = \App\Models\Setting::socialEnabled($provider))
            <a href="{{ route('social.redirect', $provider) }}" class="{{ $enabled ? '' : 'not-configured' }}"
               @unless ($enabled) title="{{ __('Quản trị viên chưa bật đăng nhập bằng :provider', ['provider' => $label]) }}" @endunless>
                @if ($provider === 'google')
                    <svg width="22" height="22" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                @else
                    <svg width="22" height="22" viewBox="0 0 48 48" aria-hidden="true"><rect width="48" height="48" rx="8" fill="#1877F2"/><path fill="#fff" d="M33 48V30h6l1-7h-7v-4.5c0-2 .6-3.5 3.5-3.5H40V8.7c-.7-.1-2.9-.3-5.5-.3-5.4 0-9.1 3.3-9.1 9.4V23H19v7h6.4v18H33z"/></svg>
                @endif
                {{ $label }}
            </a>
        @endforeach
    </div>

    <p class="ta-note">{{ __('Chưa có tài khoản? Liên hệ quản trị viên để được cấp tài khoản.') }}</p>
@endsection
