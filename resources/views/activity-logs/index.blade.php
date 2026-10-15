@extends('layouts.app')
@section('title', __('Nhật ký hoạt động'))
@section('content')
    <x-page-header :title="__('Nhật ký hoạt động')" :breadcrumbs="['Nhật ký hoạt động' => null]" :subtitle="__('Ai đã làm gì, lúc nào, trên dữ liệu nào (không ghi mật khẩu, token)')" />
    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tìm trong mô tả...') }}"></div>
            <select name="user_id" class="form-select"><option value="">{{ __('Mọi người dùng') }}</option>@foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('user_id') == $id)>{{ $name }}</option>@endforeach</select>
            <select name="action" class="form-select"><option value="">{{ __('Mọi hành động') }}</option>@foreach (App\Models\ActivityLog::ACTIONS as $k => $a)<option value="{{ $k }}" @selected(request('action') === $k)>{{ __($a[0]) }}</option>@endforeach</select>
            <select name="subject_type" class="form-select"><option value="">{{ __('Mọi đối tượng') }}</option>@foreach ($types as $t)<option value="{{ $t }}" @selected(request('subject_type') === $t)>{{ $t }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="{{ __('Từ ngày') }}">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="{{ __('Đến ngày') }}">
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
        </form>
        @if ($logs->isEmpty())
            <x-empty-state icon="bi-clock-history" :title="__('Không có nhật ký phù hợp')" />
        @else
            <div class="table-responsive"><table class="table table-hover table-sm">
                <thead><tr><th>{{ __('Thời điểm') }}</th><th>{{ __('Người thực hiện') }}</th><th>{{ __('Hành động') }}</th><th>{{ __('Mô tả') }}</th><th>{{ __('Đối tượng') }}</th><th>IP</th><th></th></tr></thead>
                <tbody>
                @foreach ($logs as $l)
                    <tr>
                        <td class="small text-nowrap">{{ vn_date($l->created_at, true) }}</td>
                        <td class="small">{{ $l->user?->name ?? __('Hệ thống') }}</td>
                        <td>{!! badge(App\Models\ActivityLog::ACTIONS, $l->action) !!}</td>
                        <td class="small">{{ $l->description }}</td>
                        <td class="small text-muted">{{ $l->subject_type ? $l->subject_type.' #'.$l->subject_id : '—' }}</td>
                        <td class="small text-muted">{{ $l->ip_address }}</td>
                        <td>@if ($l->properties)<a href="{{ route('activity-logs.show', $l) }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Xem chi tiết thay đổi') }}"><i class="bi bi-eye"></i></a>@endif</td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$logs" />
        @endif
    </div>
@endsection
