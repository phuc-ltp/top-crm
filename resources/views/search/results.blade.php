@extends('layouts.app')
@section('title', $q !== '' ? __('Tìm: :q', ['q' => $q]) : __('Tìm kiếm'))
@section('content')
    <x-page-header :title="__('Tìm kiếm')" :breadcrumbs="['Tìm kiếm' => null]"
                   :subtitle="$q !== '' ? __(':count kết quả cho “:q” (trong phạm vi bạn được xem).', ['count' => array_sum($counts), 'q' => $q]) : null" />

    <div class="card mb-4"><div class="card-body">
        <form method="GET" action="{{ route('search.results') }}" class="d-flex gap-2" role="search">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ $q }}" class="form-control" minlength="2" maxlength="100" required autofocus
                       placeholder="{{ __('Tên, mã, email, số điện thoại...') }}" aria-label="{{ __('Từ khóa tìm kiếm') }}">
            </div>
            <button class="btn btn-primary text-nowrap">{{ __('Tìm') }}</button>
        </form>
        @if ($recent->isNotEmpty())
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <span class="small text-muted"><i class="bi bi-clock-history me-1"></i>{{ __('Tìm gần đây:') }}</span>
                @foreach ($recent as $r)<a href="{{ route('search.results', ['q' => $r]) }}" class="badge badge-soft-secondary text-decoration-none">{{ $r }}</a>@endforeach
                <form method="POST" action="{{ route('search.recent.clear') }}" class="d-inline">@csrf @method('DELETE')
                    <button class="btn btn-link btn-sm p-0 text-muted">{{ __('Xóa lịch sử') }}</button></form>
            </div>
        @endif
    </div></div>

    @if ($q !== '' && mb_strlen($q) >= 2)
        <div class="row g-4">
            <div class="col-lg-3">
                <div class="card"><div class="list-group list-group-flush">
                    @foreach ($counts as $key => $count)
                        <a href="{{ route('search.results', ['q' => $q, 'type' => $key]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $type === $key ? 'active' : '' }}">
                            <span><i class="bi {{ $types[$key]['icon'] }} me-2"></i>{{ __($types[$key]['label']) }}</span>
                            <span class="badge {{ $type === $key ? 'bg-light text-dark' : 'badge-soft-secondary' }}">{{ $count }}</span>
                        </a>
                    @endforeach
                </div></div>
            </div>
            <div class="col-lg-9">
                <div class="card">
                    @if (! $items || $items->isEmpty())
                        <x-empty-state icon="bi-search" :title="__('Không tìm thấy kết quả phù hợp.')" :message="__('Thử từ khóa khác, mã (VD: KH00012) hoặc số điện thoại.')" />
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($items as $it)
                                <a href="{{ $it['url'] }}" class="list-group-item list-group-item-action py-3">
                                    <div class="d-flex gap-3 align-items-center">
                                        <span class="avatar avatar-sm tone-orange"><i class="bi {{ $types[$type]['icon'] }}"></i></span>
                                        <div class="min-w-0"><div class="fw-semibold text-truncate">{{ $it['title'] }}</div><div class="small text-muted text-truncate">{{ $it['subtitle'] }}</div></div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        <x-pagination-bar :paginator="$items" />
                    @endif
                </div>
            </div>
        </div>
    @endif
@endsection
