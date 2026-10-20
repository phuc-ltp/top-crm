@extends('layouts.app')
@section('title', __('Lịch chung'))
@section('content')
    @php($tz = crm_timezone())
    @php($prev = $view === 'week' ? $date->copy()->subWeek() : $date->copy()->subMonthNoOverflow())
    @php($next = $view === 'week' ? $date->copy()->addWeek() : $date->copy()->addMonthNoOverflow())
    @php($q = fn ($extra) => array_filter(array_merge(['view' => $view, 'user_id' => $canPickUser ? request('user_id') : null], $extra)))
    <x-page-header :title="__('Lịch chung')" :breadcrumbs="['Lịch' => null]" :subtitle="__('Lịch hẹn, cuộc họp và hạn công việc')">
        <div class="btn-group" role="group" aria-label="{{ __('Chế độ xem') }}">
            <a href="{{ route('calendar.index', $q(['view' => 'month', 'date' => $date->toDateString()])) }}" class="btn {{ $view === 'month' ? 'btn-primary' : 'btn-light' }}">{{ __('Tháng') }}</a>
            <a href="{{ route('calendar.index', $q(['view' => 'week', 'date' => $date->toDateString()])) }}" class="btn {{ $view === 'week' ? 'btn-primary' : 'btn-light' }}">{{ __('Tuần') }}</a>
        </div>
    </x-page-header>

    <div class="card mb-3">
        <div class="filter-bar">
            <a href="{{ route('calendar.index', $q(['date' => $prev->toDateString()])) }}" class="btn btn-light btn-icon" title="{{ __('Trước') }}" aria-label="{{ __('Trước') }}"><i class="bi bi-chevron-left"></i></a>
            <a href="{{ route('calendar.index', $q(['date' => now()->toDateString()])) }}" class="btn btn-light">{{ __('Hôm nay') }}</a>
            <a href="{{ route('calendar.index', $q(['date' => $next->toDateString()])) }}" class="btn btn-light btn-icon" title="{{ __('Sau') }}" aria-label="{{ __('Sau') }}"><i class="bi bi-chevron-right"></i></a>
            <div class="fw-semibold ms-2">{{ $view === 'week' ? vn_date($start).' → '.vn_date($end) : __('Tháng :m/:y', ['m' => $date->month, 'y' => $date->year]) }}</div>
            @if ($canPickUser)
                <form method="GET" class="ms-auto" data-auto-submit>
                    <input type="hidden" name="view" value="{{ $view }}"><input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <select name="user_id" class="form-select" aria-label="{{ __('Lịch của') }}">
                        @foreach ($users as $id => $name)<option value="{{ $id }}" @selected($whoId === $id)>{{ $name }}</option>@endforeach
                    </select>
                </form>
            @endif
        </div>
    </div>

    @if ($view === 'month')
        <div class="card"><div class="table-responsive"><table class="table table-bordered mb-0 calendar-month">
            <thead><tr>@foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $d)<th class="text-center small">{{ __($d) }}</th>@endforeach</tr></thead>
            <tbody>
            @for ($day = $start->copy(); $day->lte($end); $day->addWeek())
                <tr>
                    @for ($i = 0; $i < 7; $i++)
                        @php($d = $day->copy()->addDays($i))
                        @php($items = $byDay->get($d->format('Y-m-d'), collect()))
                        <td class="{{ $d->month !== $date->month ? 'bg-body-tertiary text-muted' : '' }} {{ $d->isToday() ? 'calendar-today' : '' }}">
                            <div class="small fw-semibold mb-1">{{ $d->day }}</div>
                            @foreach ($items->take(4) as $e)
                                <a href="{{ $e->url }}" class="calendar-event {{ $e->kind }} {{ $e->done ? 'is-done' : '' }} {{ $e->late ? 'is-late' : '' }}" title="{{ $e->title }} — {{ $e->meta }}">
                                    <i class="bi {{ $e->icon }}"></i> {{ $e->at->copy()->timezone($tz)->format('H:i') }} {{ $e->title }}</a>
                            @endforeach
                            @if ($items->count() > 4)<a href="{{ route('calendar.index', $q(['view' => 'week', 'date' => $d->toDateString()])) }}" class="small text-muted">+{{ $items->count() - 4 }} {{ __('mục') }}</a>@endif
                        </td>
                    @endfor
                </tr>
            @endfor
            </tbody>
        </table></div></div>
    @else
        <div class="card"><div class="card-body">
            @for ($d = $start->copy(); $d->lte($end); $d->addDay())
                @php($items = $byDay->get($d->format('Y-m-d'), collect()))
                <div class="d-flex gap-3 py-2 border-bottom {{ $d->isToday() ? 'calendar-today rounded-3 px-2' : '' }}">
                    <div class="text-center flex-shrink-0" style="width: 64px"><div class="small text-muted">{{ __(['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][$d->dayOfWeek]) }}</div><div class="fs-5 fw-bold">{{ $d->day }}</div></div>
                    <div class="flex-grow-1 min-w-0">
                        @forelse ($items as $e)
                            <div class="d-flex align-items-start gap-2 py-1">
                                <span class="badge {{ $e->kind === 'task' ? 'badge-soft-secondary' : 'badge-soft-orange' }} text-nowrap">{{ $e->at->copy()->timezone($tz)->format('H:i') }}@if ($e->end)–{{ $e->end->copy()->timezone($tz)->format('H:i') }}@endif</span>
                                <div class="min-w-0">
                                    <a href="{{ $e->url }}" class="small fw-semibold {{ $e->done ? 'text-decoration-line-through text-muted' : '' }} {{ $e->late ? 'text-danger' : '' }}"><i class="bi {{ $e->icon }} me-1"></i>{{ $e->title }}</a>
                                    <div class="small text-muted text-truncate">{{ $e->meta }}@if ($e->location) · <i class="bi bi-geo-alt"></i> {{ $e->location }}@endif
                                        @if ($e->kind === 'activity' && $e->model->attendees->isNotEmpty()) · <i class="bi bi-people"></i> {{ $e->model->attendees->pluck('name')->implode(', ') }}@endif</div>
                                </div>
                                @if ($e->kind === 'activity' && ! $e->done)<a href="{{ route('calendar.ics', $e->model) }}" class="btn btn-light btn-sm ms-auto text-nowrap" title="{{ __('Thêm vào Google / Outlook Calendar (.ics)') }}"><i class="bi bi-calendar-plus"></i> .ics</a>@endif
                            </div>
                        @empty
                            <div class="small text-muted py-1">—</div>
                        @endforelse
                    </div>
                </div>
            @endfor
        </div></div>
    @endif
    <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>{{ __('Lịch hẹn tạo ở trang khách hàng / lead / cơ hội (mục Hoạt động › Lên lịch). Tải tệp .ics để thêm vào Google Calendar hoặc Outlook.') }}</div>
@endsection
