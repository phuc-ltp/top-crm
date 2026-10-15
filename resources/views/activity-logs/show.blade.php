@extends('layouts.app')
@section('title', __('Chi tiết nhật ký'))
@section('content')
    <x-page-header :title="__('Chi tiết nhật ký')" :breadcrumbs="['Nhật ký hoạt động' => route('activity-logs.index'), '#'.$log->id => null]" />
    <div class="card" style="max-width: 1000px"><div class="card-body">
        <dl class="detail-list mb-4">
            <dt>{{ __('Thời điểm') }}</dt><dd>{{ vn_date($log->created_at, true) }}</dd>
            <dt>{{ __('Người thực hiện') }}</dt><dd>{{ $log->user?->name ?? __('Hệ thống') }}</dd>
            <dt>{{ __('Hành động') }}</dt><dd>{!! badge(App\Models\ActivityLog::ACTIONS, $log->action) !!}</dd>
            <dt>{{ __('Mô tả') }}</dt><dd>{{ $log->description }}</dd>
            <dt>{{ __('Đối tượng') }}</dt><dd>{{ $log->subject_type }} #{{ $log->subject_id }}</dd>
            <dt>{{ __('IP / Trình duyệt') }}</dt><dd class="small">{{ $log->ip_address }} · {{ $log->user_agent }}</dd>
        </dl>
        @php($props = $log->properties ?? [])
        @if (isset($props['old']) || isset($props['new']))
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>{{ __('Trường') }}</th><th>{{ __('Giá trị cũ') }}</th><th>{{ __('Giá trị mới') }}</th></tr></thead>
                <tbody>
                @foreach (array_keys(($props['new'] ?? []) + ($props['old'] ?? [])) as $field)
                    <tr><td class="fw-semibold small">{{ $field }}</td>
                        <td class="small text-danger">{{ is_scalar($props['old'][$field] ?? null) ? $props['old'][$field] : json_encode($props['old'][$field] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                        <td class="small text-success">{{ is_scalar($props['new'][$field] ?? null) ? $props['new'][$field] : json_encode($props['new'][$field] ?? null, JSON_UNESCAPED_UNICODE) }}</td></tr>
                @endforeach
                </tbody></table></div>
        @elseif ($props)
            <pre class="bg-light p-3 rounded small mb-0">{{ json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div></div>
@endsection
