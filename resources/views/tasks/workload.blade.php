@extends('layouts.app')
@section('title', __('Khối lượng công việc'))
@section('content')
    <x-page-header :title="__('Khối lượng công việc')" :breadcrumbs="['Công việc' => route('tasks.index'), 'Khối lượng' => null]" :subtitle="__('Ai đang giữ bao nhiêu việc, việc nào trễ, việc nào sắp đến hạn')" />

    <div class="card mb-3">
        <form method="GET" class="filter-bar" data-auto-submit>
            <select name="project_id" class="form-select" aria-label="{{ __('Dự án') }}"><option value="">{{ __('Mọi dự án') }}</option>
                @foreach ($projects as $id => $name)<option value="{{ $id }}" @selected(request('project_id') == $id)>{{ $name }}</option>@endforeach
            </select>
            @if ($unassigned)
                <a href="{{ route('tasks.index', array_filter(['status' => 'open', 'project_id' => request('project_id')])) }}" class="small text-warning-emphasis ms-auto"><i class="bi bi-person-dash me-1"></i>{{ __(':count việc đang mở chưa giao cho ai', ['count' => $unassigned]) }}</a>
            @endif
        </form>
    </div>

    <div class="card">
        @if ($rows->isEmpty())
            <x-empty-state icon="bi-people" :title="__('Chưa có dữ liệu')" />
        @else
            <div class="table-responsive"><table class="table mb-0 align-middle">
                <thead><tr>
                    <th>{{ __('Nhân viên') }}</th><th style="min-width: 220px">{{ __('Việc đang mở') }}</th>
                    <th class="text-end">{{ __('Đang làm') }}</th><th class="text-end">{{ __('Trễ hạn') }}</th><th class="text-end">{{ __('Đến hạn 7 ngày tới') }}</th>
                    <th class="text-end">{{ __('Giờ ước tính') }}</th><th class="text-end">{{ __('Xong 30 ngày') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td class="small fw-semibold"><a href="{{ route('tasks.index', array_filter(['assigned_to' => $r->id, 'status' => 'open', 'project_id' => request('project_id')])) }}" class="row-link">{{ $r->name }}</a></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="workload-bar flex-grow-1" title="{{ __('Trễ hạn') }}: {{ $r->late }} · {{ __('Còn hạn') }}: {{ $r->open - $r->late }}">
                                    <span style="width: {{ $r->late / $max * 100 }}%; background: var(--crm-danger)"></span>
                                    <span style="width: {{ ($r->open - $r->late) / $max * 100 }}%; background: var(--crm-primary)"></span>
                                </div>
                                <span class="small fw-semibold" style="min-width: 2ch">{{ $r->open }}</span>
                            </div>
                        </td>
                        <td class="text-end small">{{ $r->doing }}</td>
                        <td class="text-end small {{ $r->late ? 'text-danger fw-semibold' : '' }}">{{ $r->late }}</td>
                        <td class="text-end small">{{ $r->due_week }}</td>
                        <td class="text-end small">{{ rtrim(rtrim(number_format($r->hours, 1, ',', '.'), '0'), ',') }}</td>
                        <td class="text-end small text-success">{{ $r->done }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body small text-muted border-top">{{ __('Việc đang mở = chờ xử lý, đang thực hiện hoặc quá hạn. Trễ hạn = đang mở và đã qua hạn chót. Giờ ước tính = tổng giờ ước tính của việc đang mở.') }}</div>
        @endif
    </div>
@endsection
