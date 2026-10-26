<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Liên hệ :company', ['company' => $company]) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background: linear-gradient(160deg, #FFF7EF 0%, #F6EBDD 100%); min-height: 100vh; }
        .lf-wrap { max-width: 560px; margin: 0 auto; padding: 48px 16px; }
        .lf-card { background: #fff; border-radius: 20px; box-shadow: 0 20px 60px rgba(60, 30, 10, .12); padding: 32px; }
        .lf-logo { width: 56px; height: 56px; object-fit: contain; border-radius: 12px; }
        .lf-hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
    </style>
</head>
<body>
<main class="lf-wrap">
    <div class="lf-card">
        <div class="d-flex align-items-center gap-3 mb-4">
            @if ($logo)<img src="{{ Storage::disk('public')->url($logo) }}" alt="" class="lf-logo">@endif
            <div>
                <div class="small text-muted">{{ __('Liên hệ với') }}</div>
                <h1 class="h4 fw-bold mb-0">{{ $company }}</h1>
            </div>
        </div>

        @if (session('lead_form_sent'))
            <div class="text-center py-4">
                <div class="display-6 text-success mb-2"><i class="bi bi-check-circle"></i></div>
                <h2 class="h5 fw-bold">{{ __('Cảm ơn bạn!') }}</h2>
                <p class="text-muted mb-0">{{ $thanks ?: __('Chúng tôi đã nhận được thông tin và sẽ liên hệ lại với bạn sớm nhất.') }}</p>
            </div>
        @else
            <p class="text-muted">{{ __('Để lại thông tin, chúng tôi sẽ liên hệ lại với bạn.') }}</p>
            <form method="POST" action="{{ route('lead-form.submit', $token) }}" class="needs-validation" novalidate>
                @csrf
                <input type="hidden" name="stamp" value="{{ $stamp }}">
                @foreach ($tracking ?? [] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <div class="lf-hp" aria-hidden="true"><label for="website">Website</label><input type="text" name="website" id="website" tabindex="-1" autocomplete="off"></div>
                <x-input name="name" :label="__('Họ tên')" required maxlength="255" autocomplete="name" />
                <div class="row">
                    <x-input name="phone" :label="__('Số điện thoại')" maxlength="30" autocomplete="tel" pattern="[0-9+\-\s().]{6,30}" col="col-md-6 mb-3" />
                    <x-input name="email" type="email" label="Email" maxlength="255" autocomplete="email" col="col-md-6 mb-3" />
                </div>
                <div class="form-text mt-n2 mb-3">{{ __('Vui lòng nhập ít nhất số điện thoại hoặc email.') }}</div>
                <x-input name="company" :label="__('Công ty (không bắt buộc)')" maxlength="255" autocomplete="organization" />
                <x-textarea name="message" :label="__('Bạn cần hỗ trợ gì?')" rows="4" maxlength="2000" />
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" @checked(old('consent'))>
                    <label class="form-check-label small" for="consent">{{ __('Tôi đồng ý nhận thông tin, ưu đãi từ :company. Có thể từ chối bất cứ lúc nào.', ['company' => $company]) }}</label>
                </div>
                <button class="btn btn-primary w-100" data-loading-text="{{ __('Đang gửi...') }}"><i class="bi bi-send me-1"></i>{{ __('Gửi thông tin') }}</button>
                <p class="small text-muted mt-3 mb-0">{{ __('Thông tin của bạn chỉ dùng để liên hệ tư vấn, không chia sẻ cho bên thứ ba.') }}</p>
            </form>
        @endif
    </div>
    <p class="text-center small text-muted mt-3">{{ __('Vận hành bởi :brand', ['brand' => 'TOP CRM']) }}</p>
</main>
</body>
</html>
