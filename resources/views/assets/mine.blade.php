@extends('layouts.app')
@section('title', __('Tài sản của tôi'))
@section('content')
    <x-page-header :title="__('Tài sản của tôi')" :breadcrumbs="['Tài sản của tôi' => null]" :subtitle="__('Thiết bị, dụng cụ công ty đang bàn giao hoặc cho bạn mượn.')" />

    <div class="card">
        @if ($assets->isEmpty())
            <x-empty-state icon="bi-pc-display" :title="__('Bạn chưa giữ tài sản nào')" />
        @else
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Mã') }}</th><th>{{ __('Tên tài sản') }}</th><th>{{ __('Nhóm') }}</th><th>{{ __('Trạng thái') }}</th><th>{{ __('Hạn trả') }}</th></tr></thead>
                <tbody>
                @foreach ($assets as $a)
                    <tr>
                        <td class="fw-semibold text-nowrap"><a href="{{ route('assets.show', $a) }}">{{ $a->code }}</a></td>
                        <td>{{ $a->name }}@if ($a->serial_number)<div class="small text-muted">S/N {{ $a->serial_number }}</div>@endif</td>
                        <td class="small">{{ $a->category?->name ?? '—' }}</td>
                        <td>{!! badge(App\Models\Asset::STATUSES, $a->status) !!}</td>
                        <td class="small {{ $a->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $a->due_back_at ? vn_date($a->due_back_at) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    @if ($licenses->isNotEmpty())
        <div class="card mt-3">
            <div class="card-body pb-0"><h6>{{ __('Phần mềm được cấp quyền sử dụng') }}</h6></div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <tbody>
                @foreach ($licenses as $a)
                    <tr>
                        <td><i class="bi bi-key me-1"></i>{{ $a->license->name }}@if ($a->license->edition)<span class="text-muted small"> · {{ $a->license->edition }}</span>@endif</td>
                        <td class="small text-nowrap">{{ $a->license->expires_at ? __('Hết hạn :d', ['d' => vn_date($a->license->expires_at)]) : __('Không thời hạn') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top small text-muted py-2">{{ __('Cần khóa kích hoạt? Liên hệ người quản lý bản quyền của công ty.') }}</div>
        </div>
    @endif
@endsection
