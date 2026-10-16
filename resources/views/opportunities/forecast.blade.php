@extends('layouts.app')
@section('title', __('Dự báo doanh thu'))
@section('content')
    <x-page-header :title="__('Dự báo doanh thu')" :breadcrumbs="['Pipeline' => route('opportunities.index'), 'Dự báo' => null]"
                   :subtitle="__('Theo tháng dự kiến chốt của cơ hội, trong phạm vi bạn được xem.')">
        <form method="GET" class="d-flex flex-wrap gap-2" data-auto-submit>
            @if ($pipelines->count() > 1)
                <select name="pipeline" class="form-select" aria-label="{{ __('Pipeline') }}"><option value="">{{ __('Mọi pipeline') }}</option>
                    @foreach ($pipelines as $id => $name)<option value="{{ $id }}" @selected($pipelineId == $id)>{{ __($name) }}</option>@endforeach</select>
            @endif
            <select name="months" class="form-select" aria-label="{{ __('Số tháng') }}">
                @foreach ([3, 6, 12] as $m)<option value="{{ $m }}" @selected($months === $m)>{{ __(':count tháng tới', ['count' => $m]) }}</option>@endforeach
            </select>
        </form>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg"><x-stat-card icon="bi-kanban" :label="__('Pipeline dự kiến chốt')" :value="money_short($totals['pipeline'])" /></div>
        <div class="col-6 col-lg"><x-stat-card icon="bi-percent" tone="blue" :label="__('Giá trị có trọng số')" :value="money_short($totals['weighted'])" /></div>
        <div class="col-6 col-lg"><x-stat-card icon="bi-trophy" tone="green" :label="__('Đã thắng')" :value="money_short($totals['won'])" /></div>
        <div class="col-6 col-lg"><x-stat-card icon="bi-graph-up-arrow" tone="purple" :label="__('Dự báo')" :value="money_short($totals['forecast'])"
                   :sub="$totals['attainment'] !== null ? __(':rate% chỉ tiêu (:months tháng có chỉ tiêu)', ['rate' => $totals['attainment'], 'months' => $totals['target_months']]) : __('Chưa đặt chỉ tiêu')" /></div>
        <div class="col-12 col-lg"><x-stat-card icon="bi-exclamation-triangle" tone="red" :label="__('Quá ngày dự kiến chốt')" :value="$overdue['count']" :sub="money_short($overdue['value'])" /></div>
    </div>

    <div class="card mb-4"><div class="card-header">{{ __('Dự báo so với chỉ tiêu') }}</div><div class="card-body">
        @php($chart = ['type' => 'bar', 'data' => ['labels' => $rows->pluck('label'), 'datasets' => [
            ['label' => __('Đã thắng'), 'data' => $rows->pluck('won'), 'backgroundColor' => '#16A34A', 'stack' => 'f', 'borderRadius' => 4],
            ['label' => __('Trọng số đang mở'), 'data' => $rows->pluck('weighted'), 'backgroundColor' => '#E8833A', 'stack' => 'f', 'borderRadius' => 4],
            ['label' => __('Chỉ tiêu'), 'data' => $rows->map(fn ($r) => $r['target'] > 0 ? $r['target'] : null), 'type' => 'line', 'spanGaps' => false, 'borderColor' => '#6366F1', 'backgroundColor' => '#6366F1', 'tension' => .3, 'pointRadius' => 4],
        ]], 'options' => ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true]], 'plugins' => ['legend' => ['position' => 'bottom']], 'money' => true]])
        <div class="chart-box"><canvas id="forecastChart" data-chart="{{ json_encode($chart) }}"></canvas></div>
    </div></div>

    <div class="card mb-4">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>{{ __('Tháng') }}</th><th class="text-end">{{ __('Số cơ hội') }}</th><th class="text-end">{{ __('Pipeline') }}</th><th class="text-end">{{ __('Trọng số') }}</th>
                <th class="text-end">{{ __('Đã thắng') }}</th><th class="text-end">{{ __('Dự báo') }}</th><th class="text-end">{{ __('Chỉ tiêu') }}</th><th class="text-end">{{ __('Đạt') }}</th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td class="fw-semibold">{{ $r['label'] }}</td><td class="text-end">{{ $r['count'] }}</td><td class="text-end">{{ money($r['pipeline']) }}</td>
                    <td class="text-end">{{ money($r['weighted']) }}</td><td class="text-end text-success">{{ money($r['won']) }}</td><td class="text-end fw-semibold">{{ money($r['forecast']) }}</td>
                    <td class="text-end">{{ $r['target'] > 0 ? money($r['target']) : '—' }}</td>
                    <td class="text-end">@if ($r['attainment'] !== null)<span class="badge badge-soft-{{ $r['attainment'] >= 100 ? 'success' : ($r['attainment'] >= 70 ? 'warning' : 'danger') }}">{{ $r['attainment'] }}%</span>@else — @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>

    <div class="card"><div class="card-header"><span><i class="bi bi-info-circle me-1 text-orange"></i>{{ __('Cách tính') }}</span></div><div class="card-body small text-muted">
        <ul class="ps-3 mb-0">
            <li>{{ __('Pipeline: tổng giá trị cơ hội đang mở có ngày dự kiến chốt trong tháng.') }}</li>
            <li>{{ __('Trọng số = giá trị × xác suất thành công của từng cơ hội.') }}</li>
            <li>{{ __('Đã thắng: giá trị cơ hội ở giai đoạn thắng, ngày đóng trong tháng (khác doanh thu đã thu tiền).') }}</li>
            <li>{{ __('Dự báo = đã thắng + trọng số đang mở. Đạt = dự báo / chỉ tiêu.') }}</li>
            <li>{{ $ownTargetsOnly ? __('Chỉ tiêu: chỉ tiêu cá nhân của bạn.') : __('Chỉ tiêu: tổng chỉ tiêu của mọi nhân viên trong tổ chức.') }}</li>
        </ul>
    </div></div>
@endsection
