<ul class="nav nav-tabs-crm mb-4">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.edit') ? 'active' : '' }}" href="{{ route('settings.edit') }}"><i class="bi bi-gear me-1"></i>{{ __('Cài đặt tổ chức') }}</a></li>
    @can('platform')
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.branding') ? 'active' : '' }}" href="{{ route('settings.branding') }}"><i class="bi bi-brush me-1"></i>{{ __('Trang đăng nhập & thương hiệu') }}</a></li>
    @endcan
</ul>
