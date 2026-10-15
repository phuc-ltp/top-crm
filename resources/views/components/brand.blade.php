@props(['size' => 56, 'slogan' => false])
@php($s = \App\Models\Setting::allCached())
<div {{ $attributes->merge(['class' => 'brand-lockup']) }}>
    <x-brand-logo :size="$size" />
    <div class="brand-words">
        <div class="brand-name" style="font-size: {{ round($size * 0.72) }}px">{{ $s['brand_name'] }}</div>
        <div class="brand-tagline" style="font-size: {{ max(8, round($size * 0.17)) }}px">{{ $s['brand_tagline'] }}</div>
        @if ($slogan && $s['brand_slogan'])<div class="brand-slogan">{{ $s['brand_slogan'] }}</div>@endif
    </div>
</div>
