@extends('layouts.auth')
@section('title', __('Tạo tài khoản'))
@section('content')
    <a href="{{ route('login') }}" class="ta-back"><i class="bi bi-arrow-left"></i> {{ __('Quay lại đăng nhập') }}</a>
    @include('auth._card-brand')
    @if (! $org)
        <h2>{{ __('Tạo tài khoản') }}</h2>
        <div class="ta-alert warn"><i class="bi bi-info-circle"></i> {{ __('Hiện chưa mở đăng ký trực tuyến. Hãy xin quản lý của bạn tạo tài khoản hoặc gửi link mời qua email / Zalo.') }}</div>
        <a href="{{ route('login') }}" class="ta-btn mt-3 text-decoration-none"><i class="bi bi-box-arrow-in-right"></i> {{ __('Đến trang đăng nhập') }}</a>
    @else
        <h2>{{ __('Tạo tài khoản') }}</h2>
        <p class="sub">{{ __('Gửi yêu cầu tham gia :company. Sau khi quản trị viên duyệt, bạn đăng nhập bằng email và mật khẩu này.', ['company' => $company]) }}</p>
        <ol class="ta-steps small mb-3 ps-3">
            <li><strong>{{ __('Điền thông tin') }}</strong></li>
            <li>{{ __('Nhập mã 6 số gửi tới email') }}</li>
            <li>{{ __('Chờ quản trị viên duyệt') }}</li>
        </ol>
        <form method="POST" action="{{ route('register.submit') }}" class="needs-validation" novalidate>
            @csrf
            <div style="position:absolute;left:-10000px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
            <x-auth-field name="name" icon="bi-person" :placeholder="__('Họ tên')" required minlength="2" maxlength="255" autocomplete="name" autofocus />
            <x-auth-field name="email" type="email" icon="bi-envelope" placeholder="Email" required maxlength="255" autocomplete="email" :help="__('Dùng email thật — mã xác thực và kết quả duyệt sẽ gửi tới đây.')" />
            <x-auth-field name="phone" type="tel" icon="bi-telephone" :placeholder="__('Số điện thoại')" maxlength="30" autocomplete="tel" />
            <x-auth-field name="password" type="password" icon="bi-lock" :placeholder="__('Mật khẩu')" required minlength="10" maxlength="128" autocomplete="new-password" :help="__('Tối thiểu 10 ký tự, có chữ hoa, chữ thường và chữ số.')" />
            <x-auth-field name="password_confirmation" type="password" icon="bi-lock-fill" :placeholder="__('Nhập lại mật khẩu')" required autocomplete="new-password" />
            <x-auth-field name="message" icon="bi-chat-left-text" :placeholder="__('Lời nhắn (không bắt buộc)')" maxlength="500" :help="__('VD: phòng ban, người giới thiệu — giúp quản trị duyệt nhanh hơn.')" />
            <div class="form-check mb-3 small">
                <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" required @checked(old('terms'))>
                <label class="form-check-label" for="terms">{!! __('Tôi đồng ý với :terms và xác nhận thông tin là của chính tôi.', ['terms' => '<a href="'.e(route('pages.terms')).'" target="_blank" rel="noopener">'.e(__('Điều khoản sử dụng')).'</a>']) !!}</label>
                <div class="invalid-feedback">{{ $errors->first('terms') ?: __('Vui lòng xác nhận.') }}</div>
            </div>
            <button type="submit" class="ta-btn" data-loading-text="{{ __('Đang gửi mã...') }}"><i class="bi bi-envelope-arrow-up"></i> {{ __('Gửi mã xác thực') }}</button>
        </form>
    @endif
    <p class="ta-note">{{ __('Đã có tài khoản?') }} <a href="{{ route('login') }}">{{ __('Đăng nhập') }}</a></p>
@endsection
