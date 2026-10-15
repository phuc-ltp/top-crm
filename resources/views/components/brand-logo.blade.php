@props(['size' => 48])
@php($logo = \App\Models\Setting::get('company_logo'))
@php($uid = 'tl'.substr(md5(uniqid('', true)), 0, 8))
@if ($logo)
    <img src="{{ Storage::disk('public')->url($logo) }}" alt="Logo" width="{{ $size }}" height="{{ $size }}" {{ $attributes->merge(['class' => 'brand-mark']) }} style="object-fit:contain">
@else
    {{-- Khối lục giác đồng lồng nhau (logo TOP) --}}
    <svg viewBox="0 0 100 100" width="{{ $size }}" height="{{ $size }}" {{ $attributes->merge(['class' => 'brand-mark']) }} role="img" aria-label="Logo">
        <defs>
            <linearGradient id="{{ $uid }}-light" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFD0A1"/><stop offset="1" stop-color="#E08A45"/></linearGradient>
            <linearGradient id="{{ $uid }}-mid" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#D47A3A"/><stop offset="1" stop-color="#9A4A1C"/></linearGradient>
            <linearGradient id="{{ $uid }}-dark" x1="1" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8A3F16"/><stop offset="1" stop-color="#55220A"/></linearGradient>
        </defs>
        {{-- Vành ngoài: 3 mặt khối --}}
        <polygon points="10,27 50,4 90,27 72,37.4 50,24.7 28,37.4" fill="url(#{{ $uid }}-light)"/>
        <polygon points="90,27 90,73 50,96 50,75.3 72,62.6 72,37.4" fill="url(#{{ $uid }}-dark)"/>
        <polygon points="50,96 10,73 10,27 28,37.4 28,62.6 50,75.3" fill="url(#{{ $uid }}-mid)"/>
        {{-- Khối trong (đảo sáng tối tạo chiều sâu) --}}
        <polygon points="34,42.5 50,33.3 66,42.5 50,51.7" fill="url(#{{ $uid }}-mid)"/>
        <polygon points="34,42.5 50,51.7 50,70 34,60.8" fill="url(#{{ $uid }}-dark)"/>
        <polygon points="66,42.5 66,60.8 50,70 50,51.7" fill="url(#{{ $uid }}-light)"/>
        {{-- Rãnh nối tạo hình chữ P --}}
        <polygon points="72,37.4 66,42.5 50,33.3 50,24.7" fill="#3A1707" opacity=".55"/>
    </svg>
@endif
