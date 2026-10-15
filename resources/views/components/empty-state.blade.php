@props(['icon' => 'bi-inbox', 'title' => 'Chưa có dữ liệu', 'message' => null])
<div class="empty-state">
    <div class="empty-icon"><i class="bi {{ $icon }}"></i></div>
    <h6>{{ $title }}</h6>
    @if ($message)<p class="small mb-3">{{ $message }}</p>@endif
    {{ $slot }}
</div>
