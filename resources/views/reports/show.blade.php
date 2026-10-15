@extends('layouts.app')
@section('title', $report['title'])
@section('content')
    <x-page-header :title="$report['title']" :breadcrumbs="['Báo cáo' => route('reports.index'), $report['title'] => null]"
                   :subtitle="__('Từ :from đến :to', ['from' => $report['from']->format('d/m/Y'), 'to' => $report['to']->format('d/m/Y')])">
        @can('reports.export')
            <div class="dropdown">
                <button class="btn btn-primary" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>{{ __('Xuất báo cáo') }}</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('reports.export', [$report['type'], 'xlsx'] + request()->query()) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel (.xlsx)</a></li>
                    <li><a class="dropdown-item" href="{{ route('reports.export', [$report['type'], 'csv'] + request()->query()) }}"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                    <li><a class="dropdown-item" href="{{ route('reports.export', [$report['type'], 'pdf'] + request()->query()) }}"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>PDF</a></li>
                </ul>
            </div>
        @endcan
    </x-page-header>

    <div class="card mb-4">
        <form method="GET" class="filter-bar">
            <select class="form-select" onchange="location.href=this.value" aria-label="{{ __('Loại báo cáo') }}">
                @foreach ($types as $k => [$label])<option value="{{ route('reports.show', $k) }}" @selected($k === $report['type'])>{{ __($label) }}</option>@endforeach
            </select>
            <label class="small text-muted" for="from">{{ __('Từ') }}</label><input type="date" name="from" id="from" value="{{ $report['from']->format('Y-m-d') }}" class="form-control">
            <label class="small text-muted" for="to">{{ __('Đến') }}</label><input type="date" name="to" id="to" value="{{ $report['to']->format('Y-m-d') }}" class="form-control">
            @if (auth()->user()->can('invoices.view_all') || auth()->user()->can('leads.view_all'))
                <select name="user_id" class="form-select"><option value="">{{ __('Mọi nhân viên') }}</option>@foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('user_id') == $id)>{{ $name }}</option>@endforeach</select>
            @endif
            @if (!empty($report['statuses']))
                <select name="status" class="form-select"><option value="">{{ __('Mọi trạng thái') }}</option>@foreach ($report['statuses'] as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($s[0]) }}</option>@endforeach</select>
            @endif
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Áp dụng') }}</button>
        </form>
        <div class="px-3 py-2 small text-muted"><i class="bi bi-info-circle me-1"></i><strong>{{ __('Định nghĩa:') }}</strong> {{ $report['definition'] }}</div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($report['summary'] as [$label, $value, $icon])
            <div class="col-6 col-lg-3"><x-stat-card :icon="$icon" :label="$label" :value="$value" /></div>
        @endforeach
    </div>

    @if (!empty($report['chart']['labels']))
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">{{ $report['chart']['label'] }}</span></div>
            <div class="card-body"><div class="chart-box">
                @php
                    $palette = ['#FF6B00', '#2563EB', '#16A34A', '#F59E0B', '#7C3AED', '#DC2626', '#0EA5E9', '#6B7280'];
                    $chart = $report['chart'];
                    $chartConfig = [
                        'type' => $chart['type'],
                        'data' => ['labels' => $chart['labels'], 'datasets' => [[
                            'label' => $chart['label'], 'data' => $chart['values'],
                            'backgroundColor' => $chart['type'] === 'doughnut' ? $palette : '#FF6B00',
                            'borderColor' => '#FF6B00', 'borderRadius' => 6, 'tension' => 0.35, 'fill' => false,
                        ]]],
                        'options' => array_filter([
                            'money' => $chart['money'] ?? false,
                            'indexAxis' => ($chart['horizontal'] ?? false) ? 'y' : null,
                            'plugins' => ['legend' => ['display' => $chart['type'] === 'doughnut', 'position' => 'bottom']],
                        ]),
                    ];
                @endphp
                <canvas id="reportChart" data-chart="{{ json_encode($chartConfig) }}"></canvas>
            </div></div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><span>{{ __('Dữ liệu chi tiết (:count dòng)', ['count' => count($report['rows'])]) }}</span></div>
        @if (empty($report['rows']))
            <x-empty-state icon="bi-bar-chart" :title="__('Không có dữ liệu trong kỳ')" :message="__('Thử mở rộng khoảng thời gian hoặc bỏ bớt bộ lọc.')" />
        @else
            <div class="table-responsive" style="max-height: 600px">
                <table class="table table-hover table-sm">
                    <thead class="sticky-top"><tr>@foreach ($report['headings'] as $i => $h)<th class="{{ in_array($i, $report['money_cols']) ? 'text-end' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach ($report['rows'] as $row)
                        <tr>@foreach (array_values($row) as $i => $cell)<td class="{{ in_array($i, $report['money_cols']) ? 'text-end' : '' }}">{{ in_array($i, $report['money_cols']) ? money($cell) : $cell }}</td>@endforeach</tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
