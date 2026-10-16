<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function period(Request $request): array
    {
        $request->validate([
            'period' => ['nullable', 'in:today,yesterday,last_7,this_month,last_month,last_30,this_quarter,this_year,custom'],
            'from' => ['nullable', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'before_or_equal:2100-12-31'],
        ]);

        return DashboardService::range($request->input('period', 'this_month'), $request->input('from'), $request->input('to'));
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('dashboard.view'), 403);
        [$from, $to] = $this->period($request);
        $service = new DashboardService($request->user());

        $user = $request->user();

        return view('dashboard', [
            'kpis' => $service->kpis($from, $to),
            'metricGroups' => (new \App\Services\ExecutiveMetrics($user, $from, $to))->groups(),
            'from' => $from,
            'to' => $to,
            'invoices' => $service->invoicesIssued($from, $to),
            'sparks' => $service->sparklines($from, $to),
            'years' => $service->revenueYears(),
            'outcomes' => $service->opportunityOutcomes(),
            'funnel' => $user->can('opportunities.view') ? $service->funnelCumulative() : [],
            'sources' => $user->can('customers.view') ? $service->customerSources($from, $to) : ['total' => 0, 'items' => []],
            'topSellers' => $user->can('invoices.view') ? $service->topSellers($from, $to) : collect(),
            'taskBoard' => $service->taskBoard(),
            'recentOrders' => $user->can('orders.view') ? $service->recentOrders() : collect(),
            'schedule' => $service->daySchedule(now()),
            'notifications' => $user->notifications()->limit(4)->get(),
        ]);
    }

    /** Widget tải lại bằng AJAX: biểu đồ doanh thu theo độ chi tiết, lịch theo ngày. */
    public function widget(Request $request, string $widget)
    {
        abort_unless($request->user()->can('dashboard.view'), 403);
        $service = new DashboardService($request->user());

        if ($widget === 'revenue') {
            $data = $request->validate([
                'granularity' => ['nullable', 'in:day,week,month,year'],
                'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            ]);

            return response()->json(['success' => true, 'data' => $service->revenueSeries($data['granularity'] ?? 'month', $data['year'] ?? null)]);
        }

        abort_unless($widget === 'schedule', 404);
        $data = $request->validate(['day' => ['required', 'date']]);
        $day = \Illuminate\Support\Carbon::parse($data['day']);

        return response()->json([
            'success' => true,
            'label' => vn_date($day),
            'is_today' => $day->isToday(),
            'html' => view('dashboard._schedule', ['schedule' => $service->daySchedule($day)])->render(),
        ]);
    }

    /** JSON cho biểu đồ (tải bằng AJAX). */
    public function data(Request $request)
    {
        abort_unless($request->user()->can('dashboard.view'), 403);
        [$from, $to] = $this->period($request);
        $service = new DashboardService($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => $service->monthlyRevenue(),
                'funnel' => $service->funnel(),
                'leads' => $service->leadStatus($from, $to),
                'growth' => $service->customerGrowth(),
                'performance' => $service->salesPerformance($from, $to),
                'target' => $service->targetVsActual((int) $to->year),
            ],
        ]);
    }
}
