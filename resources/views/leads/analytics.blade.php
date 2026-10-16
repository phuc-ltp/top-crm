@extends('layouts.app')
@section('title', __('Phân tích lead'))
@section('content')
    @php($periods = ['this_month' => __('Tháng này'), 'last_month' => __('Tháng trước'), 'last_30' => __('30 ngày qua'), 'this_quarter' => __('Quý này'), 'this_year' => __('Năm nay'), 'custom' => __('Tùy chọn...')])
    <x-page-header :title="__('Phân tích lead')" :breadcrumbs="['Leads' => route('leads.index'), 'Phân tích' => null]"
                   :subtitle="__('Lead tạo từ :from đến :to (trong phạm vi bạn được xem).', ['from' => vn_date($from), 'to' => vn_date($to)])">
        <form method="GET" class="d-flex flex-wrap gap-2" data-auto-submit>
            <select name="period" class="form-select" aria-label="{{ __('Kỳ') }}">@foreach ($periods as $k => $label)<option value="{{ $k }}" @selected($period === $k)>{{ $label }}</option>@endforeach</select>
            @if ($period === 'custom')
                <input type="date" name="from" value="{{ request('from', $from->toDateString()) }}" class="form-control" aria-label="{{ __('Từ ngày') }}">
                <input type="date" name="to" value="{{ request('to', $to->toDateString()) }}" class="form-control" aria-label="{{ __('Đến ngày') }}">
            @endif
        </form>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg"><x-stat-card icon="bi-funnel" :label="__('Lead mới')" :value="$kpi['total']" /></div>
        <div class="col-6 col-lg"><x-stat-card icon="bi-patch-check" tone="yellow" :label="__('Đủ điều kiện')" :value="$kpi['qualified']" :sub="$kpi['qualified_rate'].'%'" /></div>
        <div class="col-6 col-lg"><x-stat-card icon="bi-stars" tone="green" :label="__('Đã chuyển đổi')" :value="$kpi['converted']" :sub="__(':rate% tỷ lệ chuyển đổi', ['rate' => $kpi['conversion_rate']])" /></div>
        <div class="col-6 col-lg"><x-stat-card icon="bi-x-octagon" tone="red" :label="__('Thất bại')" :value="$kpi['lost']" :sub="$kpi['lost_rate'].'%'" /></div>
        <div class="col-12 col-lg"><x-stat-card icon="bi-hourglass-split" tone="blue" :label="__('Thời gian chuyển đổi TB')" :value="$kpi['avg_days'] !== null ? __(':days ngày', ['days' => $kpi['avg_days']]) : '—'" :sub="__(':count lead đang mở', ['count' => $kpi['open']])" /></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card h-100"><div class="card-header">{{ __('Lead mới theo tuần (12 tuần)') }}</div><div class="card-body">
                @php($weeklyChart = ['type' => 'bar', 'data' => ['labels' => $weekly['labels'], 'datasets' => [['label' => __('Lead mới'), 'data' => $weekly['counts'], 'backgroundColor' => '#E8833A', 'borderRadius' => 6]]],
                    'options' => ['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]]])
                <div class="chart-box"><canvas id="weeklyChart" data-chart="{{ json_encode($weeklyChart) }}"></canvas></div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100"><div class="card-header">{{ __('Theo giai đoạn hiện tại') }}</div><div class="card-body">
                @if ($kpi['total'] === 0)
                    <x-empty-state icon="bi-funnel" :title="__('Chưa có lead trong kỳ')" />
                @else
                    @foreach ($byStage as $s)
                        <div class="d-flex justify-content-between small mb-1"><span>{{ $s['label'] }}</span><span class="fw-semibold">{{ $s['count'] }}</span></div>
                        <div class="progress mb-3" style="height:8px" role="progressbar" aria-label="{{ $s['label'] }}" aria-valuenow="{{ $s['count'] }}" aria-valuemin="0" aria-valuemax="{{ $kpi['total'] }}">
                            <div class="progress-bar bg-{{ ['orange' => 'warning', 'purple' => 'info', 'secondary' => 'secondary', 'primary' => 'primary'][$s['color']] ?? $s['color'] }}" style="width: {{ $kpi['total'] ? round($s['count'] / $kpi['total'] * 100) : 0 }}%"></div>
                        </div>
                    @endforeach
                @endif
            </div></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4"><div class="card-header">{{ __('Theo nguồn') }}</div>
                <div class="table-responsive"><table class="table table-hover mb-0">
                    <thead><tr><th>{{ __('Nguồn') }}</th><th class="text-end">{{ __('Lead') }}</th><th class="text-end">{{ __('Chuyển đổi') }}</th><th class="text-end">{{ __('Tỷ lệ') }}</th><th class="text-end">{{ __('Giá trị ước tính') }}</th></tr></thead>
                    <tbody>
                    @forelse ($bySource as $r)
                        <tr><td>{{ $r['label'] }}</td><td class="text-end">{{ $r['total'] }}</td><td class="text-end">{{ $r['converted'] }}</td><td class="text-end fw-semibold">{{ $r['rate'] }}%</td><td class="text-end small">{{ money($r['value']) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-muted small">{{ __('Chưa có dữ liệu.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
            @if ($byOwner->isNotEmpty())
                <div class="card"><div class="card-header">{{ __('Theo nhân viên phụ trách') }}</div>
                    <div class="table-responsive"><table class="table table-hover mb-0">
                        <thead><tr><th>{{ __('Nhân viên') }}</th><th class="text-end">{{ __('Lead') }}</th><th class="text-end">{{ __('Chuyển đổi') }}</th><th class="text-end">{{ __('Tỷ lệ') }}</th></tr></thead>
                        <tbody>@foreach ($byOwner as $r)<tr><td>{{ $r['label'] }}</td><td class="text-end">{{ $r['total'] }}</td><td class="text-end">{{ $r['converted'] }}</td><td class="text-end fw-semibold">{{ $r['rate'] }}%</td></tr>@endforeach</tbody>
                    </table></div>
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            <div class="card mb-4"><div class="card-header">{{ __('Lý do thất bại') }}</div><div class="card-body">
                @forelse ($byReason as $r)
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span>{{ $r['label'] }}</span><span class="fw-semibold">{{ $r['count'] }}</span></div>
                @empty
                    <div class="text-muted small">{{ __('Không có lead thất bại trong kỳ.') }}</div>
                @endforelse
            </div></div>
            <div class="card"><div class="card-header"><span><i class="bi bi-info-circle me-1 text-orange"></i>{{ __('Cách tính') }}</span></div><div class="card-body small text-muted">
                <ul class="ps-3 mb-0">
                    <li>{{ __('Tất cả chỉ số tính trên lead được TẠO trong kỳ đã chọn.') }}</li>
                    <li>{{ __('Đủ điều kiện = lead đã từng vào giai đoạn đủ điều kiện hoặc đã chuyển đổi.') }}</li>
                    <li>{{ __('Tỷ lệ chuyển đổi = lead đã chuyển đổi / tổng lead.') }}</li>
                    <li>{{ __('Thời gian chuyển đổi TB = trung bình (ngày chuyển đổi − ngày tạo) của lead đã chuyển đổi.') }}</li>
                </ul>
            </div></div>
        </div>
    </div>
@endsection
