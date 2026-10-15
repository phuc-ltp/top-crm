<?php

namespace App\Http\Controllers\Api;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class StatsApiController extends ApiController
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->can('dashboard.view'), 403);
        $request->validate(['period' => ['nullable', 'in:today,this_month,last_month,last_30,this_quarter,this_year,custom'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        [$from, $to] = DashboardService::range($request->input('period', 'this_month'), $request->from, $request->to);

        return $this->ok([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis' => (new DashboardService($request->user()))->kpis($from, $to),
        ]);
    }
}
