@extends('layouts.auth')
@section('title', __('Đăng ký tài khoản'))
@section('content')
    @include('auth._card-brand')
    @if (! $usable)
        <h2>{{ __('Link mời không còn hiệu lực') }}</h2>
        <div class="ta-alert warn"><i class="bi bi-exclamation-triangle"></i> {{ __('Link đã hết hạn, đã hết lượt đăng ký hoặc đã bị thu hồi. Vui lòng xin link mới từ người quản lý.') }}</div>
        <a href="{{ route('login') }}" class="ta-btn mt-3 text-decoration-none"><i class="bi bi-box-arrow-in-right"></i> {{ __('Đến trang đăng nhập') }}</a>
    @else
        <h2>{{ __('Đăng ký tài khoản') }}</h2>
        <p class="sub">{{ $company ? __('Bạn được mời tham gia :company với vai trò :role.', ['company' => $company, 'role' => __($invitation->role)]) : __('Bạn được mời tham gia với vai trò :role.', ['role' => __($invitation->role)]) }}</p>
        <form method="POST" action="{{ route('join.submit', $token) }}" class="needs-validation" novalidate>
            @csrf
            <div style="position:absolute;left:-10000px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
            <x-auth-field name="name" icon="bi-person" :placeholder="__('Họ tên')" required maxlength="255" autocomplete="name" autofocus />
            <x-auth-field name="email" type="email" icon="bi-envelope" placeholder="Email" required maxlength="255" autocomplete="email" :help="__('Mã xác thực 6 số sẽ được gửi tới email này.')" />
            <x-auth-field name="phone" type="tel" icon="bi-telephone" :placeholder="__('Số điện thoại')" maxlength="30" autocomplete="tel" />
            <x-auth-field name="password" type="password" icon="bi-lock" :placeholder="__('Mật khẩu')" required minlength="10" maxlength="128" autocomplete="new-password" :help="__('Tối thiểu 10 ký tự, có chữ hoa, chữ thường và chữ số.')" />
            <x-auth-field name="password_confirmation" type="password" icon="bi-lock-fill" :placeholder="__('Nhập lại mật khẩu')" required autocomplete="new-password" />
            <div class="form-check mb-3 small">
                <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" required @checked(old('terms'))>
                <label class="form-check-label" for="terms">{{ __('Tôi xác nhận thông tin là của chính tôi và sẽ bảo mật tài khoản.') }}</label>
                <div class="invalid-feedback">{{ $errors->first('terms') ?: __('Vui lòng xác nhận.') }}</div>
            </div>
            <button type="submit" class="ta-btn" data-loading-text="{{ __('Đang gửi mã...') }}"><i class="bi bi-envelope-arrow-up"></i> {{ __('Gửi mã xác thực') }}</button>
        </form>
        <p class="ta-note">{{ __('Đã có tài khoản?') }} <a href="{{ route('login') }}">{{ __('Đăng nhập') }}</a></p>
    @endif
@endsection
