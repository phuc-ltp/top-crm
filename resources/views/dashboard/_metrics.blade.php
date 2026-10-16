{{-- 20 chỉ số điều hành: mỗi chỉ số có công thức ở biểu tượng ⓘ; nhóm chỉ hiện khi có quyền xem module --}}
@php
    [$prevFrom, $prevTo] = \App\Services\DashboardService::previousRange($from, $to);
    $num = fn ($v) => number_format($v, floor($v) == $v ? 0 : 1, ',', '.');
    $show = function (array $m) use ($num) {
        if ($m['value'] === null) {
            return '—';
        }
        return match ($m['format']) {
            'money' => money_short($m['value']),
            'percent' => (! empty($m['signed']) && $m['value'] > 0 ? '+' : '').$num($m['value']).'%',
            'ratio' => $m['display'] ?? $num($m['value']),
            default => $num($m['value']),
        };
    };
@endphp
@if (! empty($metricGroups))
    <section class="card" aria-labelledby="metricsTitle">
        <div class="card-header flex-wrap gap-2">
            <span class="card-title" id="metricsTitle">{{ __('Chỉ số điều hành') }}</span>
            <span class="small-xs text-muted">
                {{ __('Kỳ :from – :to · so với :pf – :pt', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'pf' => $prevFrom->format('d/m/Y'), 'pt' => $prevTo->format('d/m/Y')]) }}
                · <i class="bi bi-info-circle"></i> {{ __('bấm ⓘ để xem công thức') }}
            </span>
        </div>
        <div class="card-body pt-1">
            <div class="row g-3">
                @foreach ($metricGroups as $group)
                    <div class="col-lg-6">
                        <div class="metric-group h-100" data-metric-group="{{ $group['key'] }}">
                            <div class="metric-group-head">
                                <span><i class="bi {{ $group['icon'] }} me-1"></i>{{ __($group['label']) }}</span>
                                @if ($group['demo'])
                                    <span class="badge badge-soft-warning" title="{{ __('Số liệu nhóm này có tính cả dữ liệu demo — không phải số liệu kinh doanh thật.') }}">DEMO</span>
                                @endif
                            </div>
                            @foreach ($group['metrics'] as $m)
                                <div class="metric-row" data-metric="{{ $m['key'] }}">
                                    <div class="min-w-0">
                                        <div class="metric-label">
                                            <i class="bi {{ $m['icon'] }}" aria-hidden="true"></i>
                                            @if ($m['url'])<a href="{{ $m['url'] }}" class="text-reset">{{ __($m['label']) }}</a>@else<span>{{ __($m['label']) }}</span>@endif
                                            <button type="button" class="metric-info" data-bs-toggle="tooltip" data-bs-trigger="click" data-bs-placement="top" data-bs-title="{{ __($m['formula']) }}"
                                                    aria-label="{{ __('Công thức') }}: {{ __($m['formula']) }}"><i class="bi bi-info-circle"></i></button>
                                        </div>
                                        <div class="metric-sub">
                                            @if ($m['value'] === null && $m['empty'])
                                                {{ __($m['empty']) }}
                                            @else
                                                {{ $m['sub'] }}
                                            @endif
                                            @if ($m['snapshot'])<span class="metric-now" title="{{ __('Không phụ thuộc kỳ đang lọc') }}">{{ __('hiện tại') }}</span>@endif
                                        </div>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        <div class="metric-value {{ $m['value'] === null ? 'text-muted' : '' }} {{ ! empty($m['bad']) && $m['value'] > 0 ? 'text-danger' : '' }}"
                                             @if ($m['format'] === 'money' && $m['value'] !== null) title="{{ money($m['value']) }}" @endif>{{ $show($m) }}</div>
                                        @if ($m['compare'])<x-trend :value="$m['change']" />@endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
