<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\PipelineStage;
use App\Models\SalesTarget;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Chỉ số Dashboard — tất cả tính từ MySQL theo phạm vi quyền của người xem.
 *
 * Định nghĩa:
 *  - Doanh thu = tổng tiền KHÁCH ĐÃ THANH TOÁN (bảng payments) theo ngày thanh toán (ghi nhận theo dòng tiền).
 *  - Giá trị pipeline = tổng giá trị các cơ hội chưa đóng (không ở giai đoạn Thắng/Thất bại).
 *  - Tỷ lệ chuyển đổi lead = số lead tạo trong kỳ đã chuyển đổi / tổng lead tạo trong kỳ.
 */
class DashboardService
{
    public function __construct(private User $user) {}

    public static function range(?string $period, ?string $from, ?string $to): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_quarter' => [$now->copy()->firstOfQuarter(), $now->copy()->lastOfQuarter()->endOfDay()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                $from ? Carbon::parse($from)->startOfDay() : $now->copy()->startOfMonth(),
                $to ? Carbon::parse($to)->endOfDay() : $now->copy()->endOfDay(),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    private function invoiceIds()
    {
        return Invoice::visibleTo($this->user)->where('status', 'issued')->select('id');
    }

    public function kpis(Carbon $from, Carbon $to): array
    {
        $u = $this->user;
        $leadsInRange = Lead::visibleTo($u)->whereBetween('created_at', [$from, $to]);
        $leadsTotal = (clone $leadsInRange)->count();
        $leadsConverted = (clone $leadsInRange)->where('status', 'converted')->count();

        $openOpps = Opportunity::visibleTo($u)->open();

        $revenue = (float) Payment::whereIn('invoice_id', $this->invoiceIds())->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])->sum('amount');
        [$prevFrom, $prevTo] = self::previousRange($from, $to);
        $prevRevenue = (float) Payment::whereIn('invoice_id', $this->invoiceIds())->whereBetween('payment_date', [$prevFrom->toDateString(), $prevTo->toDateString()])->sum('amount');

        $customersNew = Customer::visibleTo($u)->whereBetween('created_at', [$from, $to])->count();
        $customersNewPrev = Customer::visibleTo($u)->whereBetween('created_at', [$prevFrom, $prevTo])->count();
        $leadsNewPrev = Lead::visibleTo($u)->whereBetween('created_at', [$prevFrom, $prevTo])->count();
        $oppCreated = Opportunity::visibleTo($u)->whereBetween('created_at', [$from, $to])->count();
        $oppCreatedPrev = Opportunity::visibleTo($u)->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        return [
            'customers_total' => Customer::visibleTo($u)->count(),
            'customers_new' => $customersNew,
            'customers_new_change' => self::change($customersNew, $customersNewPrev),
            'leads_new_change' => self::change($leadsTotal, $leadsNewPrev),
            'opportunities_created' => $oppCreated,
            'opportunities_created_change' => self::change($oppCreated, $oppCreatedPrev),
            'leads_total' => Lead::visibleTo($u)->count(),
            'leads_new' => $leadsTotal,
            'leads_converted' => Lead::visibleTo($u)->whereBetween('converted_at', [$from, $to])->count(),
            'conversion_rate' => $leadsTotal > 0 ? round($leadsConverted / $leadsTotal * 100, 1) : 0,
            'opportunities_open' => (clone $openOpps)->count(),
            'opportunities_total' => Opportunity::visibleTo($u)->count(),
            'pipeline_value' => (float) (clone $openOpps)->sum('value'),
            'revenue' => $revenue,
            'revenue_change' => $prevRevenue > 0 ? round(($revenue - $prevRevenue) / $prevRevenue * 100, 1) : null,
            'receivable' => (float) Invoice::visibleTo($u)->where('status', 'issued')->where('payment_status', '!=', 'paid')->sum(DB::raw('total - amount_paid - credited_amount')),
            'tasks_open' => Task::visibleTo($u)->open()->count(),
            'tasks_late' => Task::visibleTo($u)->open()->where('due_date', '<', now())->count(),
        ];
    }

    /** Kỳ liền trước có cùng độ dài (VD: tháng này → tháng trước cùng số ngày). */
    public static function previousRange(Carbon $from, Carbon $to): array
    {
        $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $prevTo = $from->copy()->subDay()->endOfDay();

        return [$prevTo->copy()->subDays($days - 1)->startOfDay(), $prevTo];
    }

    /** % thay đổi so với kỳ trước; null khi kỳ trước bằng 0 (không chia cho 0). */
    public static function change(float|int $current, float|int $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    /**
     * Kết quả cơ hội: thắng / đang xử lý / thất bại (toàn bộ cơ hội được phép xem).
     * Tỷ lệ thắng = thắng / (thắng + thất bại) — chỉ tính cơ hội đã đóng.
     */
    public function opportunityOutcomes(): array
    {
        $rows = Opportunity::visibleTo($this->user)->join('pipeline_stages as ps', 'ps.id', '=', 'opportunities.pipeline_stage_id')
            ->selectRaw('SUM(ps.is_won = 1) as won, SUM(ps.is_lost = 1) as lost, SUM(ps.is_won = 0 AND ps.is_lost = 0) as open_count')
            ->first();
        $won = (int) $rows->won;
        $lost = (int) $rows->lost;
        $open = (int) $rows->open_count;
        $total = $won + $lost + $open;

        return [
            'won' => $won, 'lost' => $lost, 'open' => $open, 'total' => $total,
            'win_rate' => $won + $lost > 0 ? round($won / ($won + $lost) * 100) : null,
            'pct' => fn ($n) => $total > 0 ? round($n / $total * 100) : 0,
        ];
    }

    /** Cơ hội đang mở giá trị cao nhất. */
    public function topOpportunities(int $limit = 4)
    {
        return Opportunity::visibleTo($this->user)->open()->with('customer:id,name')
            ->orderByDesc('value')->limit($limit)->get(['id', 'title', 'value', 'probability', 'customer_id', 'expected_close_date']);
    }

    /** Xếp hạng doanh thu thực thu theo nhân viên phụ trách hóa đơn trong kỳ. */
    public function topSellers(Carbon $from, Carbon $to, int $limit = 5)
    {
        $totals = Payment::query()
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->whereIn('payments.invoice_id', $this->invoiceIds())
            ->whereNotNull('invoices.assigned_to')
            ->whereBetween('payments.payment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('invoices.assigned_to as uid, SUM(payments.amount) as total')
            ->groupBy('invoices.assigned_to')->orderByDesc('total')->limit($limit)->pluck('total', 'uid');
        $users = User::whereIn('id', $totals->keys())->get(['id', 'name', 'avatar', 'position'])->keyBy('id');

        return $totals->map(fn ($total, $uid) => (object) ['user' => $users[$uid] ?? null, 'total' => (float) $total])
            ->filter(fn ($r) => $r->user)->values();
    }

    /** Công việc của tôi: đến hạn hôm nay trước, rồi các việc sắp tới. */
    public function myTasks(int $limit = 5)
    {
        return Task::where('assigned_to', $this->user->id)->open()->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays(7)->endOfDay())
            ->orderBy('due_date')->limit($limit)->get(['id', 'title', 'due_date', 'priority', 'status']);
    }

    /** 12 tháng gần nhất: doanh thu thực thu & giá trị hóa đơn phát hành. */
    public function monthlyRevenue(int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $labels = [];
        $keys = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $labels[] = $m->format('m/Y');
            $keys[] = $m->format('Y-m');
        }

        $paid = Payment::whereIn('invoice_id', $this->invoiceIds())->where('payment_date', '>=', $start)
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym, SUM(amount) as total")->groupBy('ym')->pluck('total', 'ym');
        $invoiced = Invoice::visibleTo($this->user)->where('status', 'issued')->where('issue_date', '>=', $start)
            ->selectRaw("DATE_FORMAT(issue_date, '%Y-%m') as ym, SUM(total) as total")->groupBy('ym')->pluck('total', 'ym');

        return [
            'labels' => $labels,
            'paid' => array_map(fn ($k) => (float) ($paid[$k] ?? 0), $keys),
            'invoiced' => array_map(fn ($k) => (float) ($invoiced[$k] ?? 0), $keys),
        ];
    }

    /** Phễu bán hàng: số lượng & giá trị cơ hội theo giai đoạn. */
    public function funnel(): array
    {
        // Phễu theo pipeline mặc định (các pipeline khác có giai đoạn riêng, không gộp chung một phễu)
        $stages = PipelineStage::where('pipeline_id', \App\Models\Pipeline::default()?->id)->ordered()->get();
        $rows = Opportunity::visibleTo($this->user)->whereIn('pipeline_stage_id', $stages->pluck('id'))->selectRaw('pipeline_stage_id, COUNT(*) as cnt, SUM(value) as val')
            ->groupBy('pipeline_stage_id')->get()->keyBy('pipeline_stage_id');

        return [
            'labels' => $stages->pluck('name')->all(),
            'colors' => $stages->pluck('color')->all(),
            'counts' => $stages->map(fn ($s) => (int) ($rows[$s->id]->cnt ?? 0))->all(),
            'values' => $stages->map(fn ($s) => (float) ($rows[$s->id]->val ?? 0))->all(),
        ];
    }

    public function leadStatus(Carbon $from, Carbon $to): array
    {
        $rows = Lead::visibleTo($this->user)->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        // Màu biểu đồ theo màu badge của từng giai đoạn (tổ chức tự cấu hình)
        $hex = ['info' => '#0EA5E9', 'primary' => '#3B82F6', 'warning' => '#F59E0B', 'orange' => '#FF6B00', 'success' => '#16A34A',
            'danger' => '#DC2626', 'purple' => '#8B5CF6', 'secondary' => '#94A3B8'];
        $stages = Lead::statuses();

        return [
            'labels' => array_map(fn ($s) => __($s[0]), array_values($stages)),
            'counts' => array_map(fn ($k) => (int) ($rows[$k] ?? 0), array_keys($stages)),
            'colors' => array_map(fn ($s) => $hex[$s[1]] ?? '#94A3B8', array_values($stages)),
        ];
    }

    /** Tăng trưởng khách hàng: mới mỗi tháng và lũy kế. */
    public function customerGrowth(int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $base = Customer::visibleTo($this->user)->where('created_at', '<', $start)->count();
        $rows = Customer::visibleTo($this->user)->where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt")->groupBy('ym')->pluck('cnt', 'ym');

        $labels = $new = $total = [];
        $running = $base;
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $count = (int) ($rows[$m->format('Y-m')] ?? 0);
            $running += $count;
            $labels[] = $m->format('m/Y');
            $new[] = $count;
            $total[] = $running;
        }

        return compact('labels', 'new', 'total');
    }

    /** Hiệu suất bán hàng: doanh thu thực thu theo nhân viên phụ trách hóa đơn. */
    public function salesPerformance(Carbon $from, Carbon $to): array
    {
        $rows = Payment::query()
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->join('users', 'users.id', '=', 'invoices.assigned_to')
            ->whereIn('payments.invoice_id', $this->invoiceIds())
            ->whereBetween('payments.payment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('users.name as name, SUM(payments.amount) as total')
            ->groupBy('users.id', 'users.name')->orderByDesc('total')->limit(10)->get();

        return ['labels' => $rows->pluck('name')->all(), 'values' => $rows->pluck('total')->map(fn ($v) => (float) $v)->all()];
    }

    /** Doanh thu theo tháng trong năm so với tổng chỉ tiêu. */
    public function targetVsActual(int $year): array
    {
        $userIds = $this->user->can('reports.view') && $this->user->can('invoices.view_all') ? null : [$this->user->id];
        $targets = SalesTarget::where('year', $year)->when($userIds, fn ($q) => $q->whereIn('user_id', $userIds))
            ->selectRaw('month, SUM(target_amount) as total')->groupBy('month')->pluck('total', 'month');
        $actual = Payment::whereIn('invoice_id', $this->invoiceIds())->whereYear('payment_date', $year)
            ->selectRaw('MONTH(payment_date) as m, SUM(amount) as total')->groupBy('m')->pluck('total', 'm');

        return [
            'labels' => array_map(fn ($m) => 'T'.$m, range(1, 12)),
            'target' => array_map(fn ($m) => (float) ($targets[$m] ?? 0), range(1, 12)),
            'actual' => array_map(fn ($m) => (float) ($actual[$m] ?? 0), range(1, 12)),
        ];
    }

    public function recentActivities(int $limit = 8)
    {
        return ActivityLog::with('user:id,name,avatar')
            ->when(! $this->user->can('activity_logs.view'), fn ($q) => $q->where('user_id', $this->user->id))
            ->whereNotIn('action', ['login', 'logout', 'login_failed'])
            ->latest('created_at')->limit($limit)->get();
    }

    public function upcomingTasks(int $limit = 6)
    {
        return Task::visibleTo($this->user)->open()->whereNotNull('due_date')
            ->where('assigned_to', $this->user->id)->orderBy('due_date')->limit($limit)->get();
    }

    /** Số hóa đơn phát hành trong kỳ và % thay đổi so với kỳ trước. */
    public function invoicesIssued(Carbon $from, Carbon $to): array
    {
        [$prevFrom, $prevTo] = self::previousRange($from, $to);
        $q = fn ($a, $b) => Invoice::visibleTo($this->user)->where('status', 'issued')
            ->whereBetween('issue_date', [$a->toDateString(), $b->toDateString()]);
        $count = $q($from, $to)->count();

        return ['count' => $count, 'total' => (float) $q($from, $to)->sum('total'), 'change' => self::change($count, $q($prevFrom, $prevTo)->count())];
    }

    /**
     * Chuỗi nhỏ (sparkline) cho 4 thẻ KPI: chia kỳ đang xem thành tối đa 12 khoảng bằng nhau.
     * Trả về ['revenue' => [...], 'customers' => [...], 'opportunities' => [...], 'invoices' => [...]].
     */
    public function sparklines(Carbon $from, Carbon $to, int $points = 12): array
    {
        $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $points = max(1, min($points, $days));
        $size = (int) ceil($days / $points);
        $bucket = function ($rows) use ($from, $size, $points) {
            $out = array_fill(0, $points, 0.0);
            foreach ($rows as $day => $value) {
                $i = intdiv((int) $from->copy()->startOfDay()->diffInDays(Carbon::parse($day)), $size);
                if ($i >= 0 && $i < $points) {
                    $out[$i] += (float) $value;
                }
            }

            return $out;
        };
        $a = $from->toDateString();
        $b = $to->toDateString();

        return [
            'revenue' => $bucket(Payment::whereIn('invoice_id', $this->invoiceIds())->whereBetween('payment_date', [$a, $b])
                ->selectRaw('DATE(payment_date) as d, SUM(amount) as v')->groupBy('d')->pluck('v', 'd')),
            'customers' => $bucket(Customer::visibleTo($this->user)->whereBetween('created_at', [$from, $to])
                ->selectRaw('DATE(created_at) as d, COUNT(*) as v')->groupBy('d')->pluck('v', 'd')),
            'opportunities' => $bucket(Opportunity::visibleTo($this->user)->whereBetween('created_at', [$from, $to])
                ->selectRaw('DATE(created_at) as d, COUNT(*) as v')->groupBy('d')->pluck('v', 'd')),
            'invoices' => $bucket(Invoice::visibleTo($this->user)->where('status', 'issued')->whereBetween('issue_date', [$a, $b])
                ->selectRaw('DATE(issue_date) as d, COUNT(*) as v')->groupBy('d')->pluck('v', 'd')),
        ];
    }

    /**
     * Doanh thu theo độ chi tiết: day = 30 ngày gần nhất, week = 12 tuần gần nhất,
     * month = 12 tháng của năm $year, year = 5 năm gần nhất. Thực thu (payments) & hóa đơn phát hành.
     */
    public function revenueSeries(string $granularity, ?int $year = null): array
    {
        $year ??= (int) now()->year;
        [$start, $end, $sqlFormat, $steps, $step, $keyFormat, $label] = match ($granularity) {
            'day' => [now()->subDays(29)->startOfDay(), now()->endOfDay(), '%Y-%m-%d', 30, 'addDays', 'Y-m-d', fn ($d) => $d->format('d/m')],
            'week' => [now()->startOfWeek()->subWeeks(11), now()->endOfWeek(), '%x-%v', 12, 'addWeeks', 'o-W', fn ($d) => __('Tuần :n', ['n' => (int) $d->format('W')])],
            'year' => [now()->startOfYear()->subYears(4), now()->endOfYear(), '%Y', 5, 'addYears', 'Y', fn ($d) => $d->format('Y')],
            default => [Carbon::create($year)->startOfYear(), Carbon::create($year)->endOfYear(), '%Y-%m', 12, 'addMonths', 'Y-m', fn ($d) => app()->getLocale() === 'vi' ? 'T'.$d->month : $d->format('M')],
        };

        $paid = Payment::whereIn('invoice_id', $this->invoiceIds())->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(payment_date, '{$sqlFormat}') as k, SUM(amount) as total")->groupBy('k')->pluck('total', 'k');
        $invoiced = Invoice::visibleTo($this->user)->where('status', 'issued')->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(issue_date, '{$sqlFormat}') as k, SUM(total) as total")->groupBy('k')->pluck('total', 'k');

        $labels = $p = $i = [];
        for ($n = 0; $n < $steps; $n++) {
            $d = $start->copy()->{$step}($n);
            $k = $d->format($keyFormat);
            $labels[] = $label($d);
            $p[] = (float) ($paid[$k] ?? 0);
            $i[] = (float) ($invoiced[$k] ?? 0);
        }

        return ['labels' => $labels, 'paid' => $p, 'invoiced' => $i, 'total_paid' => array_sum($p), 'total_invoiced' => array_sum($i)];
    }

    /** Các năm có dữ liệu thanh toán/hóa đơn (để chọn năm trên biểu đồ). */
    public function revenueYears(): array
    {
        $min = Payment::whereIn('invoice_id', $this->invoiceIds())->min('payment_date');
        $first = $min ? (int) Carbon::parse($min)->year : (int) now()->year;

        return range((int) now()->year, min($first, (int) now()->year));
    }

    /**
     * Phễu lũy kế: số cơ hội (không tính thất bại) đã đi tới ít nhất từng giai đoạn.
     * Cơ hội ở giai đoạn sau được tính là đã qua các giai đoạn trước.
     */
    public function funnelCumulative(): array
    {
        $stages = PipelineStage::where('pipeline_id', \App\Models\Pipeline::default()?->id)->ordered()->where('is_lost', false)->get();
        $counts = Opportunity::visibleTo($this->user)->whereIn('pipeline_stage_id', $stages->pluck('id'))->selectRaw('pipeline_stage_id, COUNT(*) as cnt')
            ->groupBy('pipeline_stage_id')->pluck('cnt', 'pipeline_stage_id');

        $rows = [];
        $running = 0;
        foreach ($stages->reverse() as $s) {
            $running += (int) ($counts[$s->id] ?? 0);
            $rows[] = ['name' => $s->name, 'color' => $s->color, 'count' => $running];
        }
        $rows = array_reverse($rows);
        $top = $rows[0]['count'] ?? 0;

        return array_map(fn ($r) => $r + ['pct' => $top > 0 ? round($r['count'] / $top * 100) : 0], $rows);
    }

    /** Khách hàng mới trong kỳ theo nguồn. */
    public function customerSources(Carbon $from, Carbon $to): array
    {
        $rows = Customer::visibleTo($this->user)->whereBetween('customers.created_at', [$from, $to])
            ->leftJoin('lead_sources as ls', 'ls.id', '=', 'customers.lead_source_id')
            ->selectRaw('ls.name as name, COUNT(*) as cnt')
            ->groupBy('name')->orderByDesc('cnt')->orderBy('name')->get();
        $total = (int) $rows->sum('cnt');

        return [
            'total' => $total,
            'items' => $rows->map(fn ($r) => ['name' => $r->name ?? __('Chưa xác định'), 'label' => $r->name ? __($r->name) : __('Chưa xác định'), 'count' => (int) $r->cnt, 'pct' => $total ? round($r->cnt / $total * 100) : 0])->all(),
        ];
    }

    /** Công việc của tôi theo nhóm: hôm nay, quá hạn, sắp đến hạn (3 ngày), hoàn thành hôm nay. */
    public function taskBoard(int $limit = 6): array
    {
        $mine = fn () => Task::where('assigned_to', $this->user->id);
        $today = [now()->startOfDay(), now()->endOfDay()];
        $groups = [
            'all' => $mine()->where(fn ($q) => $q->whereBetween('due_date', $today)->orWhere(fn ($q) => $q->open()->where('due_date', '<', now())))
                ->where('status', '!=', 'cancelled'),
            'overdue' => $mine()->open()->where('due_date', '<', now()),
            'upcoming' => $mine()->open()->whereBetween('due_date', [now(), now()->addDays(3)->endOfDay()]),
            'completed' => $mine()->where('status', 'completed')->whereBetween('completed_at', $today),
        ];

        return collect($groups)->map(fn ($q) => [
            'count' => (clone $q)->count(),
            'items' => $q->orderBy('due_date')->limit($limit)->get(['id', 'title', 'due_date', 'priority', 'status', 'completed_at']),
        ])->all();
    }

    /** Đơn hàng mới nhất trong phạm vi quyền. */
    public function recentOrders(int $limit = 5)
    {
        return \App\Models\SalesOrder::visibleTo($this->user)->with('customer:id,name')
            ->latest('order_date')->latest('id')->limit($limit)->get(['id', 'number', 'customer_id', 'order_date', 'status', 'total']);
    }

    /** Lịch trong ngày: công việc của tôi có hạn trong ngày $day (sắp theo giờ). */
    public function daySchedule(Carbon $day)
    {
        return Task::where('assigned_to', $this->user->id)->where('status', '!=', 'cancelled')
            ->whereBetween('due_date', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->orderBy('due_date')->limit(8)->get(['id', 'title', 'due_date', 'priority', 'status']);
    }
}
