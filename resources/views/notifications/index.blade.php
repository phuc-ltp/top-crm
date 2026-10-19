@extends('layouts.app')
@section('title', __('Thông báo'))
@section('content')
    @php($G = App\Support\NotificationCatalog::GROUPS)
    <x-page-header :title="__('Thông báo')" :breadcrumbs="['Thông báo' => null]">
        <a href="{{ route('notifications.preferences') }}" class="btn btn-light"><i class="bi bi-sliders me-1"></i>{{ __('Tùy chọn nhận') }}</a>
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
            @if (request('group'))<input type="hidden" name="group" value="{{ request('group') }}">@endif
            <button class="btn btn-light"><i class="bi bi-check2-all me-1"></i>{{ __('Đánh dấu đã đọc') }}</button></form>
        <form method="POST" action="{{ route('notifications.clear-read') }}" data-confirm="{{ __('Xóa mọi thông báo đã đọc?') }}" data-confirm-button="{{ __('Xóa') }}">@csrf @method('DELETE')
            <button class="btn btn-light text-danger"><i class="bi bi-trash me-1"></i>{{ __('Xóa thông báo đã đọc') }}</button></form>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card"><div class="list-group list-group-flush">
                <a href="{{ route('notifications.index', array_filter(['filter' => request('filter')])) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ request('group') ? '' : 'active' }}">
                    <span><i class="bi bi-inbox me-2"></i>{{ __('Tất cả') }}</span>
                    @if ($unreadByGroup->sum())<span class="badge {{ request('group') ? 'badge-soft-orange' : 'bg-light text-dark' }}">{{ $unreadByGroup->sum() }}</span>@endif
                </a>
                @foreach ($G as $key => [$label, $icon, $tone])
                    @continue($key === 'other' && ! ($unreadByGroup[$key] ?? 0))
                    <a href="{{ route('notifications.index', array_filter(['group' => $key, 'filter' => request('filter')])) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ request('group') === $key ? 'active' : '' }}">
                        <span><i class="bi {{ $icon }} me-2"></i>{{ __($label) }}</span>
                        @if ($unreadByGroup[$key] ?? 0)<span class="badge {{ request('group') === $key ? 'bg-light text-dark' : 'badge-soft-orange' }}">{{ $unreadByGroup[$key] }}</span>@endif
                    </a>
                @endforeach
            </div></div>
        </div>
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs-crm border-0">
                        <li class="nav-item"><a class="nav-link {{ request('filter') !== 'unread' ? 'active' : '' }}" href="{{ route('notifications.index', array_filter(['group' => request('group')])) }}">{{ __('Tất cả') }}</a></li>
                        <li class="nav-item"><a class="nav-link {{ request('filter') === 'unread' ? 'active' : '' }}" href="{{ route('notifications.index', array_filter(['group' => request('group'), 'filter' => 'unread'])) }}">{{ __('Chưa đọc') }}</a></li>
                    </ul>
                </div>
                @if ($notifications->isEmpty())
                    <x-empty-state icon="bi-bell-slash" :title="__('Không có thông báo')" />
                @else
                    @foreach ($byDay as $day => $items)
                        <div class="notif-day">{{ $day }}</div>
                        <div class="px-2">
                            @foreach ($items as $n)
                                @php([$gLabel, $gIcon, $gTone] = $G[$n->data['group'] ?? App\Support\NotificationCatalog::group($n->data['kind'] ?? null)])
                                <div class="notif-item {{ $n->read_at ? '' : 'unread' }}" data-notif="{{ $n->id }}">
                                    <span class="avatar avatar-sm tone-{{ $gTone }}"><i class="bi {{ $n->data['icon'] ?? $gIcon }}"></i></span>
                                    <a href="{{ route('notifications.open', $n->id) }}" class="flex-grow-1 min-w-0 text-reset">
                                        <span class="d-block small fw-semibold">{{ $n->data['message'] ?? '' }}</span>
                                        <span class="small-xs text-muted">{{ __($gLabel) }} · {{ $n->created_at->diffForHumans() }} · {{ vn_date($n->created_at, true) }}</span>
                                    </a>
                                    <div class="d-flex gap-1 align-self-center flex-shrink-0">
                                        <form method="POST" action="{{ route('notifications.toggle', $n->id) }}">@csrf
                                            <button class="btn btn-light btn-icon btn-sm" title="{{ $n->read_at ? __('Đánh dấu chưa đọc') : __('Đánh dấu đã đọc') }}" aria-label="{{ $n->read_at ? __('Đánh dấu chưa đọc') : __('Đánh dấu đã đọc') }}">
                                                <i class="bi {{ $n->read_at ? 'bi-envelope' : 'bi-envelope-open' }}"></i></button></form>
                                        <form method="POST" action="{{ route('notifications.destroy', $n->id) }}">@csrf @method('DELETE')
                                            <button class="btn btn-light btn-icon btn-sm text-danger" title="{{ __('Xóa') }}" aria-label="{{ __('Xóa') }}"><i class="bi bi-x-lg"></i></button></form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                    <x-pagination-bar :paginator="$notifications" />
                @endif
            </div>
            <p class="small text-muted mt-2"><i class="bi bi-info-circle"></i> {{ __('Thông báo đã đọc được tự xóa sau 90 ngày, thông báo chưa đọc sau 180 ngày.') }}</p>
        </div>
    </div>
@endsection
