<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LostReason;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phân tích lead theo NHÓM LEAD TẠO TRONG KỲ (cohort) — chỉ trong phạm vi người dùng được xem.
 * Tỷ lệ chuyển đổi = lead đã chuyển đổi / tổng lead tạo trong kỳ. Thời gian chuyển đổi = converted_at − created_at.
 */
class LeadAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $user = $request->user();
        [$from, $to] = DashboardService::range($request->input('period', 'this_quarter'), $request->input('from'), $request->input('to'));

        $leads = Lead::visibleTo($user)->whereBetween('created_at', [$from, $to])
            ->get(['id', 'status', 'lead_source_id', 'assigned_to', 'lost_reason_id', 'created_at', 'qualified_at', 'converted_at', 'estimated_value']);
        $total = $leads->count();
        $converted = $leads->where('status', LeadStage::CONVERTED);
        $lost = $leads->where('status', LeadStage::LOST);
        $qualified = $leads->filter(fn ($l) => $l->qualified_at !== null || $l->status === LeadStage::CONVERTED);
        $rate = fn (int $part, int $all) => $all ? round($part / $all * 100, 1) : 0.0;
        $days = $converted->filter(fn ($l) => $l->converted_at)->map(fn ($l) => $l->created_at->diffInHours($l->converted_at) / 24);

        $stages = Lead::statuses();
        $byStage = collect($stages)->map(fn ($s, $key) => ['label' => __($s[0]), 'color' => $s[1], 'count' => $leads->where('status', $key)->count()]);

        $sources = LeadSource::pluck('name', 'id');
        $bySource = $leads->groupBy('lead_source_id')->map(fn ($g, $id) => [
            'label' => isset($sources[$id]) ? __($sources[$id]) : __('Không rõ'), 'total' => $g->count(),
            'converted' => $g->where('status', LeadStage::CONVERTED)->count(),
            'rate' => $rate($g->where('status', LeadStage::CONVERTED)->count(), $g->count()),
            'value' => (float) $g->sum('estimated_value'),
        ])->sortByDesc('total')->values();

        $byOwner = collect();
        if ($user->can('leads.view_all')) {
            $names = User::whereIn('id', $leads->pluck('assigned_to')->filter()->unique())->pluck('name', 'id');
            $byOwner = $leads->groupBy('assigned_to')->map(fn ($g, $id) => [
                'label' => $names[$id] ?? __('Chưa phân công'), 'total' => $g->count(),
                'converted' => $g->where('status', LeadStage::CONVERTED)->count(),
                'rate' => $rate($g->where('status', LeadStage::CONVERTED)->count(), $g->count()),
            ])->sortByDesc('total')->values();
        }

        $reasons = LostReason::pluck('name', 'id');
        $byReason = $lost->groupBy(fn ($l) => $l->lost_reason_id ?? 0)->map(fn ($g, $id) => [
            'label' => $id ? ($reasons[$id] ?? __('Lý do đã xóa')) : __('Chưa ghi lý do'), 'count' => $g->count(),
        ])->sortByDesc('count')->values();

        // Lead mới theo tuần (12 tuần gần nhất, cùng phạm vi quyền)
        $weeks = collect(range(11, 0))->map(fn ($i) => now()->startOfWeek()->subWeeks($i));
        $weekly = Lead::visibleTo($user)->where('created_at', '>=', $weeks->first())->get(['created_at'])
            ->groupBy(fn ($l) => $l->created_at->copy()->startOfWeek()->toDateString());

        return view('leads.analytics', [
            'from' => $from, 'to' => $to, 'period' => $request->input('period', 'this_quarter'),
            'kpi' => [
                'total' => $total,
                'qualified' => $qualified->count(), 'qualified_rate' => $rate($qualified->count(), $total),
                'converted' => $converted->count(), 'conversion_rate' => $rate($converted->count(), $total),
                'lost' => $lost->count(), 'lost_rate' => $rate($lost->count(), $total),
                'open' => $leads->whereIn('status', Lead::openStatuses())->count(),
                'avg_days' => $days->isNotEmpty() ? round($days->avg(), 1) : null,
            ],
            'byStage' => $byStage, 'bySource' => $bySource, 'byOwner' => $byOwner, 'byReason' => $byReason,
            'weekly' => [
                'labels' => $weeks->map(fn (Carbon $w) => $w->format('d/m'))->all(),
                'counts' => $weeks->map(fn (Carbon $w) => isset($weekly[$w->toDateString()]) ? $weekly[$w->toDateString()]->count() : 0)->all(),
            ],
        ]);
    }
}
