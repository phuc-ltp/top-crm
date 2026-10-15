@extends('layouts.app')
@section('title', __('Thông báo'))
@section('content')
    <x-page-header :title="__('Thông báo')" :breadcrumbs="['Thông báo' => null]">
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-light"><i class="bi bi-check2-all me-1"></i>{{ __('Đánh dấu tất cả đã đọc') }}</button></form>
    </x-page-header>
    <div class="card" style="max-width: 900px">
        <div class="card-header">
            <ul class="nav nav-tabs-crm border-0">
                <li class="nav-item"><a class="nav-link {{ request('filter') !== 'unread' ? 'active' : '' }}" href="{{ route('notifications.index') }}">{{ __('Tất cả') }}</a></li>
                <li class="nav-item"><a class="nav-link {{ request('filter') === 'unread' ? 'active' : '' }}" href="{{ route('notifications.index', ['filter' => 'unread']) }}">{{ __('Chưa đọc') }}</a></li>
            </ul>
        </div>
        <div class="p-2">
            @forelse ($notifications as $n)
                <a href="{{ route('notifications.open', $n->id) }}" class="notif-item {{ $n->read_at ? '' : 'unread' }}">
                    <span class="avatar avatar-sm tone-orange"><i class="bi {{ $n->data['icon'] ?? 'bi-bell' }}"></i></span>
                    <span class="flex-grow-1"><span class="d-block small fw-semibold">{{ $n->data['message'] ?? '' }}</span>
                        <span class="small-xs text-muted">{{ $n->created_at->diffForHumans() }} · {{ vn_date($n->created_at, true) }}</span></span>
                    @unless ($n->read_at)<span class="badge badge-soft-orange align-self-center">{{ __('Mới') }}</span>@endunless
                </a>
            @empty
                <x-empty-state icon="bi-bell" :title="__('Không có thông báo')" />
            @endforelse
        </div>
        <x-pagination-bar :paginator="$notifications" />
    </div>
@endsection
