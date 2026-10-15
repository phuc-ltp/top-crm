@props(['icon', 'label', 'value', 'tone' => 'orange', 'sub' => null, 'href' => null])
<div class="card card-hover h-100">
    @if ($href)<a href="{{ $href }}" class="text-reset">@endif
    <div class="stat-card">
        <div class="stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></div>
        <div class="min-w-0">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value">{{ $value }}</div>
            @if ($sub)<div class="stat-sub">{!! $sub !!}</div>@endif
        </div>
    </div>
    @if ($href)</a>@endif
</div>
