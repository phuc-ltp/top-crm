@extends('layouts.app')
@section('title', $audit->name)
@section('content')
    @php($manage = auth()->user()->can('assets.manage') && $audit->isOpen())
    @php($R = App\Models\AssetAudit::RESULTS)
    <x-page-header :title="$audit->name" :breadcrumbs="['Kiểm kê' => route('assets.audits.index'), $audit->name => null]"
                   :subtitle="$audit->isOpen() ? __('Đang kiểm kê — mở trang tài sản (quét QR) để xác nhận nhanh, hoặc đánh dấu trong danh sách bên dưới.') : __('Đã đóng lúc :t.', ['t' => vn_date($audit->closed_at, true)])">
        @if ($manage)
            <form method="POST" action="{{ route('assets.audits.close', $audit) }}" data-confirm="{{ __('Đóng đợt kiểm kê? :n tài sản chưa kiểm sẽ được ghi là không tìm thấy.', ['n' => $pending->count()]) }}" data-confirm-button="{{ __('Đóng đợt') }}">@csrf
                <button class="btn btn-primary"><i class="bi bi-lock me-1"></i>{{ __('Đóng đợt kiểm kê') }}</button></form>
        @endif
    </x-page-header>

    <div class="row g-3 mb-3">
        @foreach ($R as $k => $r)
            <div class="col-4 col-lg-3"><x-stat-card icon="bi-clipboard-check" :tone="['found' => 'green', 'damaged' => 'orange', 'missing' => 'red'][$k]" :label="__($r[0])" :value="$items->where('result', $k)->count()" /></div>
        @endforeach
        @if ($audit->isOpen())<div class="col-12 col-lg-3"><x-stat-card icon="bi-hourglass-split" tone="blue" :label="__('Chưa kiểm')" :value="$pending->count()" /></div>@endif
    </div>

    @if ($pending->isNotEmpty())
        <div class="card mb-3">
            <div class="card-body pb-0"><h6>{{ __('Chưa kiểm (:n)', ['n' => $pending->count()]) }}</h6></div>
            <div class="table-responsive"><table class="table align-middle mb-0 small">
                <tbody>
                @foreach ($pending as $a)
                    <tr>
                        <td class="fw-semibold text-nowrap"><a href="{{ route('assets.show', $a) }}">{{ $a->code }}</a></td>
                        <td>{{ $a->name }}<div class="text-muted">{{ collect([$a->holder?->name, $a->location])->filter()->join(' · ') }}</div></td>
                        <td class="text-end text-nowrap">
                            @if ($manage)
                                <form method="POST" action="{{ route('assets.audits.check', $audit) }}" class="d-inline">@csrf<input type="hidden" name="asset_id" value="{{ $a->id }}">
                                    <button name="result" value="found" class="btn btn-sm btn-success">{{ __('Có mặt') }}</button>
                                    <button name="result" value="damaged" class="btn btn-sm btn-outline-warning">{{ __('Hư hỏng') }}</button>
                                    <button name="result" value="missing" class="btn btn-sm btn-outline-danger">{{ __('Không thấy') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endif

    <div class="card">
        <div class="card-body pb-0"><h6>{{ __('Đã kiểm (:n)', ['n' => $items->count()]) }}</h6></div>
        @if ($items->isEmpty())
            <x-empty-state icon="bi-clipboard" :title="__('Chưa kiểm tài sản nào')" />
        @else
            <div class="table-responsive"><table class="table align-middle mb-0 small">
                <thead><tr><th>{{ __('Mã') }}</th><th>{{ __('Tài sản') }}</th><th>{{ __('Kết quả') }}</th><th>{{ __('Người kiểm') }}</th></tr></thead>
                <tbody>
                @foreach ($items->sortBy(fn ($i) => $i->asset?->code) as $i)
                    <tr>
                        <td class="fw-semibold text-nowrap">@if ($i->asset && ! $i->asset->trashed())<a href="{{ route('assets.show', $i->asset) }}">{{ $i->asset->code }}</a>@else{{ $i->asset?->code }}@endif</td>
                        <td>{{ $i->asset?->name }}@if ($i->note)<div class="text-muted">{{ $i->note }}</div>@endif</td>
                        <td>{!! badge($R, $i->result) !!}</td>
                        <td class="text-nowrap">{{ $i->checker?->name }}<div class="text-muted">{{ vn_date($i->checked_at, true) }}</div></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
@endsection
