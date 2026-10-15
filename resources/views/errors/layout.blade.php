@php
    // Trang lỗi phải hiển thị được cả khi database gặp sự cố → không phụ thuộc truy vấn
    try { $s = \App\Models\Setting::allCached(); } catch (\Throwable) { $s = \App\Models\Setting::DEFAULTS; }
    $home = auth()->check() ? route('dashboard') : route('login');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ $s['company_name'] ?? 'TOP CRM' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
            font-family: 'Be Vietnam Pro', 'Segoe UI', system-ui, sans-serif; color: #F6ECE4;
            background: radial-gradient(60% 55% at 50% 30%, rgba(232,132,58,.18), transparent 70%), linear-gradient(180deg, #1C100A, #140B06); }
        .box { width: 100%; max-width: 520px; text-align: center; background: rgba(42,25,15,.75); border: 1px solid rgba(255,196,150,.16);
            border-radius: 22px; padding: 40px 32px; box-shadow: 0 30px 80px rgba(0,0,0,.5); }
        .logo { width: 56px; height: 56px; margin: 0 auto 12px; }
        .code { font-size: 64px; font-weight: 800; line-height: 1; margin: 8px 0 4px;
            background: linear-gradient(180deg, #FFC48E, #E8843A 60%, #B85A1C); -webkit-background-clip: text; background-clip: text; color: transparent; }
        h1 { font-size: 22px; margin: 8px 0; }
        p { color: #C9B6A8; line-height: 1.6; margin: 0 0 24px; font-size: 15px; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        a.btn { display: inline-flex; align-items: center; justify-content: center; height: 46px; padding: 0 22px; border-radius: 10px; font-weight: 600; text-decoration: none; font-size: 15px; }
        a.primary { color: #fff; background: linear-gradient(180deg, #EC8E45, #C9692A); box-shadow: 0 10px 24px rgba(201,105,42,.35); }
        a.ghost { color: #F3E6DB; border: 1px solid rgba(255,200,160,.25); }
        .foot { margin-top: 24px; font-size: 12px; color: #8D7A6C; }
    </style>
</head>
<body>
<main class="box">
    <svg class="logo" viewBox="0 0 100 100" aria-hidden="true">
        <polygon points="10,27 50,4 90,27 72,37.4 50,24.7 28,37.4" fill="#F0A562"/>
        <polygon points="90,27 90,73 50,96 50,75.3 72,62.6 72,37.4" fill="#6E3010"/>
        <polygon points="50,96 10,73 10,27 28,37.4 28,62.6 50,75.3" fill="#B85E23"/>
        <polygon points="34,42.5 50,33.3 66,42.5 50,51.7" fill="#B85E23"/>
        <polygon points="34,42.5 50,51.7 50,70 34,60.8" fill="#6E3010"/>
        <polygon points="66,42.5 66,60.8 50,70 50,51.7" fill="#F0A562"/>
    </svg>
    <div class="code">@yield('code')</div>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <div class="actions">
        @hasSection('action')
            @yield('action')
        @else
            <a class="btn primary" href="{{ $home }}">{{ auth()->check() ? __('Về trang tổng quan') : __('Về trang đăng nhập') }}</a>
        @endif
        <a class="btn ghost" href="javascript:history.back()">{{ __('Quay lại') }}</a>
    </div>
    <div class="foot">© {{ date('Y') }} {{ $s['copyright_text'] ?? 'TOP — Technology of Prowess' }}</div>
</main>
</body>
</html>
