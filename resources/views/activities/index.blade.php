@extends('layouts.app')
@section('title', __('Hoạt động'))
@section('content')
    <x-page-header :title="__('Hoạt động')" :breadcrumbs="['Hoạt động' => null]"
                   :subtitle="__('Lịch hẹn, cuộc gọi, việc theo dõi với khách hàng, lead và cơ hội')" />

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-exclamation-octagon" tone="red" :label="__('Quá hạn')" :value="$counts['overdue']" :href="route('activities.index', ['status' => 'overdue'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-calendar-day" tone="blue" :label="__('Hôm nay')" :value="$counts['today']" :href="route('activities.index', ['status' => 'today'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-calendar-week" :label="__('7 ngày tới')" :value="$counts['week']" :href="route('activities.index', ['status' => 'upcoming'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-check2-circle" tone="green" :label="__('Đã xong (30 ngày)')" :value="$counts['done']" :href="route('activities.index', ['status' => 'done'])" /></div>
    </div>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="seg" role="group" aria-label="{{ __('Phạm vi') }}">
                <input type="radio" class="btn-check" name="view" value="mine" id="v-mine" @checked($view === 'mine')><label class="btn btn-sm" for="v-mine">{{ __('Của tôi') }}</label>
                <input type="radio" class="btn-check" name="view" value="all" id="v-all" @checked($view === 'all')><label class="btn btn-sm" for="v-all">{{ __('Tất cả tôi được xem') }}</label>
            </div>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tìm theo tiêu đề, nội dung...') }}"></div>
            <select name="status" class="form-select" aria-label="{{ __('Trạng thái') }}">
                <option value="">{{ __('Mọi trạng thái') }}</option>
                @foreach (['overdue' => 'Quá hạn', 'today' => 'Hôm nay', 'upcoming' => 'Sắp tới', 'planned' => 'Đã lên lịch', 'done' => 'Đã hoàn thành'] as $k => $label)
                    <option value="{{ $k }}" @selected($status === $k)>{{ __($label) }}</option>
                @endforeach
            </select>
            <select name="type" class="form-select" aria-label="{{ __('Loại hoạt động') }}">
                <option value="">{{ __('Mọi loại') }}</option>
                @foreach (App\Models\Interaction::TYPES as $k => $t)<option value="{{ $k }}" @selected(request('type') === $k)>{{ __($t[0]) }}</option>@endforeach
            </select>
            <select name="subject_type" class="form-select" aria-label="{{ __('Liên quan') }}">
                <option value="">{{ __('Mọi đối tượng') }}</option>
                @foreach (['customer' => 'Khách hàng', 'contact' => 'Người liên hệ', 'lead' => 'Leads', 'opportunity' => 'Cơ hội'] as $k => $label)
                    <option value="{{ $k }}" @selected(request('subject_type') === $k)>{{ __($label) }}</option>
                @endforeach
            </select>
            @if ($view === 'all')
                <select name="assigned_to" class="form-select" aria-label="{{ __('Người phụ trách') }}"><option value="">{{ __('Mọi nhân viên') }}</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach
                </select>
            @endif
        </form>

        @if ($activities->isEmpty())
            <x-empty-state icon="bi-calendar-check" :title="__('Không có hoạt động nào')"
                           :message="__('Lên lịch cuộc gọi, cuộc hẹn hay việc theo dõi ngay trong trang khách hàng, lead hoặc cơ hội.')" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>{{ __('Hoạt động') }}</th><th>{{ __('Liên quan') }}</th><th>{{ __('Thời điểm') }}</th><th>{{ __('Phụ trách') }}</th><th>{{ __('Trạng thái') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                <tbody>
                @foreach ($activities as $a)
                    <tr>
                        <td>
                            <div class="fw-semibold small"><i class="bi {{ App\Models\Interaction::TYPES[$a->type][1] ?? 'bi-dot' }} me-1 text-orange"></i>{{ label_of(App\Models\Interaction::TYPES, $a->type) }}@if ($a->subject) · {{ $a->subject }}@endif</div>
                            @if ($a->content)<div class="small text-muted text-truncate" style="max-width: 360px">{{ $a->content }}</div>@endif
                            @if ($a->outcome)<div class="small"><span class="text-muted">{{ __('Kết quả') }}:</span> {{ $a->outcome }}</div>@endif
                        </td>
                        <td class="small">
                            @if ($a->subjectUrl())<a href="{{ $a->subjectUrl() }}">{{ $a->subjectLabel() }}</a>@else — @endif
                            <div class="small-xs text-muted">{{ __(['customer' => 'Khách hàng', 'contact' => 'Người liên hệ', 'lead' => 'Lead', 'opportunity' => 'Cơ hội'][$a->interactable_type] ?? '') }}</div>
                        </td>
                        <td class="small text-nowrap {{ $a->isOverdue() ? 'text-danger fw-semibold' : '' }}">
                            {{ vn_date($a->moment(), true) }}
                            @if ($a->isOverdue())<div class="small-xs">{{ __('Quá hạn :time', ['time' => $a->scheduled_at->diffForHumans(null, true)]) }}</div>@endif
                        </td>
                        <td class="small">{{ $a->assignee?->name ?? $a->user?->name ?? '—' }}</td>
                        <td>{!! badge(App\Models\Interaction::STATUSES, $a->status) !!}</td>
                        <td class="table-actions">
                            @if ($a->isPlanned() && $a->completableBy(auth()->user()))
                                <form method="POST" action="{{ route('interactions.complete', $a) }}" class="d-inline">@csrf @method('PATCH')
                                    <button class="btn btn-light btn-icon btn-sm text-success" title="{{ __('Đánh dấu hoàn thành') }}"><i class="bi bi-check2"></i></button></form>
                            @endif
                            @if ($a->subjectUrl())<a href="{{ $a->subjectUrl() }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Mở') }}"><i class="bi bi-box-arrow-up-right"></i></a>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$activities" />
        @endif
    </div>
@endsection
