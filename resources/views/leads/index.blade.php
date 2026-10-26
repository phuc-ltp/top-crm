@extends('layouts.app')
@section('title', 'Leads')
@section('content')
    <x-page-header title="Leads" :breadcrumbs="['Leads' => null]" :subtitle="__('Khách hàng tiềm năng — chấm điểm, phân công và chuyển đổi')">
        <a href="{{ route('leads.analytics') }}" class="btn btn-light"><i class="bi bi-graph-up me-1"></i>{{ __('Phân tích') }}</a>
        @can('settings.manage')
            <a href="{{ route('settings.leads') }}" class="btn btn-light"><i class="bi bi-sliders me-1"></i>{{ __('Cấu hình lead') }}</a>
        @endcan
        @can('export', App\Models\Lead::class)
            <a href="{{ route('leads.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>{{ __('Xuất Excel') }}</a>
        @endcan
        @can('create', App\Models\Lead::class)
            <a href="{{ route('leads.import') }}" class="btn btn-light"><i class="bi bi-upload me-1"></i>{{ __('Nhập CSV / Excel') }}</a>
            <a href="{{ route('leads.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo lead') }}</a>
        @endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        @foreach (App\Models\Lead::statuses() as $key => [$label, $color])
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('leads.index', ['status' => $key]) }}" class="card card-hover p-3 d-block text-reset {{ request('status') === $key ? 'border-warning' : '' }}">
                    <div class="small text-muted">{{ __($label) }}</div>
                    <div class="fs-4 fw-bold">{{ $summary[$key] ?? 0 }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên, mã, email, công ty...') }}">
            </div>
            <select name="status" class="form-select"><option value="">{{ __('Mọi trạng thái') }}</option>
                @foreach (App\Models\Lead::statuses() as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($s[0]) }}</option>@endforeach
            </select>
            <select name="source" class="form-select"><option value="">{{ __('Mọi nguồn') }}</option>
                @foreach ($sources as $id => $name)<option value="{{ $id }}" @selected(request('source') == $id)>{{ $name }}</option>@endforeach
            </select>
            <select name="score" class="form-select"><option value="">{{ __('Mọi mức điểm') }}</option>
                <option value="hot" @selected(request('score') === 'hot')>{{ __('Nóng') }} (≥70)</option>
                <option value="warm" @selected(request('score') === 'warm')>{{ __('Ấm') }} (40–69)</option>
                <option value="cold" @selected(request('score') === 'cold')>{{ __('Lạnh') }} (&lt;40)</option>
            </select>
            <select name="campaign" class="form-select"><option value="">{{ __('Mọi chiến dịch') }}</option>
                @foreach ($campaigns as $id => $name)<option value="{{ $id }}" @selected(request('campaign') == $id)>{{ $name }}</option>@endforeach
            </select>
            @can('leads.view_all')
                <select name="assigned_to" class="form-select"><option value="">{{ __('Mọi nhân viên') }}</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach
                </select>
            @endcan
            <select name="follow_up" class="form-select" aria-label="{{ __('Theo dõi tiếp') }}"><option value="">{{ __('Mọi lịch theo dõi') }}</option>
                <option value="overdue" @selected(request('follow_up') === 'overdue')>{{ __('Theo dõi quá hạn') }}</option>
                <option value="today" @selected(request('follow_up') === 'today')>{{ __('Theo dõi hôm nay') }}</option>
                <option value="none" @selected(request('follow_up') === 'none')>{{ __('Đang mở, chưa có lịch theo dõi') }}</option>
            </select>
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
            @if (request()->hasAny(['q', 'status', 'source', 'score', 'campaign', 'assigned_to', 'follow_up']))<a href="{{ route('leads.index') }}" class="btn btn-link text-muted">{{ __('Xóa lọc') }}</a>@endif
        </form>

        @if ($leads->isEmpty())
            <x-empty-state icon="bi-funnel" :title="__('Không có lead nào')" :message="__('Thay đổi bộ lọc hoặc tạo lead mới.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr>
                        <th><x-sort-link column="code" :label="__('Mã')" /></th>
                        <th><x-sort-link column="name" label="Lead" /></th>
                        <th>{{ __('Nguồn') }}</th>
                        <th><x-sort-link column="score" :label="__('Điểm')" /></th>
                        <th class="text-end"><x-sort-link column="estimated_value" :label="__('Giá trị ước tính')" /></th>
                        <th><x-sort-link column="status" :label="__('Giai đoạn')" /></th>
                        <th>{{ __('Theo dõi tiếp') }}</th>
                        <th>{{ __('Phụ trách') }}</th>
                        <th class="text-end">{{ __('Thao tác') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($leads as $l)
                        <tr>
                            <td class="small text-muted">{{ $l->code }}</td>
                            <td><a href="{{ route('leads.show', $l) }}" class="row-link">{{ $l->name }}</a><x-contact-qr type="lead" :id="$l->id" :phone="$l->phone" /><div class="small text-muted">{{ $l->company }}</div></td>
                            <td class="small">{{ $l->source?->label ?? '—' }}</td>
                            <td style="min-width:110px">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar" style="width: {{ $l->score }}%"></div></div>
                                    <span class="small fw-semibold">{{ $l->score }}</span>
                                </div>
                                <span class="badge badge-soft-{{ $l->score_level[1] }} mt-1">{{ $l->score_level[0] }}</span>
                            </td>
                            <td class="text-end small fw-semibold">{{ money($l->estimated_value) }}</td>
                            <td>{!! badge(App\Models\Lead::statuses(), $l->status) !!}</td>
                            @php($next = $l->next_follow_up ? \Illuminate\Support\Carbon::parse($l->next_follow_up) : null)
                            <td class="small text-nowrap {{ $next?->isPast() ? 'text-danger fw-semibold' : '' }}">{{ $next ? vn_date($next, true) : '—' }}</td>
                            <td class="small">{{ $l->assignee?->name ?? '—' }}</td>
                            <td class="table-actions">
                                @if (! $l->isConverted())
                                    @can('update', $l)<a href="{{ route('leads.convert', $l) }}" class="btn btn-soft-primary btn-sm" title="{{ __('Chuyển đổi') }}"><i class="bi bi-arrow-repeat"></i></a>@endcan
                                @endif
                                @can('update', $l)<a href="{{ route('leads.edit', $l) }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></a>@endcan
                                @can('delete', $l)<x-delete-button :action="route('leads.destroy', $l)" />@endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination-bar :paginator="$leads" />
        @endif
    </div>
@endsection
