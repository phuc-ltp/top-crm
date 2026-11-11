@extends('layouts.auth')
@section('title', __('Xác thực email'))
@section('content')
    <a href="{{ route('register') }}" class="ta-back"><i class="bi bi-arrow-left"></i> {{ __('Sửa thông tin') }}</a>
    @include('auth._card-brand')
    <h2>{{ __('Xác thực email') }}</h2>
    <p class="sub">{{ __('Chúng tôi đã gửi mã 6 số tới :email. Mở hộp thư (cả mục Spam) và nhập mã — mã hết hạn sau 10 phút.', ['email' => $maskedEmail]) }}</p>
    <form method="POST" action="{{ route('register.verify.store') }}" class="needs-validation" novalidate>
        @csrf
        <x-auth-field name="code" icon="bi-envelope-check" :placeholder="__('Mã xác thực 6 số')" required autofocus
                      inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" />
        <button type="submit" class="ta-btn" data-loading-text="{{ __('Đang kiểm tra...') }}"><i class="bi bi-send-check"></i> {{ __('Gửi yêu cầu tham gia') }}</button>
    </form>
    <form method="POST" action="{{ route('register.resend') }}" class="mt-3 text-center">@csrf
        <button type="submit" class="btn btn-link small" @disabled($cooldown > 0) data-cooldown="{{ $cooldown }}">{{ __('Chưa nhận được mã? Gửi lại') }}@if ($cooldown > 0) ({{ $cooldown }}s)@endif</button>
    </form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-cooldown]').forEach((btn) => {
    let left = Number(btn.dataset.cooldown);
    if (!left) return;
    const label = btn.textContent.replace(/\s*\(\d+s\)\s*$/, '');
    const tick = setInterval(() => {
        left -= 1;
        if (left <= 0) { clearInterval(tick); btn.disabled = false; btn.textContent = label; return; }
        btn.textContent = label + ' (' + left + 's)';
    }, 1000);
});
</script>
@endpush
