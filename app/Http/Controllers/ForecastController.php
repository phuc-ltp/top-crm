<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\SalesTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Dự báo doanh thu theo tháng dự kiến chốt (trong phạm vi người dùng được xem).
 * - Đang mở: cơ hội chưa thắng/thua có ngày dự kiến chốt trong tháng; trọng số = giá trị × xác suất.
 * - Đã thắng: cơ hội ở giai đoạn thắng, ngày đóng trong tháng (giá trị cơ hội, chưa phải tiền đã thu).
 * - Dự báo = đã thắng + trọng số đang mở. So với chỉ tiêu doanh số của tháng.
 */
class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);
        $user = $request->user();
        $months = in_array((int) $request->input('months'), [3, 6, 12], true) ? (int) $request->input('months') : 6;
        $pipelineId = $request->filled('pipeline') ? Pipeline::whereKey($request->integer('pipeline'))->value('id') : null;
        $start = now()->startOfMonth();
        $end = $start->copy()->addMonths($months - 1)->endOfMonth();

        $base = fn () => Opportunity::visibleTo($user)->when($pipelineId, fn ($q) => $q->where('pipeline_id', $pipelineId));
        $open = $base()->open()->whereBetween('expected_close_date', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'value', 'probability', 'expected_close_date']);
        $won = $base()->whereHas('stage', fn ($q) => $q->where('is_won', true))->whereBetween('closed_at', [$start, $end])->get(['id', 'value', 'closed_at']);
        $overdue = $base()->open()->where('expected_close_date', '<', $start->toDateString())->get(['id', 'value', 'probability']);

        // Chỉ tiêu: người xem được tất cả → chỉ tiêu toàn tổ chức; ngược lại → chỉ tiêu của chính mình
        $targets = SalesTarget::query()->when(! $user->can('opportunities.view_all'), fn ($q) => $q->where('user_id', $user->id))
            ->where(fn ($q) => $q->whereRaw('(year * 100 + month) BETWEEN ? AND ?', [$start->year * 100 + $start->month, $end->year * 100 + $end->month]))
            ->get(['year', 'month', 'target_amount'])
            ->groupBy(fn ($t) => sprintf('%04d-%02d', $t->year, $t->month))->map(fn ($g) => (float) $g->sum('target_amount'));

        $rows = collect(range(0, $months - 1))->map(function ($i) use ($start, $open, $won, $targets) {
            $m = $start->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $inMonth = $open->filter(fn ($o) => $o->expected_close_date?->format('Y-m') === $key);
            $wonValue = (float) $won->filter(fn ($o) => $o->closed_at?->format('Y-m') === $key)->sum('value');
            $weighted = round($inMonth->sum(fn ($o) => $o->weighted_value), 2);
            $target = $targets[$key] ?? 0.0;
            $forecast = $wonValue + $weighted;

            return [
                'month' => $m, 'label' => $m->format('m/Y'), 'count' => $inMonth->count(), 'pipeline' => (float) $inMonth->sum('value'),
                'weighted' => $weighted, 'won' => $wonValue, 'forecast' => $forecast, 'target' => $target,
                'attainment' => $target > 0 ? round($forecast / $target * 100, 1) : null,
            ];
        });

        return view('opportunities.forecast', [
            'rows' => $rows, 'months' => $months, 'pipelineId' => $pipelineId,
            'pipelines' => Pipeline::ordered()->pluck('name', 'id'),
            'overdue' => ['count' => $overdue->count(), 'value' => (float) $overdue->sum('value')],
            'totals' => ['pipeline' => $rows->sum('pipeline'), 'weighted' => $rows->sum('weighted'), 'won' => $rows->sum('won'),
                'forecast' => $rows->sum('forecast'), 'target' => $rows->sum('target'),
                // Tỷ lệ đạt chỉ so trên các tháng CÓ chỉ tiêu (tháng chưa đặt chỉ tiêu không làm phồng tỷ lệ)
                'attainment' => ($t = $rows->where('target', '>', 0)->sum('target')) > 0
                    ? round($rows->where('target', '>', 0)->sum('forecast') / $t * 100, 1) : null,
                'target_months' => $rows->where('target', '>', 0)->count()],
            'ownTargetsOnly' => ! $user->can('opportunities.view_all'),
        ]);
    }
}
