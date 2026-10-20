@extends('layouts.app')
@section('title', __('Nhóm'))
@section('content')
    <x-page-header :title="__('Nhóm')" :breadcrumbs="['Nhóm' => null]" :subtitle="__('Nhóm làm việc, trưởng nhóm và kết quả tháng này')">
        @can('teams.manage')<a href="{{ route('teams.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo nhóm') }}</a>@endcan
    </x-page-header>
    @if ($teams->isEmpty())
        <div class="card"><x-empty-state icon="bi-people" :title="__('Chưa có nhóm')" /></div>
    @else
        <div class="row g-3">
            @foreach ($teams as $t)
                @php($k = $teamKpi[$t->id])
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100"><div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <a href="{{ route('teams.show', $t) }}" class="fw-semibold fs-6 row-link">{{ $t->name }}</a>
                            <span class="badge badge-soft-secondary">{{ __(':count thành viên', ['count' => $t->members_count]) }}</span>
                        </div>
                        <div class="small text-muted mb-3">{{ $t->department?->name ?? __('Không thuộc phòng ban') }} · {{ __('Trưởng nhóm') }}: {{ $t->leader?->name ?? '—' }}</div>
                        <div class="row g-2 small mb-3">
                            <div class="col-6"><div class="text-muted">{{ __('Doanh thu đã thu') }}</div><div class="fw-semibold">{{ money($k['revenue']) }}</div></div>
                            <div class="col-6"><div class="text-muted">{{ __('Cơ hội thắng') }}</div><div class="fw-semibold">{{ $k['deals_won'] }}</div></div>
                            <div class="col-6"><div class="text-muted">{{ __('Việc hoàn thành') }}</div><div class="fw-semibold">{{ $k['tasks_done'] }}@if ($k['on_time_rate'] !== null) <span class="text-muted fw-normal">({{ $k['on_time_rate'] }}% {{ __('đúng hạn') }})</span>@endif</div></div>
                            <div class="col-6"><div class="text-muted">{{ __('Ticket đã giải quyết') }}</div><div class="fw-semibold">{{ $k['tickets_resolved'] }}</div></div>
                        </div>
                        <div class="avatar-stack mt-auto">
                            @foreach ($t->members->take(6) as $m)<x-avatar :user="$m" size="sm" />@endforeach
                            @if ($t->members->count() > 6)<span class="avatar avatar-sm tone-gray">+{{ $t->members->count() - 6 }}</span>@endif
                        </div>
                    </div></div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
