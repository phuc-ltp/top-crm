@props(['column', 'label'])
@php
    $current = request('sort');
    $dir = request('direction', 'desc') === 'asc' ? 'asc' : 'desc';
    $next = $current === $column && $dir === 'asc' ? 'desc' : 'asc';
@endphp
<a class="sort-link" href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $next, 'page' => null]) }}">
    {{ $label }}
    @if ($current === $column)<i class="bi bi-arrow-{{ $dir === 'asc' ? 'up' : 'down' }}"></i>@endif
</a>
