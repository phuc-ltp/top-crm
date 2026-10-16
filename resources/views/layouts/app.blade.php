<!DOCTYPE html>
@php($userTheme = in_array(auth()->user()?->theme, ['light', 'dark'], true) ? auth()->user()->theme : null)
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ $userTheme ?? 'light' }}" data-user-theme="{{ $userTheme ?? 'system' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="crm-theme-url" content="{{ route('preferences.theme') }}">
    <title>@yield('title', __('Tổng quan')) · {{ $crmSettings['company_name'] }}</title>
    {{-- Áp dụng giao diện sáng/tối trước khi vẽ trang (tránh nháy màu). Ưu tiên lựa chọn lưu trong tài khoản. --}}
    <script>
        (function () {
            var saved = document.documentElement.getAttribute('data-user-theme');
            try {
                var t = saved === 'light' || saved === 'dark' ? saved : localStorage.getItem('crm.theme');
                if (t !== 'light' && t !== 'dark') t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                if (saved === 'light' || saved === 'dark') localStorage.setItem('crm.theme', saved);
                document.documentElement.setAttribute('data-bs-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php($brandColor = strtoupper($crmSettings['brand_color'] ?? '#D9823B'))
    @if (! in_array($brandColor, ['#D9823B', '#FF6B00'], true) && preg_match('/^#[0-9A-F]{6}$/', $brandColor))
        <style>:root { --crm-primary: {{ $brandColor }}; --crm-primary-hover: {{ $brandColor }}; }</style>
    @endif
    @stack('styles')
    @if ($jsI18n = \App\Support\JsTranslations::forCurrentLocale())<script>window.CRM_I18N = @json($jsI18n);</script>@endif
</head>
<body class="crm-app">
@php($authUser = auth()->user())
@php($wallpaper = $crmSettings['login_background'] ? Storage::disk('public')->url($crmSettings['login_background']) : asset('images/login-bg.jpg'))
<div class="crm-wallpaper" style="--wallpaper: url('{{ $wallpaper }}')" aria-hidden="true"></div>

<aside class="crm-sidebar" aria-label="{{ __('Điều hướng chính') }}">
    <div class="mac-lights">
        <span class="l-red" aria-hidden="true"></span>
        <button class="l-yellow" type="button" data-toggle-sidebar title="{{ __('Thu gọn / mở rộng menu') }}" aria-label="{{ __('Thu gọn menu') }}"></button>
        <button class="l-green" type="button" data-fullscreen title="{{ __('Toàn màn hình') }}" aria-label="{{ __('Toàn màn hình') }}"></button>
    </div>
    <a href="{{ route('dashboard') }}" class="brand">
        <x-brand-logo :size="40" />
        <span class="brand-text"><span class="sidebar-brand-name">{{ $crmSettings['brand_name'] }}</span> CRM<small>{{ \Illuminate\Support\Str::title(mb_strtolower($crmSettings['brand_tagline'])) }}</small></span>
    </a>
    <div class="nav-search" role="search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" id="navSearch" placeholder="{{ __('Tìm chức năng...') }}" aria-label="{{ __('Tìm chức năng trong menu') }}" autocomplete="off" data-nav-search>
    </div>
    <nav class="crm-nav">
        <div class="nav-empty d-none" data-nav-empty>{{ __('Không có chức năng phù hợp') }}</div>
        @foreach (\App\Support\Menu::for($authUser) as $section)
            @if ($section['title'])<div class="nav-section">{{ $section['title'] }}</div>@endif
            @foreach ($section['items'] as $item)
                @if ($item['children'])
                    <button class="nav-group-toggle {{ $item['active'] ? 'has-active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $item['id'] }}"
                            aria-expanded="{{ $item['active'] ? 'true' : 'false' }}" aria-controls="{{ $item['id'] }}" title="{{ $item['label'] }}">
                        <i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span><i class="bi bi-chevron-down chev"></i>
                    </button>
                    <div class="collapse {{ $item['active'] ? 'show' : '' }}" id="{{ $item['id'] }}">
                        <div class="nav-sub">
                            @foreach ($item['children'] as $child)
                                <a href="{{ route($child['route']) }}" class="nav-link {{ $child['active'] ? 'active' : '' }}">{{ $child['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ route($item['route']) }}" class="nav-link {{ $item['active'] ? 'active' : '' }}" title="{{ $item['label'] }}">
                        <i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        @endforeach
    </nav>
    <div class="sidebar-bottom">
        <div class="dropup">
            <button type="button" class="sidebar-card workspace" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Không gian làm việc') }}">
                <span class="ic"><x-brand-logo :size="22" /></span>
                <span class="min-w-0 flex-grow-1 text-start"><span class="t d-block text-truncate">{{ $currentOrganization?->name ?? $crmSettings['company_name'] }}</span><span class="s">{{ __('Không gian làm việc') }}</span></span>
                <i class="bi bi-chevron-expand small"></i>
            </button>
            <ul class="dropdown-menu workspace-menu">
                <li><h6 class="dropdown-header">{{ __('Chuyển tổ chức') }}</h6></li>
                @foreach ($workspaces as $ws)
                    <li>
                        <form method="POST" action="{{ route('workspace.switch') }}">
                            @csrf
                            <input type="hidden" name="organization_id" value="{{ $ws->id }}">
                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2 {{ $ws->id === $currentOrganization?->id ? 'active' : '' }}" @if ($ws->id === $currentOrganization?->id) aria-current="true" @endif>
                                <i class="bi {{ $ws->id === $currentOrganization?->id ? 'bi-check2' : 'bi-building' }}"></i><span class="text-truncate">{{ $ws->name }}</span>
                            </button>
                        </form>
                    </li>
                @endforeach
                @can('settings.manage')
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('settings.edit') }}"><i class="bi bi-gear me-2"></i>{{ __('Cài đặt tổ chức') }}</a></li>
                @endcan
                @can('platform')
                    <li><a class="dropdown-item" href="{{ route('platform.organizations.index') }}"><i class="bi bi-buildings me-2"></i>{{ __('Quản lý tổ chức') }}</a></li>
                @endcan
            </ul>
        </div>
        <div class="dropup">
            <button class="sidebar-card" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <x-avatar :user="$authUser" />
                <span class="min-w-0 flex-grow-1"><span class="t d-block text-truncate">{{ $authUser->name }}</span><span class="s d-block text-truncate">{{ $authUser->isPlatformAdmin() ? __('Platform Super Administrator') : ($authUser->getRoleNames()->first() ? __($authUser->getRoleNames()->first()) : __('Chưa có vai trò')) }}</span></span>
                <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>{{ __('Hồ sơ cá nhân') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}#password"><i class="bi bi-key me-2"></i>{{ __('Đổi mật khẩu') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('profile.security') }}"><i class="bi bi-shield-lock me-2"></i>{{ __('Bảo mật tài khoản') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('pages.support') }}"><i class="bi bi-question-circle me-2"></i>{{ __('Hướng dẫn & hỗ trợ') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Đăng xuất') }}</button></form></li>
            </ul>
        </div>
    </div>
</aside>
<div class="crm-backdrop"></div>

<div class="crm-main">
<div class="crm-window">
    @if ($hasDemoData ?? false)
        <div class="demo-ribbon no-print"><i class="bi bi-cone-striped me-1"></i> {{ __('Hệ thống đang chứa DỮ LIỆU DEMO phục vụ thử nghiệm — không phải số liệu kinh doanh thật.') }}</div>
    @endif
    <header class="crm-topbar">
        <button class="topbar-icon d-lg-none" type="button" data-toggle-sidebar aria-label="{{ __('Mở menu') }}"><i class="bi bi-list"></i></button>

        <div class="crm-search d-none d-md-block">
            <i class="bi bi-search"></i>
            <input type="search" id="globalSearch" class="form-control" placeholder="{{ __('Tìm kiếm khách hàng, đơn hàng, công việc...') }}" autocomplete="off"
                   data-url="{{ route('search') }}" data-results-url="{{ route('search.results') }}" data-clear-url="{{ route('search.recent.clear') }}"
                   aria-label="{{ __('Tìm kiếm') }}" role="combobox" aria-expanded="false" aria-controls="globalSearchResults" aria-autocomplete="list">
            <kbd>Ctrl K</kbd>
            <div id="globalSearchResults" class="crm-search-results" role="listbox"></div>
        </div>
        {{-- Điện thoại / màn hình nhỏ: mở trang tìm kiếm --}}
        <a href="{{ route('search.results') }}" class="topbar-icon d-md-none" aria-label="{{ __('Tìm kiếm') }}" title="{{ __('Tìm kiếm') }}"><i class="bi bi-search"></i></a>

        <div class="ms-auto d-flex align-items-center gap-2">
            @php($quick = collect([
                ['customers.create', __('labels.customer'), 'bi-people', 'create', \App\Models\Customer::class],
                ['leads.create', __('labels.lead'), 'bi-funnel', 'create', \App\Models\Lead::class],
                ['opportunities.create', __('labels.opportunity'), 'bi-kanban', 'create', \App\Models\Opportunity::class],
                ['tasks.create', __('labels.task'), 'bi-check2-square', 'create', \App\Models\Task::class],
                ['quotations.create', __('labels.quotation'), 'bi-file-earmark-text', 'create', \App\Models\Quotation::class],
                ['orders.create', __('labels.order'), 'bi-bag-check', 'create', \App\Models\SalesOrder::class],
                ['tickets.create', __('labels.ticket'), 'bi-headset', 'create', \App\Models\SupportTicket::class],
            ])->filter(fn ($q) => $authUser->can($q[3], $q[4])))
            @if ($quick->isNotEmpty())
                <div class="dropdown">
                    <button class="btn btn-create" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg"></i><span class="txt">{{ __('Tạo mới') }}</span></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach ($quick as $q)
                            <li><a class="dropdown-item" href="{{ route($q[0]) }}"><i class="bi {{ $q[2] }} me-2 text-orange"></i>{{ $q[1] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="dropdown" id="notifDropdown" data-url="{{ route('notifications.feed') }}">
                <button class="topbar-icon" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="{{ __('Thông báo') }}">
                    <i class="bi bi-bell"></i>
                    @if ($unreadNotifications > 0)
                        <span class="dot" id="notifBadge">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 360px; max-width: 92vw;">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <strong>{{ __('Thông báo') }}</strong>
                        <a href="#" class="small" id="notifMarkAll" data-url="{{ route('notifications.read-all') }}">{{ __('Đánh dấu tất cả đã đọc') }}</a>
                    </div>
                    <div id="notifList" class="p-2" style="max-height: 380px; overflow-y: auto;"></div>
                    <a href="{{ route('notifications.index') }}" class="d-block text-center py-2 border-top small fw-semibold">{{ __('Xem tất cả') }}</a>
                </div>
            </div>
            @can('tasks.view')
                <a href="{{ route('tasks.calendar') }}" class="topbar-icon d-none d-sm-grid" title="{{ __('Lịch công việc') }}" aria-label="{{ __('Lịch công việc') }}"><i class="bi bi-calendar2-check"></i></a>
            @endcan
            <button class="topbar-icon theme-toggle" type="button" data-theme-toggle title="{{ __('Chuyển giao diện sáng / tối') }}" aria-label="{{ __('Chuyển giao diện sáng / tối') }}">
                <i class="bi bi-moon-stars"></i><i class="bi bi-sun"></i>
            </button>
            @include('partials.locale-switcher', ['variant' => 'topbar'])
            <div class="dropdown">
                <button class="topbar-user" data-bs-toggle="dropdown" aria-label="{{ __('Tài khoản') }}"><x-avatar :user="$authUser" /></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2"><div class="fw-semibold small">{{ $authUser->name }}</div><div class="small-xs text-muted">{{ $authUser->email }}</div></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>{{ __('Hồ sơ cá nhân') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('profile.security') }}"><i class="bi bi-shield-lock me-2"></i>{{ __('Bảo mật tài khoản') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('profile.sessions') }}"><i class="bi bi-laptop me-2"></i>{{ __('Phiên đăng nhập') }}</a></li>
                    @can('api.use')<li><a class="dropdown-item" href="{{ route('api-tokens.index') }}"><i class="bi bi-key me-2"></i>{{ __('Khóa API') }}</a></li>@endcan
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Đăng xuất') }}</button></form></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="crm-content fade-in">
        @yield('content')
    </main>

    <footer class="crm-footer">
        <span>© {{ date('Y') }} {{ $crmSettings['copyright_text'] }}.@if ($crmSettings['copyright_owner']) {{ __('Bản quyền thuộc :owner.', ['owner' => $crmSettings['copyright_owner']]) }}@endif {{ __('Tất cả quyền được bảo lưu.') }}</span>
        <nav><a href="{{ route('pages.terms') }}">{{ __('Điều khoản sử dụng') }}</a><a href="{{ route('pages.privacy') }}">{{ __('Chính sách bảo mật') }}</a><a href="{{ route('pages.support') }}">{{ __('Hỗ trợ') }}</a></nav>
    </footer>
</div>
</div>

{{-- Hộp thoại xác nhận dùng chung --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="empty-icon mx-auto mb-3" style="width:56px;height:56px;border-radius:50%;display:grid;place-items:center;background:var(--crm-danger-soft);color:var(--crm-danger-text);font-size:1.5rem;"><i class="bi bi-exclamation-triangle"></i></div>
                <p class="confirm-message mb-0 fw-semibold"></p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0 pb-4">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button>
                <button type="button" class="btn btn-danger confirm-ok">{{ __('Xác nhận') }}</button>
            </div>
        </div>
    </div>
</div>

@include('partials.flash')
@stack('scripts')
</body>
</html>
