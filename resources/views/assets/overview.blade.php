@extends('layouts.app')
@section('title', __('Tổng quan tài sản'))
@section('content')
    @php($S = App\Models\Asset::STATUSES)
    <x-page-header :title="__('Tổng quan tài sản')" :breadcrumbs="['Tài sản' => null]" :subtitle="__('Giá trị còn lại tính theo khấu hao đường thẳng đến hôm nay; không gồm tài sản đã thanh lý.')">
        <a href="{{ route('assets.index') }}" class="btn btn-light"><i class="bi bi-list-ul me-1"></i>{{ __('Danh mục tài sản') }}</a>
        @can('assets.manage')<a href="{{ route('assets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm tài sản') }}</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-pc-display" tone="blue" :label="__('Tài sản đang quản lý')" :value="$o['total']" :href="route('assets.index')" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-cash-coin" :label="__('Tổng nguyên giá')" :value="money($o['cost'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-graph-down" tone="green" :label="__('Giá trị còn lại')" :value="money($o['book'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-tools" tone="orange" :label="__('Chi phí bảo trì năm nay')" :value="money($o['maintenance_cost'])" /></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5"><div class="card h-100"><div class="card-body">
            <h6 class="mb-3">{{ __('Theo trạng thái') }}</h6>
            @php($max = max(1, $o['by_status']->max()))
            @foreach ($o['by_status'] as $k => $n)
                <a href="{{ route('assets.index', ['status' => $k]) }}" class="d-block text-reset text-decoration-none mb-2">
                    <div class="d-flex justify-content-between small"><span>{!! badge($S, $k) !!}</span><span class="fw-semibold">{{ $n }}</span></div>
                    <div class="progress mt-1" style="height: 6px" role="progressbar" aria-label="{{ label_of($S, $k) }}" aria-valuenow="{{ $n }}" aria-valuemin="0" aria-valuemax="{{ $max }}"><div class="progress-bar bg-{{ $S[$k][1] }}" style="width: {{ round($n / $max * 100) }}%"></div></div>
                </a>
            @endforeach
        </div></div></div>

        <div class="col-lg-7"><div class="card h-100">
            <div class="card-body pb-0 flex-grow-0"><h6>{{ __('Theo nhóm tài sản') }}</h6></div>
            @if ($o['by_category']->isEmpty())
                <x-empty-state icon="bi-diagram-3" :title="__('Chưa có tài sản')" />
            @else
                <div class="table-responsive"><table class="table align-middle mb-0 small">
                    <thead><tr><th>{{ __('Nhóm') }}</th><th class="text-end">{{ __('Số lượng') }}</th><th class="text-end">{{ __('Nguyên giá') }}</th><th class="text-end">{{ __('Giá trị còn lại') }}</th></tr></thead>
                    <tbody>@foreach ($o['by_category'] as $name => $row)<tr><td>{{ $name }}</td><td class="text-end">{{ $row['count'] }}</td><td class="text-end text-nowrap">{{ money($row['cost']) }}</td><td class="text-end text-nowrap">{{ money($row['book']) }}</td></tr>@endforeach</tbody>
                </table></div>
            @endif
        </div></div>

        <div class="col-lg-6"><div class="card h-100"><div class="card-body">
            <h6 class="mb-3"><i class="bi bi-alarm text-danger me-1"></i>{{ __('Mượn quá hạn') }}</h6>
            @forelse ($o['overdue'] as $a)
                <div class="d-flex justify-content-between small border-bottom py-2"><a href="{{ route('assets.show', $a) }}">{{ $a->code }} — {{ $a->name }}</a><span class="text-danger text-nowrap ms-2">{{ vn_date($a->due_back_at) }}</span></div>
            @empty
                <div class="small text-muted">{{ __('Không có tài sản mượn quá hạn.') }}</div>
            @endforelse
        </div></div></div>

        <div class="col-lg-6"><div class="card h-100"><div class="card-body">
            <h6 class="mb-3"><i class="bi bi-shield-exclamation text-warning me-1"></i>{{ __('Sắp hết bảo hành (30 ngày)') }}</h6>
            @forelse ($o['warranty'] as $a)
                <div class="d-flex justify-content-between small border-bottom py-2"><a href="{{ route('assets.show', $a) }}">{{ $a->code }} — {{ $a->name }}</a><span class="text-nowrap ms-2">{{ vn_date($a->warranty_until) }}</span></div>
            @empty
                <div class="small text-muted">{{ __('Không có tài sản sắp hết bảo hành.') }}</div>
            @endforelse
        </div></div></div>
    </div>
@endsection
