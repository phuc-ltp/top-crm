{{-- Bộ chọn ngôn ngữ dùng chung. $variant: 'topbar' (giao diện chính) | 'auth' (trang đăng nhập) --}}
@php($current = app()->getLocale())
@php($locales = config('crm.locales'))
<div class="dropdown {{ $variant === 'auth' ? 'ta-lang' : 'd-none d-md-block' }}">
    @if ($variant === 'auth')
        <button class="btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Ngôn ngữ') }}"><i class="bi bi-globe2"></i> {{ $locales[$current] ?? $current }} <i class="bi bi-chevron-down small"></i></button>
    @else
        <button class="topbar-lang" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Ngôn ngữ') }}">
            <span class="flag-{{ $current }}" aria-hidden="true">{{ $current === 'vi' ? '★' : 'EN' }}</span><span class="lang-text">{{ $locales[$current] ?? $current }}</span><i class="bi bi-chevron-down small"></i>
        </button>
    @endif
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach ($locales as $code => $name)
            <li>
                <form method="POST" action="{{ route('locale.update') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $code }}">
                    <button type="submit" class="dropdown-item {{ $code === $current ? 'active' : '' }}" lang="{{ $code }}" @if ($code === $current) aria-current="true" @endif>
                        <i class="bi {{ $code === $current ? 'bi-check2' : 'bi-translate' }} me-2"></i>{{ $name }}
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
