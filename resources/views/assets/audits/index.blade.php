@extends('layouts.app')
@section('title', __('Kiểm kê tài sản'))
@section('content')
    <x-page-header :title="__('Kiểm kê tài sản')" :breadcrumbs="['Tài sản' => route('assets.overview'), 'Kiểm kê' => null]"
                   :subtitle="__('Mở một đợt kiểm kê, quét mã QR trên từng tài sản bằng điện thoại để xác nhận có mặt; đóng đợt để chốt danh sách thiếu.')">
        @can('assets.manage')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#auditModal"><i class="bi bi-plus-lg me-1"></i>{{ __('Mở đợt kiểm kê') }}</button>@endcan
    </x-page-header>

    <div class="card">
        @if ($audits->isEmpty())
            <x-empty-state icon="bi-clipboard-check" :title="__('Chưa có đợt kiểm kê nào')" />
        @else
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Đợt kiểm kê') }}</th><th>{{ __('Trạng thái') }}</th><th class="text-end">{{ __('Đã kiểm') }}</th><th class="text-end">{{ __('Hư hỏng') }}</th><th class="text-end">{{ __('Không tìm thấy') }}</th><th>{{ __('Người mở') }}</th></tr></thead>
                <tbody>
                @foreach ($audits as $a)
                    <tr>
                        <td class="fw-semibold"><a href="{{ route('assets.audits.show', $a) }}">{{ $a->name }}</a><div class="small text-muted fw-normal">{{ vn_date($a->created_at, true) }}</div></td>
                        <td>@if ($a->isOpen())<span class="badge badge-soft-warning">{{ __('Đang kiểm') }}</span>@else<span class="badge badge-soft-success">{{ __('Đã đóng') }}</span>@endif</td>
                        <td class="text-end">{{ $a->items_count - $a->missing_count }}@if ($a->isOpen()) / {{ $activeCount }}@endif</td>
                        <td class="text-end">{{ $a->damaged_count }}</td>
                        <td class="text-end {{ $a->missing_count ? 'text-danger fw-semibold' : '' }}">{{ $a->missing_count }}</td>
                        <td class="small">{{ $a->creator?->name ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <x-pagination-bar :paginator="$audits" />
        @endif
    </div>

    @can('assets.manage')
        <div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('assets.audits.store') }}" class="needs-validation" novalidate>@csrf
                <div class="modal-header"><h5 class="modal-title">{{ __('Mở đợt kiểm kê') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
                <div class="modal-body">
                    <x-input name="name" :label="__('Tên đợt kiểm kê')" required maxlength="255" :value="__('Kiểm kê tháng :m/:y', ['m' => now()->month, 'y' => now()->year])" />
                    <x-textarea name="note" :label="__('Ghi chú')" maxlength="1000" rows="2" col="mb-0" />
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Mở đợt') }}</button></div>
            </form>
        </div></div></div>
    @endcan
@endsection
