@props(['icon' => 'bi-inbox', 'title' => null, 'message' => null])
<div class="empty-state">
    <div class="empty-icon"><i class="bi {{ $icon }}"></i></div>
    <h6>{{ $title ?? __('Chưa có dữ liệu') }}</h6>
    @if ($message)<p class="small mb-3">{{ $message }}</p>@endif
    {{ $slot }}
</div>
