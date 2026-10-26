<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#140B06">
    <meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ $crmSettings['company_name'] }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @include('partials.pwa-head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- Thứ tự quan trọng: auth-mobile.css phải nạp SAU auth.css --}}
    @vite(['resources/css/app.css', 'resources/css/auth.css', 'resources/css/auth-mobile.css', 'resources/js/app.js'])
    @if ($jsI18n = \App\Support\JsTranslations::forCurrentLocale())<script>window.CRM_I18N = @json($jsI18n);</script>@endif
</head>
<body class="top-auth">
@php
    $bg = $crmSettings['login_background'] ? Storage::disk('public')->url($crmSettings['login_background']) : asset('images/login-bg.jpg');
@endphp
<div class="ta-shell">
    <div class="ta-bg" aria-hidden="true">
        <div class="ta-photo" style="background-image: url('{{ $bg }}')"></div>
        <div class="ta-shade"></div>
        <svg class="ta-arcs" viewBox="0 0 760 1100" fill="none">
            <path d="M740 20C420 140 250 420 330 760C380 960 560 1060 760 1090" stroke="url(#arc1)" stroke-width="1.2"/>
            <path d="M760 120C500 230 380 470 450 760C495 940 640 1040 760 1060" stroke="url(#arc1)" stroke-width="1"/>
            <path d="M760 260C600 330 520 520 570 760C600 900 690 980 760 1000" stroke="url(#arc1)" stroke-width=".8"/>
            <defs><linearGradient id="arc1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E8843A" stop-opacity=".0"/><stop offset=".5" stop-color="#E8843A" stop-opacity=".55"/><stop offset="1" stop-color="#E8843A" stop-opacity="0"/></linearGradient></defs>
        </svg>
    </div>

    <div class="ta-topbar">
        @include('partials.locale-switcher', ['variant' => 'auth'])
    </div>

    @hasSection('page')
        <main class="ta-page fade-in"><div class="ta-page-inner">@yield('page')</div></main>
    @else
        <main class="ta-main">
            <section class="ta-hero ta-desktop-only" aria-label="{{ __('Giới thiệu sản phẩm') }}">
                <x-brand :size="80" :slogan="true" />
                <div class="ta-divider"></div>
                <h1>{{ $crmSettings['login_headline'] }}<span class="accent">{{ $crmSettings['login_headline_accent'] }}</span></h1>
                <p class="lead-text">{{ $crmSettings['login_description'] }}</p>
                <ul class="ta-features">
                    <li><span class="ic"><i class="bi bi-people-fill"></i></span><span><span class="t">{{ __('Quản lý khách hàng') }}</span><span class="d">{{ __('Lưu trữ và chăm sóc khách hàng hiệu quả') }}</span></span></li>
                    <li><span class="ic"><i class="bi bi-bar-chart-fill"></i></span><span><span class="t">{{ __('Tăng doanh số') }}</span><span class="d">{{ __('Tối ưu quy trình bán hàng') }}</span></span></li>
                    <li><span class="ic"><i class="bi bi-clipboard-check"></i></span><span><span class="t">{{ __('Quản lý công việc') }}</span><span class="d">{{ __('Theo dõi và kiểm soát tiến độ') }}</span></span></li>
                    <li><span class="ic"><i class="bi bi-pie-chart-fill"></i></span><span><span class="t">{{ __('Báo cáo trực quan') }}</span><span class="d">{{ __('Dữ liệu chính xác, dễ dàng phân tích') }}</span></span></li>
                </ul>
                @php($stats = \App\Models\Setting::loginStats())
                @if ($stats)
                    @php($icons = ['bi-people-fill', 'bi-graph-up-arrow', 'bi-shield-check', 'bi-headset'])
                    <div class="ta-stats">
                        @foreach ($stats as $i => [$value, $label])
                            <div class="ta-stat"><i class="bi {{ $icons[$i] }}"></i><div><div class="v">{{ $value }}</div><div class="l">{{ $label }}</div></div></div>
                        @endforeach
                    </div>
                @endif
            </section>
            <section class="ta-card-wrap">
                <div class="ta-card fade-in">
                    @yield('content')
                </div>
            </section>
        </main>
    @endif

    <footer class="ta-footer">
        <span>© {{ date('Y') }} {{ $crmSettings['copyright_text'] }}.@if ($crmSettings['copyright_owner']) {{ __('Bản quyền thuộc :owner.', ['owner' => $crmSettings['copyright_owner']]) }}@endif {{ __('Tất cả quyền được bảo lưu.') }}</span>
        <nav aria-label="{{ __('Liên kết chân trang') }}">
            <a href="{{ route('pages.terms') }}">{{ __('Điều khoản sử dụng') }}</a>
            <a href="{{ route('pages.privacy') }}">{{ __('Chính sách bảo mật') }}</a>
            <a href="{{ route('pages.support') }}">{{ __('Hỗ trợ') }}</a>
        </nav>
    </footer>
</div>
@include('partials.flash')
</body>
</html>
