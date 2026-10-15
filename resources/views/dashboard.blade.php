@extends('layouts.app')
@section('title', 'Tổng quan')
@section('content')
    @php
        $user = auth()->user();
        $periods = ['today' => 'Hôm nay', 'this_month' => 'Tháng này', 'last_month' => 'Tháng trước', 'last_30' => '30 ngày qua', 'this_quarter' => 'Quý này', 'this_year' => 'Năm nay', 'custom' => 'Tùy chọn...'];
        $priorityTone = ['urgent' => 'red', 'high' => 'yellow', 'medium' => 'blue', 'low' => 'gray'];
    @endphp

    {{-- Tiêu đề + bộ lọc thời gian --}}
    <div class="page-header">
        <div class="min-w-0">
            <h1>Tổng quan</h1>
            <div class="text-muted small mt-1">Chào mừng trở lại, {{ $user->name }}! Đây là tình hình kinh doanh từ {{ $from->format('d/m/Y') }} đến {{ $to->format('d/m/Y') }}.</div>
        </div>
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" id="periodForm">
            <select name="period" class="form-select form-select-sm w-auto" id="periodSelect" aria-label="Khoảng thời gian">
                @foreach ($periods as $k => $label)
                    <option value="{{ $k }}" @selected(request('period', 'this_month') === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <span id="customRange" class="d-flex gap-1 align-items-center {{ request('period') === 'custom' ? '' : 'd-none' }}">
                <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}" class="form-control form-control-sm" aria-label="Từ ngày">
                <span class="text-muted">–</span>
                <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}" class="form-control form-control-sm" aria-label="Đến ngày">
                <button class="btn btn-primary btn-sm">Áp dụng</button>
            </span>
        </form>
    </div>

    <div class="row g-4">
        {{-- ===================== CỘT CHÍNH ===================== --}}
        <div class="col-xxl-9">
            {{-- KPI chính --}}
            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-xl-3">
                    <a class="card card-hover h-100 text-reset" href="{{ $user->can('customers.view') ? route('customers.index') : '#' }}"><div class="stat-card">
                        <div class="stat-icon tone-green"><i class="bi bi-people-fill"></i></div>
                        <div class="min-w-0"><div class="stat-label">Tổng khách hàng</div>
                            <div class="stat-value">{{ number_format($kpis['customers_total'], 0, ',', '.') }}</div>
                            <div class="stat-sub d-flex flex-wrap gap-1 align-items-center"><x-trend :value="$kpis['customers_new_change']" hint="Khách mới so với kỳ trước" /> +{{ $kpis['customers_new'] }} mới trong kỳ</div></div>
                    </div></a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a class="card card-hover h-100 text-reset" href="{{ $user->can('leads.view') ? route('leads.index') : '#' }}"><div class="stat-card">
                        <div class="stat-icon tone-orange"><i class="bi bi-funnel-fill"></i></div>
                        <div class="min-w-0"><div class="stat-label">Leads mới</div>
                            <div class="stat-value">{{ number_format($kpis['leads_new'], 0, ',', '.') }}</div>
                            <div class="stat-sub d-flex flex-wrap gap-1 align-items-center"><x-trend :value="$kpis['leads_new_change']" /> {{ $kpis['leads_converted'] }} đã chuyển đổi</div></div>
                    </div></a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a class="card card-hover h-100 text-reset" href="{{ $user->can('opportunities.view') ? route('opportunities.index') : '#' }}"><div class="stat-card">
                        <div class="stat-icon tone-yellow"><i class="bi bi-kanban-fill"></i></div>
                        <div class="min-w-0"><div class="stat-label">Cơ hội đang xử lý</div>
                            <div class="stat-value">{{ number_format($kpis['opportunities_open'], 0, ',', '.') }}</div>
                            <div class="stat-sub d-flex flex-wrap gap-1 align-items-center"><x-trend :value="$kpis['opportunities_created_change']" hint="Cơ hội tạo mới so với kỳ trước" /> {{ $kpis['opportunities_created'] }} tạo trong kỳ</div></div>
                    </div></a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a class="card card-hover h-100 text-reset" href="{{ $user->can('invoices.view') ? route('invoices.index') : '#' }}"><div class="stat-card">
                        <div class="stat-icon tone-blue"><i class="bi bi-cash-stack"></i></div>
                        <div class="min-w-0"><div class="stat-label">Doanh thu (đã thu)</div>
                            <div class="stat-value" title="{{ money($kpis['revenue']) }}">{{ money_short($kpis['revenue']) }}</div>
                            <div class="stat-sub d-flex flex-wrap gap-1 align-items-center"><x-trend :value="$kpis['revenue_change']" /> so với kỳ trước</div></div>
                    </div></a>
                </div>
            </div>

            {{-- Chỉ số phụ --}}
            <div class="card mb-4"><div class="row g-0 text-center text-md-start">
                @foreach ([
                    ['Giá trị pipeline', money($kpis['pipeline_value']), 'bi-graph-up-arrow', 'Tổng giá trị cơ hội chưa đóng'],
                    ['Tỷ lệ chuyển đổi lead', $kpis['conversion_rate'].'%', 'bi-percent', 'Lead tạo trong kỳ đã chuyển đổi'],
                    ['Công nợ phải thu', money($kpis['receivable']), 'bi-wallet2', 'Hóa đơn đã phát hành chưa thu đủ'],
                    ['Công việc trễ hạn', $kpis['tasks_late'].' / '.$kpis['tasks_open'], 'bi-alarm', 'Trễ hạn / tổng việc chưa xong'],
                ] as $i => [$label, $value, $icon, $hint])
                    <div class="col-6 col-md-3 p-3 {{ $i ? 'border-start' : '' }}" title="{{ $hint }}">
                        <div class="small text-muted d-flex align-items-center gap-1 justify-content-center justify-content-md-start"><i class="bi {{ $icon }} text-orange"></i>{{ $label }}</div>
                        <div class="fw-bold fs-6 mt-1">{{ $value }}</div>
                    </div>
                @endforeach
            </div></div>

            {{-- Biểu đồ --}}
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">Doanh thu theo tháng</span><span class="small text-muted">12 tháng gần nhất</span></div>
                        <div class="card-body"><div class="chart-box"><div class="skeleton"></div><canvas id="chartRevenue"></canvas></div></div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">Tỷ lệ cơ hội</span><span class="small text-muted" title="Thắng / (Thắng + Thất bại)">Tỷ lệ thắng</span></div>
                        <div class="card-body">
                            @if ($outcomes['total'] === 0)
                                <x-empty-state icon="bi-kanban" title="Chưa có cơ hội" />
                            @else
                                <div class="chart-box sm" style="height: 190px">
                                    <canvas id="chartOutcome"></canvas>
                                    <div class="position-absolute top-50 start-50 translate-middle text-center" style="pointer-events:none">
                                        <div class="fw-bold" style="font-size:1.6rem">{{ $outcomes['win_rate'] === null ? '—' : $outcomes['win_rate'].'%' }}</div>
                                        <div class="small-xs text-muted">tỷ lệ thắng</div>
                                    </div>
                                </div>
                                <ul class="list-unstyled small mt-3 mb-0">
                                    @foreach ([['Thành công', $outcomes['won'], 'var(--crm-success)'], ['Đang xử lý', $outcomes['open'], 'var(--crm-primary)'], ['Thất bại', $outcomes['lost'], 'var(--crm-danger)']] as [$l, $n, $c])
                                        <li class="d-flex justify-content-between py-1"><span><span class="d-inline-block rounded-circle me-2" style="width:8px;height:8px;background:{{ $c }}"></span>{{ $l }} ({{ $n }})</span><strong>{{ $outcomes['pct']($n) }}%</strong></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Hàng dưới --}}
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">Việc của tôi</span>@can('tasks.view')<a href="{{ route('tasks.index', ['mine' => 1, 'status' => 'open']) }}" class="card-link-sm">Xem tất cả</a>@endcan</div>
                        <div class="card-body pt-2">
                            @forelse ($myTasks as $t)
                                <a href="{{ route('tasks.show', $t) }}" class="d-flex gap-3 py-2 text-reset {{ ! $loop->last ? 'border-bottom' : '' }}">
                                    <span class="stat-icon tone-{{ $priorityTone[$t->priority] ?? 'gray' }}" style="width:34px;height:34px;border-radius:10px;font-size:.95rem;display:grid;place-items:center;flex-shrink:0"><i class="bi bi-check2-square"></i></span>
                                    <span class="min-w-0">
                                        <span class="d-block small fw-semibold text-truncate">{{ $t->title }}</span>
                                        <span class="small-xs {{ $t->is_late ? 'text-danger' : 'text-muted' }}"><i class="bi bi-clock"></i>
                                            {{ $t->due_date->isToday() ? 'Hôm nay '.$t->due_date->format('H:i') : vn_date($t->due_date, true) }}{{ $t->is_late ? ' · trễ hạn' : '' }}</span>
                                    </span>
                                </a>
                            @empty
                                <x-empty-state icon="bi-emoji-smile" title="Không có việc trong 7 ngày tới" />
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">Cơ hội tiềm năng</span>@can('opportunities.view')<a href="{{ route('opportunities.index') }}" class="card-link-sm">Xem pipeline</a>@endcan</div>
                        <div class="card-body pt-2">
                            @forelse ($topOpportunities as $o)
                                <a href="{{ route('opportunities.show', $o) }}" class="d-block py-2 text-reset {{ ! $loop->last ? 'border-bottom' : '' }}">
                                    <div class="d-flex justify-content-between gap-2"><span class="small fw-semibold text-truncate">{{ $o->customer?->name ?? $o->title }}</span><span class="small fw-semibold text-orange">{{ $o->probability }}%</span></div>
                                    <div class="small-xs text-muted text-truncate mb-1">{{ $o->title }} · {{ money($o->value) }}</div>
                                    <div class="mini-progress"><span style="width: {{ $o->probability }}%"></span></div>
                                </a>
                            @empty
                                <x-empty-state icon="bi-kanban" title="Chưa có cơ hội đang mở" />
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header"><span class="card-title">Doanh số nhân viên</span><span class="small-xs text-muted">Đã thu trong kỳ</span></div>
                        <div class="card-body pt-2">
                            @php($maxSale = $topSellers->max('total') ?: 1)
                            @forelse ($topSellers as $i => $s)
                                <div class="d-flex align-items-center gap-3 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                                    <x-avatar :user="$s->user" size="sm" />
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex justify-content-between gap-2"><span class="small fw-semibold text-truncate">{{ $s->user->name }}</span><span class="small fw-semibold">{{ money($s->total) }}</span></div>
                                        <div class="mini-progress mt-1"><span style="width: {{ round($s->total / $maxSale * 100) }}%"></span></div>
                                    </div>
                                </div>
                            @empty
                                <x-empty-state icon="bi-bar-chart" title="Chưa có doanh thu trong kỳ" />
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== CỘT PHẢI ===================== --}}
        <div class="col-xxl-3"><div class="row g-4">
            <div class="col-md-6 col-xxl-12"><div class="card h-100">
                <div class="card-header"><span class="card-title">Hoạt động gần đây</span>@can('activity_logs.view')<a href="{{ route('activity-logs.index') }}" class="card-link-sm">Xem tất cả</a>@endcan</div>
                <div class="card-body pt-1">
                    @if ($activities->isEmpty())
                        <x-empty-state icon="bi-activity" title="Chưa có hoạt động" />
                    @else
                        <ul class="feed">
                            @foreach ($activities as $a)
                                <li>
                                    @if ($a->user)<x-avatar :user="$a->user" size="sm" />@else<span class="avatar avatar-sm tone-gray"><i class="bi bi-gear"></i></span>@endif
                                    <div class="min-w-0">
                                        <div class="small"><strong>{{ $a->user?->name ?? 'Hệ thống' }}</strong> <span class="text-muted">{{ \Illuminate\Support\Str::lcfirst($a->description) }}</span></div>
                                        <div class="meta">{{ $a->created_at?->diffForHumans() }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
            </div>
            <div class="col-md-6 col-xxl-12"><div class="card h-100">
                <div class="card-header"><span class="card-title">Thông báo</span><a href="{{ route('notifications.index') }}" class="card-link-sm">Xem tất cả</a></div>
                <div class="card-body pt-1">
                    @forelse ($notifications as $n)
                        <a href="{{ route('notifications.open', $n->id) }}" class="d-flex gap-2 py-2 text-reset {{ ! $loop->last ? 'border-bottom' : '' }}">
                            <span class="badge-dot {{ $n->read_at ? 'text-muted' : 'text-danger' }}" style="padding-top:2px"></span>
                            <span class="min-w-0"><span class="d-block small text-truncate-2">{{ $n->data['message'] ?? '' }}</span><span class="small-xs text-muted">{{ $n->created_at->diffForHumans() }}</span></span>
                        </a>
                    @empty
                        <x-empty-state icon="bi-bell" title="Không có thông báo" />
                    @endforelse
                </div>
            </div></div>
        </div></div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
    const sel = document.getElementById('periodSelect');
    sel.addEventListener('change', () => {
        document.getElementById('customRange').classList.toggle('d-none', sel.value !== 'custom');
        if (sel.value !== 'custom') document.getElementById('periodForm').requestSubmit();
    });

    const p = crmPalette();
    @if ($outcomes['total'] > 0)
        renderChart('chartOutcome', {
            type: 'doughnut',
            data: { labels: ['Thành công', 'Đang xử lý', 'Thất bại'], datasets: [{ data: [{{ $outcomes['won'] }}, {{ $outcomes['open'] }}, {{ $outcomes['lost'] }}], backgroundColor: [p.success, p.primary, p.danger], borderColor: p.surface, borderWidth: 3 }] },
            options: { cutout: '72%', plugins: { legend: { display: false } } },
        });
    @endif

    try {
        const res = await api('{{ route('dashboard.data') }}' + window.location.search);
        const d = res.data;
        renderChart('chartRevenue', {
            type: 'bar',
            data: { labels: d.revenue.labels.map(l => 'T' + l.split('/')[0]), datasets: [
                { label: 'Thực thu', data: d.revenue.paid, backgroundColor: p.primary, borderRadius: 6, maxBarThickness: 26, order: 2 },
                { label: 'Hóa đơn phát hành', data: d.revenue.invoiced, type: 'line', borderColor: p.dark, backgroundColor: 'transparent', cubicInterpolationMode: 'monotone', pointRadius: 3, pointBackgroundColor: p.dark, order: 1 },
            ] },
            options: { money: true, plugins: { legend: { position: 'top', align: 'end' } }, scales: { x: { grid: { display: false } } } },
        });
    } catch (e) {
        const box = document.getElementById('chartRevenue')?.parentElement;
        if (box) box.innerHTML = `<div class="empty-state text-danger"><div class="empty-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="small">${e.message}</div></div>`;
    }
});
</script>
@endpush
