@props(['title', 'breadcrumbs' => [], 'subtitle' => null])
<div class="page-header">
    <div class="min-w-0">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="bi bi-house-door"></i></a></li>
                @foreach ($breadcrumbs as $label => $url)
                    @if ($loop->last && ! $url)
                        <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                    @endif
                @endforeach
            </ol>
        </nav>
        <h1 class="text-truncate">{{ $title }}</h1>
        @if ($subtitle)<div class="text-muted small mt-1">{{ $subtitle }}</div>@endif
    </div>
    @if (trim($slot))
        <div class="d-flex flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
