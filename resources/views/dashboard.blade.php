@extends('layouts.app')
@section('title', __('Tổng quan'))
@section('content')
    @php
        $user = auth()->user();
        $periods = ['today' => __('Hôm nay'), 'this_month' => __('Tháng này'), 'last_month' => __('Tháng trước'), 'last_30' => __('30 ngày qua'), 'this_quarter' => __('Quý này'), 'this_year' => __('Năm nay'), 'custom' => __('Tùy chọn...')];
        $hour = now()->hour;
        $greeting = $hour < 11 ? __('Chào buổi sáng') : ($hour < 13 ? __('Chào buổi trưa') : ($hour < 18 ? __('Chào buổi chiều') : __('Chào buổi tối')));
        $firstName = \Illuminate\Support\Str::afterLast(trim($user->name), ' ');
        $priorityTone = ['urgent' => 'red', 'high' => 'yellow', 'medium' => 'blue', 'low' => 'gray'];
        $kpiCards = [
            [__('Doanh thu (đã thu)'), money_short($kpis['revenue']), money($kpis['revenue']), $kpis['revenue_change'], 'bi-currency-dollar', 'orange', 'revenue', 'invoices.view', 'invoices.index'],
            [__('Khách hàng mới'), number_format($kpis['customers_new'], 0, ',', '.'), __('Tổng: :count khách hàng', ['count' => number_format($kpis['customers_total'], 0, ',', '.')]), $kpis['customers_new_change'], 'bi-people-fill', 'blue', 'customers', 'customers.view', 'customers.index'],
            [__('Cơ hội mới'), number_format($kpis['opportunities_created'], 0, ',', '.'), __(':count cơ hội đang xử lý', ['count' => $kpis['opportunities_open']]), $kpis['opportunities_created_change'], 'bi-bullseye', 'green', 'opportunities', 'opportunities.view', 'opportunities.index'],
            [__('Hóa đơn phát hành'), number_format($invoices['count'], 0, ',', '.'), __('Giá trị: :amount', ['amount' => money($invoices['total'])]), $invoices['change'], 'bi-receipt', 'purple', 'invoices', 'invoices.view', 'invoices.index'],
        ];
    @endphp

    <div class="row g-3">
        {{-- ===================== CỘT CHÍNH ===================== --}}
        <div class="col-xxl-9 d-flex flex-column gap-3">
            {{-- Lời chào + bộ lọc kỳ --}}
            <div class="hello-card">
                <div class="min-w-0">
                    <h1>{{ $greeting }}, {{ $firstName }}! <span aria-hidden="true">👋</span></h1>
                    <p>{!! __('Đây là tình hình kinh doanh từ :from đến :to.', ['from' => '<strong>'.e($from->format('d/m/Y')).'</strong>', 'to' => '<strong>'.e($to->format('d/m/Y')).'</strong>']) !!}</p>
                </div>
                <form method="GET" class="period-picker" id="periodForm">
                    <i class="bi bi-calendar3"></i>
                    <select name="period" class="form-select form-select-sm" id="periodSelect" aria-label="{{ __('Khoảng thời gian') }}">
                        @foreach ($periods as $k => $label)
                            <option value="{{ $k }}" @selected(request('period', 'this_month') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span id="customRange" class="d-flex gap-1 align-items-center {{ request('period') === 'custom' ? '' : 'd-none' }}">
                        <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}" class="form-control form-control-sm" aria-label="{{ __('Từ ngày') }}">
                        <span class="text-muted">–</span>
                        <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}" class="form-control form-control-sm" aria-label="{{ __('Đến ngày') }}">
                        <button class="btn btn-primary btn-sm">{{ __('Áp dụng') }}</button>
                    </span>
                </form>
            </div>

            {{-- KPI + sparkline --}}
            <div class="row g-3">
                @foreach ($kpiCards as [$label, $value, $hint, $change, $icon, $tone, $spark, $perm, $route])
                    <div class="col-sm-6 col-xl-3">
                        <a class="card kpi-card h-100 text-reset" href="{{ $user->can($perm) ? route($route) : '#' }}" title="{{ $hint }}">
                            <div class="kpi-head">
                                <span class="kpi-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                                <span class="kpi-label">{{ $label }}</span>
                            </div>
                            <div class="kpi-value">{{ $value }}</div>
                            <div class="kpi-foot">
                                <span class="kpi-trend"><x-trend :value="$change" /><span class="small-xs text-muted">{{ __('so với kỳ trước') }}</span></span>
                                <span class="kpi-spark"><canvas id="spark-{{ $spark }}" data-values="{{ json_encode($sparks[$spark]) }}" data-tone="{{ $tone }}" aria-hidden="true"></canvas></span>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            {{-- Doanh thu + Tỷ lệ cơ hội --}}
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header flex-wrap gap-2">
                            <span class="card-title">{{ __('Doanh thu') }}</span>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="seg" role="tablist" id="revenueSeg" aria-label="{{ __('Độ chi tiết') }}">
                                    @foreach (['day' => __('Ngày'), 'week' => __('Tuần'), 'month' => __('Tháng'), 'year' => __('Năm')] as $g => $gl)
                                        <button type="button" class="{{ $g === 'month' ? 'active' : '' }}" data-granularity="{{ $g }}" role="tab" aria-selected="{{ $g === 'month' ? 'true' : 'false' }}">{{ $gl }}</button>
                                    @endforeach
                                </div>
                                <select class="form-select form-select-sm w-auto" id="revenueYear" aria-label="{{ __('Năm') }}">
                                    @foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="card-body pt-2">
                            <div class="d-flex gap-4 mb-2 small">
                                <span><span class="legend-dot" style="background:var(--crm-primary)"></span>{{ __('Thực thu') }} <strong id="revPaid" class="ms-1">—</strong></span>
                                <span><span class="legend-dot" style="background:var(--crm-info)"></span>{{ __('Hóa đơn phát hành') }} <strong id="revInvoiced" class="ms-1">—</strong></span>
                            </div>
                            <div class="chart-box" style="height:260px"><div class="skeleton"></div><canvas id="chartRevenue"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">{{ __('Tỷ lệ cơ hội') }}</span>@can('opportunities.view')<a href="{{ route('opportunities.index') }}" class="card-link-sm">{{ __('Pipeline') }}</a>@endcan</div>
                        <div class="card-body pt-1">
                            @if ($outcomes['total'] === 0)
                                <x-empty-state icon="bi-kanban" :title="__('Chưa có cơ hội')" />
                            @else
                                <div class="chart-box sm" style="height: 180px">
                                    <canvas id="chartOutcome"></canvas>
                                    <div class="donut-center">
                                        <div class="v">{{ $outcomes['win_rate'] === null ? '—' : $outcomes['win_rate'].'%' }}</div>
                                        <div class="l" title="{{ __('Thắng / (Thắng + Thất bại)') }}">{{ __('tỷ lệ thắng') }}</div>
                                    </div>
                                </div>
                                <ul class="legend-list mt-3">
                                    @foreach ([[__('Thành công'), $outcomes['won'], 'var(--crm-success)'], [__('Đang xử lý'), $outcomes['open'], 'var(--crm-primary)'], [__('Thất bại'), $outcomes['lost'], 'var(--crm-danger)']] as [$l, $n, $c])
                                        <li><span><span class="legend-dot" style="background:{{ $c }}"></span>{{ $l }} <span class="text-muted">({{ $n }})</span></span><strong>{{ $outcomes['pct']($n) }}%</strong></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Phễu + Nguồn khách hàng + Top nhân viên --}}
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">{{ __('Cơ hội bán hàng') }}</span><span class="small-xs text-muted" title="{{ __('Số cơ hội (không tính thất bại) đã đi tới từng giai đoạn') }}">{{ __('Phễu lũy kế') }}</span></div>
                        <div class="card-body pt-1">
                            @if (empty($funnel) || $funnel[0]['count'] === 0)
                                <x-empty-state icon="bi-funnel" :title="__('Chưa có cơ hội')" />
                            @else
                                <div class="funnel">
                                    @foreach ($funnel as $i => $f)
                                        <div class="funnel-row">
                                            <div class="funnel-bar" style="--w: {{ max(18, $f['pct']) }}%; --c: {{ $f['color'] ?: 'var(--crm-primary)' }}; --o: {{ 1 - $i * 0.12 }}"><span>{{ $f['count'] }}</span></div>
                                            <div class="funnel-label"><span class="text-truncate" title="{{ __($f['name']) }}">{{ __($f['name']) }}</span><strong>{{ $f['pct'] }}%</strong></div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">{{ __('Nguồn khách hàng') }}</span><span class="small-xs text-muted" title="{{ __('Khách hàng mới trong kỳ') }}">{{ __('Trong kỳ') }}</span></div>
                        <div class="card-body pt-1">
                            @if ($sources['total'] === 0)
                                <x-empty-state icon="bi-diagram-3" :title="__('Chưa có khách hàng mới trong kỳ')" />
                            @else
                                <div class="d-flex align-items-center gap-3">
                                    <div class="chart-box sm flex-shrink-0" style="height:140px;width:140px">
                                        <canvas id="chartSources"></canvas>
                                        <div class="donut-center"><div class="v">{{ $sources['total'] }}</div><div class="l">{{ __('khách') }}</div></div>
                                    </div>
                                    <ul class="legend-list flex-grow-1 min-w-0" id="sourceLegend">
                                        @foreach (array_slice($sources['items'], 0, 6) as $i => $s)
                                            <li><span class="text-truncate"><span class="legend-dot" data-source-dot="{{ $i }}"></span>{{ __($s['name']) }}</span><strong>{{ $s['pct'] }}%</strong></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">{{ __('Top nhân viên') }}</span><span class="small-xs text-muted">{{ __('Đã thu trong kỳ') }}</span></div>
                        <div class="card-body pt-1">
                            @php($maxSale = $topSellers->max('total') ?: 1)
                            @forelse ($topSellers as $i => $s)
                                <div class="rank-row">
                                    <span class="rank-no {{ $i < 3 ? 'top' : '' }}">{{ $i + 1 }}</span>
                                    <x-avatar :user="$s->user" size="sm" />
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex justify-content-between gap-2"><span class="small fw-semibold text-truncate">{{ $s->user->name }}</span><span class="small fw-semibold text-nowrap" title="{{ money($s->total) }}">{{ money_short($s->total) }}</span></div>
                                        <div class="mini-progress mt-1"><span style="width: {{ round($s->total / $maxSale * 100) }}%"></span></div>
                                    </div>
                                </div>
                            @empty
                                <x-empty-state icon="bi-trophy" :title="__('Chưa có doanh thu trong kỳ')" />
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Công việc + Đơn hàng gần đây --}}
            <div class="row g-3">
                <div class="col-lg-6 col-xxl-5">
                    <div class="card h-100">
                        <div class="card-header flex-wrap gap-2">
                            <span class="card-title">{{ __('Công việc hôm nay') }}</span>
                            @can('create', \App\Models\Task::class)
                                <a href="{{ route('tasks.create') }}" class="btn btn-soft-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm công việc') }}</a>
                            @endcan
                        </div>
                        <div class="card-body pt-1">
                            <div class="seg seg-line mb-2" role="tablist" id="taskSeg">
                                @foreach (['all' => __('Tất cả'), 'overdue' => __('Quá hạn'), 'upcoming' => __('Sắp đến hạn'), 'completed' => __('Hoàn thành')] as $k => $l)
                                    <button type="button" class="{{ $k === 'all' ? 'active' : '' }}" data-task-tab="{{ $k }}" role="tab">{{ $l }} <span class="count">{{ $taskBoard[$k]['count'] }}</span></button>
                                @endforeach
                            </div>
                            @foreach ($taskBoard as $k => $group)
                                <div data-task-pane="{{ $k }}" class="{{ $k === 'all' ? '' : 'd-none' }}">
                                    @forelse ($group['items'] as $t)
                                        <a href="{{ route('tasks.show', $t) }}" class="task-row">
                                            <span class="task-check {{ $t->status === 'completed' ? 'done' : '' }}"><i class="bi bi-check"></i></span>
                                            <span class="min-w-0 flex-grow-1">
                                                <span class="d-block small fw-semibold text-truncate {{ $t->status === 'completed' ? 'text-decoration-line-through text-muted' : '' }}">{{ $t->title }}</span>
                                                <span class="small-xs {{ $t->is_late ? 'text-danger' : 'text-muted' }}"><i class="bi bi-clock"></i>
                                                    {{ $t->due_date ? ($t->due_date->isToday() ? __('Hôm nay :time', ['time' => $t->due_date->format('H:i')]) : vn_date($t->due_date, true)) : __('Không có hạn') }}{{ $t->is_late ? ' · '.__('trễ hạn') : '' }}</span>
                                            </span>
                                            <span class="badge badge-soft-{{ \App\Models\Task::PRIORITIES[$t->priority][1] ?? 'secondary' }}">{{ label_of(\App\Models\Task::PRIORITIES, $t->priority) }}</span>
                                        </a>
                                    @empty
                                        <x-empty-state icon="bi-emoji-smile" :title="__('Không có công việc')" />
                                    @endforelse
                                </div>
                            @endforeach
                            @can('tasks.view')
                                <a href="{{ route('tasks.index', ['mine' => 1]) }}" class="card-link-sm d-inline-block mt-2">{{ __('Xem tất cả công việc') }} <i class="bi bi-arrow-right"></i></a>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-xxl-7">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">{{ __('Đơn hàng gần đây') }}</span>@can('orders.view')<a href="{{ route('orders.index') }}" class="card-link-sm">{{ __('Xem tất cả') }}</a>@endcan</div>
                        @if ($recentOrders->isEmpty())
                            <div class="card-body"><x-empty-state icon="bi-bag" :title="__('Chưa có đơn hàng')" /></div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 align-middle">
                                    <thead><tr><th>{{ __('Mã đơn') }}</th><th>{{ __('labels.customer') }}</th><th class="text-end">{{ __('Giá trị') }}</th><th>{{ __('Trạng thái') }}</th></tr></thead>
                                    <tbody>
                                        @foreach ($recentOrders as $o)
                                            <tr>
                                                <td class="text-nowrap"><a href="{{ route('orders.show', $o) }}" class="fw-semibold">{{ $o->number }}</a><div class="small-xs text-muted">{{ vn_date($o->order_date) }}</div></td>
                                                <td class="text-truncate" style="max-width:170px">{{ $o->customer?->name ?? '—' }}</td>
                                                                                                <td class="text-end text-nowrap fw-semibold" title="{{ money($o->total) }}">{{ money_short($o->total) }}</td>
                                                <td>{!! badge(\App\Models\SalesOrder::STATUSES, $o->status) !!}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== CỘT PHẢI ===================== --}}
        <div class="col-xxl-3">
            <div class="row g-3">
                <div class="col-md-6 col-xl-4 col-xxl-12">
                    <div class="promo-card" style="--promo: url('{{ asset('images/login-bg.jpg') }}')">
                        <div class="promo-body">
                            <div class="promo-brand"><x-brand-logo :size="26" /> {{ $crmSettings['brand_name'] }} CRM</div>
                            <div class="promo-quote">{{ __('Quản lý khách hàng thông minh — tăng trưởng doanh thu bền vững.') }}</div>
                            <a href="{{ route('pages.support') }}" class="btn btn-light btn-sm">{{ __('Hướng dẫn sử dụng') }} <i class="bi bi-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4 col-xxl-12">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">{{ __('Thông báo') }}</span><a href="{{ route('notifications.index') }}" class="card-link-sm">{{ __('Xem tất cả') }}</a></div>
                        <div class="card-body pt-1">
                            @forelse ($notifications as $n)
                                <a href="{{ route('notifications.open', $n->id) }}" class="notif-row">
                                    <span class="notif-ic {{ $n->read_at ? '' : 'unread' }}"><i class="bi bi-bell"></i></span>
                                    <span class="min-w-0"><span class="d-block small text-truncate-2">{{ $n->data['message'] ?? '' }}</span><span class="small-xs text-muted">{{ $n->created_at->diffForHumans() }}</span></span>
                                </a>
                            @empty
                                <x-empty-state icon="bi-bell" :title="__('Không có thông báo')" />
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-xxl-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <span class="card-title">{{ __('Lịch') }}</span>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-icon btn-sm" data-day-shift="-1" aria-label="{{ __('Ngày trước') }}"><i class="bi bi-chevron-left"></i></button>
                                <span class="small fw-semibold text-nowrap" id="schedLabel" data-day="{{ now()->toDateString() }}">{{ __('Hôm nay') }}</span>
                                <button type="button" class="btn btn-icon btn-sm" data-day-shift="1" aria-label="{{ __('Ngày sau') }}"><i class="bi bi-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="card-body pt-1"><div id="schedList" class="sched">@include('dashboard._schedule')</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('periodSelect');
    sel.addEventListener('change', () => {
        document.getElementById('customRange').classList.toggle('d-none', sel.value !== 'custom');
        if (sel.value !== 'custom') document.getElementById('periodForm').requestSubmit();
    });
    const fmtMoney = (v) => moneyTick(v) + ' ₫';
    const toneColor = (p, tone) => ({ orange: p.primary, blue: p.info, green: p.success, purple: p.purple })[tone] || p.primary;
    const alpha = (hex, a) => {
        const m = hex.replace('#', '').match(/.{2}/g);
        return m ? `rgba(${m.map((x) => parseInt(x, 16)).join(',')},${a})` : hex;
    };

    function drawStatic() {
        const p = crmPalette();
        document.querySelectorAll('.kpi-spark canvas').forEach((c) => {
            const color = toneColor(p, c.dataset.tone);
            renderChart(c, {
                type: 'line',
                data: { labels: JSON.parse(c.dataset.values).map((_, i) => i), datasets: [{ data: JSON.parse(c.dataset.values), borderColor: color, borderWidth: 2, pointRadius: 0, cubicInterpolationMode: 'monotone', fill: true,
                    backgroundColor: (ctx) => { const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, ctx.chart.height); g.addColorStop(0, alpha(color, .28)); g.addColorStop(1, alpha(color, 0)); return g; } }] },
                options: { animation: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } }, events: [] },
            });
        });
        @if ($outcomes['total'] > 0)
            renderChart('chartOutcome', {
                type: 'doughnut',
                data: { labels: [t('Thành công'), t('Đang xử lý'), t('Thất bại')], datasets: [{ data: [{{ $outcomes['won'] }}, {{ $outcomes['open'] }}, {{ $outcomes['lost'] }}], backgroundColor: [p.success, p.primary, p.danger], borderColor: p.surface, borderWidth: 3, borderRadius: 4 }] },
                options: { cutout: '74%', plugins: { legend: { display: false } } },
            });
        @endif
        @if ($sources['total'] > 0)
            const srcColors = [p.primary, p.info, p.success, p.purple, p.warning, p.danger, p.text];
            const src = @json($sources['items']);
            document.querySelectorAll('[data-source-dot]').forEach((d) => { d.style.background = srcColors[d.dataset.sourceDot % srcColors.length]; });
            renderChart('chartSources', {
                type: 'doughnut',
                data: { labels: src.map((s) => s.label), datasets: [{ data: src.map((s) => s.count), backgroundColor: src.map((_, i) => srcColors[i % srcColors.length]), borderColor: p.surface, borderWidth: 2 }] },
                options: { cutout: '70%', plugins: { legend: { display: false } } },
            });
        @endif
    }

    /* Biểu đồ doanh thu: cột = thực thu, vùng = hóa đơn phát hành */
    const state = { granularity: 'month', year: document.getElementById('revenueYear')?.value };
    async function loadRevenue() {
        const box = document.getElementById('chartRevenue').parentElement;
        try {
            const res = await api(`{{ route('dashboard.widget', 'revenue') }}?granularity=${state.granularity}&year=${state.year || ''}`);
            const d = res.data, p = crmPalette();
            document.getElementById('revPaid').textContent = fmtMoney(d.total_paid);
            document.getElementById('revInvoiced').textContent = fmtMoney(d.total_invoiced);
            renderChart('chartRevenue', {
                type: 'bar',
                data: { labels: d.labels, datasets: [
                    { label: t('Thực thu'), data: d.paid, backgroundColor: p.primary, borderRadius: 6, maxBarThickness: 22, order: 2 },
                    { label: t('Hóa đơn phát hành'), data: d.invoiced, type: 'line', borderColor: p.info, borderWidth: 2, fill: true, cubicInterpolationMode: 'monotone', pointRadius: 0, pointHoverRadius: 4, order: 1,
                      backgroundColor: (ctx) => { const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, ctx.chart.height); g.addColorStop(0, alpha(p.info, .22)); g.addColorStop(1, alpha(p.info, 0)); return g; } },
                ] },
                options: { money: true, interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } } } },
            });
        } catch (e) {
            box.innerHTML = `<div class="empty-state text-danger"><div class="empty-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="small">${e.message}</div></div>`;
        }
    }
    document.querySelectorAll('#revenueSeg [data-granularity]').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('#revenueSeg button').forEach((x) => { x.classList.toggle('active', x === b); x.setAttribute('aria-selected', x === b); });
        state.granularity = b.dataset.granularity;
        document.getElementById('revenueYear').disabled = state.granularity !== 'month';
        loadRevenue();
    }));
    document.getElementById('revenueYear')?.addEventListener('change', (e) => { state.year = e.target.value; loadRevenue(); });

    /* Tab công việc */
    document.querySelectorAll('[data-task-tab]').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('[data-task-tab]').forEach((x) => x.classList.toggle('active', x === b));
        document.querySelectorAll('[data-task-pane]').forEach((p) => p.classList.toggle('d-none', p.dataset.taskPane !== b.dataset.taskTab));
    }));

    /* Lịch theo ngày */
    const label = document.getElementById('schedLabel');
    document.querySelectorAll('[data-day-shift]').forEach((b) => b.addEventListener('click', async () => {
        const d = new Date(label.dataset.day + 'T00:00:00');
        d.setDate(d.getDate() + Number(b.dataset.dayShift));
        const day = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        try {
            const res = await api(`{{ route('dashboard.widget', 'schedule') }}?day=${day}`);
            label.dataset.day = day;
            label.textContent = res.is_today ? t('Hôm nay') : res.label;
            document.getElementById('schedList').innerHTML = res.html;
        } catch (e) { toast('error', e.message); }
    }));

    drawStatic();
    loadRevenue();
    document.addEventListener('crm:theme', () => { drawStatic(); loadRevenue(); });
});
</script>
@endpush
