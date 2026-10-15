@extends('layouts.app')
@section('title', __('Lịch công việc'))
@section('content')
    <x-page-header :title="__('Lịch công việc')" :breadcrumbs="['Công việc' => route('tasks.index'), 'Lịch' => null]">
        <a href="{{ route('tasks.index') }}" class="btn btn-light"><i class="bi bi-list-ul me-1"></i>{{ __('Danh sách') }}</a>
        @can('create', App\Models\Task::class)<a href="{{ route('tasks.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo công việc') }}</a>@endcan
    </x-page-header>
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('tasks.calendar', ['month' => $month->copy()->subMonth()->format('Y-m'), 'mine' => request('mine')]) }}" class="btn btn-light btn-icon btn-sm" aria-label="{{ __('Tháng trước') }}"><i class="bi bi-chevron-left"></i></a>
                <span class="fw-bold">{{ __('Tháng :n', ['n' => $month->format('m/Y')]) }}</span>
                <a href="{{ route('tasks.calendar', ['month' => $month->copy()->addMonth()->format('Y-m'), 'mine' => request('mine')]) }}" class="btn btn-light btn-icon btn-sm" aria-label="{{ __('Tháng sau') }}"><i class="bi bi-chevron-right"></i></a>
                <a href="{{ route('tasks.calendar', ['mine' => request('mine')]) }}" class="btn btn-light btn-sm">{{ __('Hôm nay') }}</a>
            </div>
            <form method="GET"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <div class="form-check"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" @checked(request('mine') === '1') onchange="this.form.submit()"><label class="form-check-label small" for="mine">{{ __('Chỉ việc của tôi') }}</label></div>
            </form>
        </div>
        <div class="card-body">
            @php($colors = ['pending' => '#6B7280', 'in_progress' => '#2563EB', 'completed' => '#16A34A', 'overdue' => '#DC2626', 'cancelled' => '#111827'])
            <div class="calendar">
                @foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $d)<div class="cal-head">{{ $d }}</div>@endforeach
                @for ($day = $start->copy(); $day->lte($end); $day->addDay())
                    <div class="cal-day {{ $day->month !== $month->month ? 'muted' : '' }} {{ $day->isToday() ? 'today' : '' }}">
                        <div class="num">{{ $day->day }}</div>
                        @foreach ($tasks->get($day->format('Y-m-d'), []) as $t)
                            <a href="{{ route('tasks.show', $t) }}" class="cal-event" style="border-left-color: {{ $colors[$t->status] ?? '#FF6B00' }}" title="{{ $t->due_date->format('H:i') }} {{ $t->title }}">{{ $t->due_date->format('H:i') }} {{ $t->title }}</a>
                        @endforeach
                    </div>
                @endfor
            </div>
            <div class="d-flex flex-wrap gap-3 mt-3 small">
                @foreach (App\Models\Task::STATUSES as $k => $s)<span><span class="d-inline-block rounded-1 me-1" style="width:10px;height:10px;background: {{ $colors[$k] }}"></span>{{ __($s[0]) }}</span>@endforeach
            </div>
        </div>
    </div>
@endsection
