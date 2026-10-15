<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function period(Request $request): array
    {
        $request->validate([
            'period' => ['nullable', 'in:today,this_month,last_month,last_30,this_quarter,this_year,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
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
            'from' => $from,
            'to' => $to,
            'outcomes' => $service->opportunityOutcomes(),
            'topOpportunities' => $user->can('opportunities.view') ? $service->topOpportunities() : collect(),
            'topSellers' => $user->can('invoices.view') ? $service->topSellers($from, $to) : collect(),
            'myTasks' => $service->myTasks(),
            'activities' => $service->recentActivities(6),
            'notifications' => $user->notifications()->limit(4)->get(),
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
