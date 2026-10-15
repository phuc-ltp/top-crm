@extends('layouts.app')
@section('title', __('Hỗ trợ khách hàng'))
@section('content')
    <x-page-header :title="__('Hỗ trợ khách hàng')" :breadcrumbs="['Hỗ trợ' => null]" :subtitle="__('Tiếp nhận và xử lý yêu cầu hỗ trợ')">
        @can('export', App\Models\SupportTicket::class)<a href="{{ route('tickets.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>{{ __('Xuất Excel') }}</a>@endcan
        @can('create', App\Models\SupportTicket::class)<a href="{{ route('tickets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo ticket') }}</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        @foreach (App\Models\SupportTicket::STATUSES as $k => [$label, $color])
            <div class="col-6 col-md-4 col-xl"><a href="{{ route('tickets.index', ['status' => $k]) }}" class="card card-hover p-3 d-block text-reset {{ request('status') === $k ? 'border-warning' : '' }}">
                <div class="small text-muted">{{ __($label) }}</div><div class="fs-4 fw-bold">{{ $counts[$k] ?? 0 }}</div></a></div>
        @endforeach
    </div>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Mã, chủ đề, khách hàng...') }}"></div>
            <select name="status" class="form-select"><option value="">{{ __('Mọi trạng thái') }}</option><option value="active" @selected(request('status') === 'active')>{{ __('Đang mở (chưa giải quyết)') }}</option>
                @foreach (App\Models\SupportTicket::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($s[0]) }}</option>@endforeach</select>
            <select name="priority" class="form-select"><option value="">{{ __('Mọi mức ưu tiên') }}</option>@foreach (App\Models\SupportTicket::PRIORITIES as $k => $s)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ __($s[0]) }}</option>@endforeach</select>
            @can('tickets.view_all')
                <select name="assigned_to" class="form-select"><option value="">{{ __('Mọi nhân viên') }}</option>@foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach</select>
            @endcan
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
        </form>
        @if ($tickets->isEmpty())
            <x-empty-state icon="bi-headset" :title="__('Không có ticket nào')" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th><x-sort-link column="code" :label="__('Mã')" /></th><th><x-sort-link column="subject" :label="__('Chủ đề')" /></th><th>{{ __('labels.customer') }}</th><th><x-sort-link column="priority" :label="__('Ưu tiên')" /></th>
                    <th><x-sort-link column="status" :label="__('Trạng thái')" /></th><th>{{ __('Phụ trách') }}</th><th><x-sort-link column="created_at" :label="__('Tạo lúc')" /></th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                <tbody>
                @foreach ($tickets as $t)
                    <tr>
                        <td class="small text-muted">{{ $t->code }}</td>
                        <td><a href="{{ route('tickets.show', $t) }}" class="row-link">{{ $t->subject }}</a></td>
                        <td class="small">{{ $t->customer?->name }}</td>
                        <td>{!! badge(App\Models\SupportTicket::PRIORITIES, $t->priority) !!}</td>
                        <td>{!! badge(App\Models\SupportTicket::STATUSES, $t->status) !!}</td>
                        <td class="small">{{ $t->assignee?->name ?? __('Chưa giao') }}</td>
                        <td class="small text-muted">{{ vn_date($t->created_at, true) }}</td>
                        <td class="table-actions">
                            @can('update', $t)<a href="{{ route('tickets.edit', $t) }}" class="btn btn-light btn-icon btn-sm"><i class="bi bi-pencil"></i></a>@endcan
                            @can('delete', $t)<x-delete-button :action="route('tickets.destroy', $t)" />@endcan
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$tickets" />
        @endif
    </div>
@endsection
