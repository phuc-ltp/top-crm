<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\SalesTarget;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 20 chỉ số điều hành (bước 2.7). Nguyên tắc:
 *  - Mọi số liệu tính trực tiếp từ MySQL, qua visibleTo (phạm vi quyền) + phạm vi tổ chức của model.
 *  - Nhóm chỉ số chỉ xuất hiện khi người xem có quyền xem module tương ứng (không hiện số 0 giả).
 *  - Không chia cho 0: khi mẫu số bằng 0, giá trị là null và giao diện hiện "—" kèm lý do.
 *  - Mỗi chỉ số kèm công thức (hiển thị ở biểu tượng ⓘ).
 *  - Chỉ số "hiện tại" (snapshot) không phụ thuộc kỳ lọc; chỉ số "trong kỳ" có so sánh với kỳ liền trước cùng độ dài.
 */
class ExecutiveMetrics
{
    private Carbon $prevFrom;

    private Carbon $prevTo;

    public function __construct(private User $user, private Carbon $from, private Carbon $to)
    {
        [$this->prevFrom, $this->prevTo] = DashboardService::previousRange($from, $to);
    }

    private function can(string ...$permissions): bool
    {
        foreach ($permissions as $p) {
            if (! $this->user->can($p)) {
                return false;
            }
        }

        return true;
    }

    private static function pct(float|int $part, float|int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    private function dates(Carbon $a, Carbon $b): array
    {
        return [$a->toDateString(), $b->toDateString()];
    }

    /** Một chỉ số. format: number | money | percent | ratio (hiển thị sẵn trong 'display'). */
    private static function metric(string $key, string $label, string $icon, string $format, $value, string $formula, array $extra = []): array
    {
        return array_merge(['key' => $key, 'label' => $label, 'icon' => $icon, 'format' => $format, 'value' => $value, 'formula' => $formula,
            'display' => null, 'sub' => null, 'change' => null, 'compare' => false, 'snapshot' => false, 'url' => null, 'empty' => null], $extra);
    }

    /** @return array<int, array{key: string, label: string, demo: bool, metrics: array}> */
    public function groups(): array
    {
        return array_values(array_filter([
            $this->customerGroup(),
            $this->salesGroup(),
            $this->financeGroup(),
            $this->teamGroup(),
        ], fn ($g) => $g && $g['metrics']));
    }

    // ---------------------------------------------------------------- Khách hàng & Lead

    private function customerGroup(): ?array
    {
        $m = [];
        $u = $this->user;
        $period = [$this->from, $this->to];
        $prev = [$this->prevFrom, $this->prevTo];

        if ($this->can('customers.view')) {
            $now = Customer::visibleTo($u)->whereBetween('created_at', $period)->count();
            $before = Customer::visibleTo($u)->whereBetween('created_at', $prev)->count();
            $m[] = self::metric('customers_new', 'Khách hàng mới', 'bi-person-plus', 'number', $now,
                'Số khách hàng được tạo trong kỳ.', ['change' => DashboardService::change($now, $before), 'compare' => true, 'url' => route('customers.index')]);
        }

        if ($this->can('leads.view')) {
            $now = Lead::visibleTo($u)->whereBetween('created_at', $period)->count();
            $before = Lead::visibleTo($u)->whereBetween('created_at', $prev)->count();
            $m[] = self::metric('leads_new', 'Lead mới', 'bi-funnel', 'number', $now,
                'Số lead được tạo trong kỳ.', ['change' => DashboardService::change($now, $before), 'compare' => true, 'url' => route('leads.index')]);

            // Đạt điều kiện trong kỳ; lead chuyển đổi thẳng (không qua giai đoạn đủ điều kiện) tính theo ngày chuyển đổi
            $qualified = fn (array $range) => Lead::visibleTo($u)->where(fn ($w) => $w->whereBetween('qualified_at', $range)
                ->orWhere(fn ($c) => $c->whereNull('qualified_at')->whereBetween('converted_at', $range)))->count();
            $q = $qualified($period);
            $qualifiedKeys = app(\App\Support\LeadStages::class)->all()->where('is_qualified', true)->pluck('key');
            $inStage = $qualifiedKeys->isEmpty() ? 0 : Lead::visibleTo($u)->whereIn('status', $qualifiedKeys)->count();
            $m[] = self::metric('leads_qualified', 'Lead đủ điều kiện', 'bi-patch-check', 'number', $q,
                'Số lead đạt giai đoạn "đủ điều kiện" trong kỳ (theo ngày đạt điều kiện). Lead chuyển đổi thẳng mà chưa qua giai đoạn này được tính theo ngày chuyển đổi.',
                ['change' => DashboardService::change($q, $qualified($prev)), 'compare' => true, 'url' => route('leads.index'),
                    'sub' => __('Đang ở giai đoạn đủ điều kiện: :n', ['n' => $inStage])]);

            $converted = Lead::visibleTo($u)->whereBetween('created_at', $period)->whereNotNull('converted_at')->count();
            $m[] = self::metric('lead_conversion', 'Tỷ lệ chuyển đổi lead', 'bi-arrow-left-right', 'percent', self::pct($converted, $now),
                'Lead tạo trong kỳ đã chuyển thành khách hàng ÷ tổng lead tạo trong kỳ × 100%.',
                ['sub' => __(':a / :b lead', ['a' => $converted, 'b' => $now]), 'empty' => 'Chưa có lead trong kỳ']);
        }

        if ($this->can('customers.view', 'orders.view')) {
            $buyers = fn (Carbon $a, Carbon $b) => SalesOrder::visibleTo($u)->where('status', '!=', 'cancelled')
                ->whereBetween('order_date', $this->dates($a, $b))->distinct()->pluck('customer_id');
            $base = $buyers($this->prevFrom, $this->prevTo);
            $kept = $base->intersect($buyers($this->from, $this->to))->count();
            $m[] = self::metric('retention', 'Giữ chân khách hàng', 'bi-arrow-repeat', 'percent', self::pct($kept, $base->count()),
                'Khách hàng có đơn hàng (không tính đơn hủy) ở kỳ trước VÀ mua lại trong kỳ này ÷ số khách hàng có đơn ở kỳ trước × 100%.',
                ['sub' => __(':a / :b khách mua lại', ['a' => $kept, 'b' => $base->count()]), 'empty' => 'Kỳ trước chưa có khách mua hàng']);
        }

        return ['key' => 'customers', 'label' => 'Khách hàng & Lead', 'icon' => 'bi-people', 'metrics' => $m,
            'demo' => Customer::visibleTo($u)->where('is_demo', true)->exists() || ($this->can('leads.view') && Lead::visibleTo($u)->where('is_demo', true)->exists())];
    }

    // ---------------------------------------------------------------- Bán hàng

    private function salesGroup(): ?array
    {
        if (! $this->can('opportunities.view')) {
            return null;
        }
        $u = $this->user;
        $open = fn () => Opportunity::visibleTo($u)->open();
        $closed = fn (bool $won) => Opportunity::visibleTo($u)->whereBetween('closed_at', [$this->from, $this->to])
            ->whereHas('stage', fn ($s) => $s->where($won ? 'is_won' : 'is_lost', true));

        $won = $closed(true)->count();
        $wonValue = (float) $closed(true)->sum('value');
        $lost = $closed(false)->count();
        $weighted = (float) $open()->whereBetween('expected_close_date', $this->dates($this->from, $this->to))
            ->sum(DB::raw('value * probability / 100'));

        $m = [
            self::metric('opportunities_open', 'Cơ hội đang mở', 'bi-kanban', 'number', $open()->count(),
                'Số cơ hội chưa đóng (không ở giai đoạn Thắng hoặc Thất bại) tại thời điểm hiện tại.', ['snapshot' => true, 'url' => route('opportunities.index')]),
            self::metric('pipeline_value', 'Giá trị pipeline', 'bi-stack', 'money', (float) $open()->sum('value'),
                'Tổng giá trị các cơ hội đang mở tại thời điểm hiện tại.', ['snapshot' => true, 'url' => route('opportunities.index')]),
            self::metric('forecast', 'Dự báo doanh số kỳ', 'bi-graph-up-arrow', 'money', round($wonValue + $weighted, 2),
                'Giá trị cơ hội đã thắng trong kỳ + Σ (giá trị × xác suất) của cơ hội đang mở dự kiến chốt trong kỳ. Cùng công thức với trang Dự báo.',
                ['sub' => __('Đã thắng :a + có trọng số :b', ['a' => money_short($wonValue), 'b' => money_short($weighted)]),
                    'url' => route('opportunities.forecast')]),
            self::metric('won_lost', 'Thắng / thua trong kỳ', 'bi-trophy', 'ratio', $won,
                'Số cơ hội chuyển sang giai đoạn Thắng / Thất bại trong kỳ (theo ngày đóng).', ['display' => $won.' / '.$lost]),
            self::metric('win_rate', 'Tỷ lệ thắng', 'bi-bullseye', 'percent', self::pct($won, $won + $lost),
                'Cơ hội thắng ÷ (thắng + thất bại) trong kỳ × 100%. Cơ hội còn mở không được tính.', ['empty' => 'Chưa có cơ hội đóng trong kỳ']),
            self::metric('avg_deal', 'Giá trị deal trung bình', 'bi-cash-coin', 'money', $won > 0 ? round($wonValue / $won, 2) : null,
                'Tổng giá trị cơ hội thắng trong kỳ ÷ số cơ hội thắng trong kỳ.', ['empty' => 'Chưa có cơ hội thắng trong kỳ']),
        ];

        return ['key' => 'sales', 'label' => 'Bán hàng', 'icon' => 'bi-kanban', 'metrics' => $m,
            'demo' => Opportunity::visibleTo($u)->where('is_demo', true)->exists()];
    }

    // ---------------------------------------------------------------- Tài chính

    private function invoiceIds()
    {
        return Invoice::visibleTo($this->user)->where('status', 'issued')->select('id');
    }

    private function paid(Carbon $a, Carbon $b): float
    {
        return (float) Payment::whereIn('invoice_id', $this->invoiceIds())->whereBetween('payment_date', $this->dates($a, $b))->sum('amount');
    }

    private function financeGroup(): ?array
    {
        if (! $this->can('invoices.view')) {
            return null;
        }
        $u = $this->user;
        $revenue = $this->paid($this->from, $this->to);
        $prevRevenue = $this->paid($this->prevFrom, $this->prevTo);
        $unpaid = fn () => Invoice::visibleTo($u)->where('status', 'issued')->where('payment_status', '!=', 'paid');
        $overdue = fn () => $unpaid()->whereNotNull('due_date')->where('due_date', '<', now()->toDateString());
        $remaining = DB::raw('total - amount_paid - credited_amount');

        $m = [
            self::metric('revenue', 'Doanh thu (đã thu)', 'bi-currency-dollar', 'money', $revenue,
                'Tổng tiền khách đã thanh toán trong kỳ (theo ngày thanh toán) cho các hóa đơn đã phát hành, trừ các khoản hoàn tiền.',
                ['change' => DashboardService::change($revenue, $prevRevenue), 'compare' => true, 'url' => route('invoices.index')]),
            self::metric('revenue_growth', 'Tăng trưởng doanh thu', 'bi-graph-up', 'percent', DashboardService::change($revenue, $prevRevenue),
                '(Doanh thu kỳ này − doanh thu kỳ trước) ÷ doanh thu kỳ trước × 100%. Kỳ trước là khoảng liền trước có cùng số ngày.',
                ['sub' => __('Kỳ trước: :a', ['a' => money_short($prevRevenue)]), 'empty' => 'Kỳ trước chưa có doanh thu', 'signed' => true]),
            self::metric('invoices_unpaid', 'Hóa đơn chờ thanh toán', 'bi-receipt', 'number', $unpaid()->count(),
                'Số hóa đơn đã phát hành nhưng chưa thanh toán đủ, tại thời điểm hiện tại.',
                ['snapshot' => true, 'sub' => __('Quá hạn: :n', ['n' => $overdue()->count()]), 'url' => route('invoices.index')]),
            self::metric('receivable', 'Công nợ phải thu', 'bi-wallet2', 'money', (float) $unpaid()->sum($remaining),
                'Σ (tổng tiền − giảm trừ − đã thanh toán) của các hóa đơn đã phát hành chưa thanh toán đủ, tại thời điểm hiện tại.',
                ['snapshot' => true, 'sub' => __('Quá hạn: :a', ['a' => money_short((float) $overdue()->sum($remaining))])]),
        ];

        return ['key' => 'finance', 'label' => 'Tài chính', 'icon' => 'bi-cash-stack', 'metrics' => $m,
            'demo' => Invoice::visibleTo($u)->where('is_demo', true)->exists()];
    }

    // ---------------------------------------------------------------- Đội ngũ & vận hành

    /** Người dùng có chỉ tiêu được tính: quản lý (xem báo cáo + xem mọi hóa đơn) thấy cả đội, còn lại chỉ chính mình. */
    private function targetUserIds(): ?array
    {
        return $this->can('reports.view', 'invoices.view_all') ? null : [$this->user->id];
    }

    private function teamGroup(): ?array
    {
        $u = $this->user;
        $m = [];

        if ($this->can('tasks.view')) {
            $m[] = self::metric('tasks_overdue', 'Công việc quá hạn', 'bi-alarm', 'number',
                Task::visibleTo($u)->open()->whereNotNull('due_date')->where('due_date', '<', now())->count(),
                'Số công việc chưa hoàn thành đã quá hạn, tại thời điểm hiện tại.', ['snapshot' => true, 'url' => route('tasks.index'), 'bad' => true]);

            $due = Task::visibleTo($u)->where('status', '!=', 'cancelled')->whereBetween('due_date', [$this->from, $this->to]);
            $total = (clone $due)->count();
            $onTime = (clone $due)->where('status', 'completed')->whereNotNull('completed_at')->whereColumn('completed_at', '<=', 'due_date')->count();
            $m[] = self::metric('tasks_on_time', 'Hoàn thành đúng hạn', 'bi-check2-circle', 'percent', self::pct($onTime, $total),
                'Công việc có hạn trong kỳ đã hoàn thành trước hoặc đúng hạn ÷ tổng công việc có hạn trong kỳ (không tính việc đã hủy) × 100%.',
                ['sub' => __(':a / :b việc', ['a' => $onTime, 'b' => $total]), 'empty' => 'Không có công việc đến hạn trong kỳ']);
        }

        if ($this->can('tickets.view')) {
            $m[] = self::metric('tickets_open', 'Ticket đang mở', 'bi-headset', 'number',
                SupportTicket::visibleTo($u)->whereIn('status', ['open', 'in_progress', 'waiting'])->count(),
                'Số ticket hỗ trợ ở trạng thái Mới mở, Đang xử lý hoặc Chờ phản hồi, tại thời điểm hiện tại.',
                ['snapshot' => true, 'sub' => __('Mới trong kỳ: :n', ['n' => SupportTicket::visibleTo($u)->whereBetween('created_at', [$this->from, $this->to])->count()]),
                    'url' => route('tickets.index')]);
        }

        if ($this->can('invoices.view')) {
            // Chỉ tiêu theo tháng của ngày cuối kỳ đang xem
            $month = $this->to->copy()->startOfMonth();
            $ids = $this->targetUserIds();
            $targets = SalesTarget::where('year', $month->year)->where('month', $month->month)
                ->when($ids, fn ($q) => $q->whereIn('user_id', $ids))->where('target_amount', '>', 0)->pluck('target_amount', 'user_id');
            $actual = $this->paid($month, $month->copy()->endOfMonth());
            $targetTotal = (float) $targets->sum();
            $label = __('tháng :m', ['m' => $month->format('m/Y')]);
            $m[] = self::metric('target_progress', 'Hoàn thành chỉ tiêu', 'bi-flag', 'percent', self::pct($actual, $targetTotal),
                'Doanh thu đã thu trong tháng ÷ tổng chỉ tiêu doanh số của tháng × 100%. Tháng = tháng chứa ngày cuối của kỳ đang xem.',
                ['sub' => $targetTotal > 0 ? __(':a / :b (:month)', ['a' => money_short($actual), 'b' => money_short($targetTotal), 'month' => $label]) : $label,
                    'empty' => 'Chưa đặt chỉ tiêu cho tháng này']);

            // Nhân viên đạt chỉ tiêu: thực thu trên hóa đơn mình phụ trách ≥ chỉ tiêu của mình
            $byUser = $targets->isEmpty() ? collect() : Payment::query()->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                ->whereIn('payments.invoice_id', $this->invoiceIds())->whereIn('invoices.assigned_to', $targets->keys())
                ->whereBetween('payments.payment_date', $this->dates($month, $month->copy()->endOfMonth()))
                ->selectRaw('invoices.assigned_to as uid, SUM(payments.amount) as total')->groupBy('invoices.assigned_to')->pluck('total', 'uid');
            $achieved = $targets->filter(fn ($target, $uid) => (float) ($byUser[$uid] ?? 0) >= (float) $target)->count();
            $m[] = self::metric('kpi_achievers', 'Nhân viên đạt chỉ tiêu', 'bi-award', 'ratio', $targets->isEmpty() ? null : $achieved,
                'Số nhân viên có doanh thu đã thu (trên hóa đơn mình phụ trách) trong tháng ≥ chỉ tiêu của mình ÷ số nhân viên được giao chỉ tiêu tháng đó.',
                ['display' => $targets->isEmpty() ? null : $achieved.' / '.$targets->count(),
                    'sub' => $targets->isEmpty() ? $label : __(':p% nhân viên (:month)', ['p' => self::pct($achieved, $targets->count()), 'month' => $label]),
                    'empty' => 'Chưa đặt chỉ tiêu cho tháng này']);
        }

        return ['key' => 'team', 'label' => 'Đội ngũ & vận hành', 'icon' => 'bi-person-workspace', 'metrics' => $m,
            'demo' => ($this->can('tasks.view') && Task::visibleTo($u)->where('is_demo', true)->exists())
                || ($this->can('tickets.view') && SupportTicket::visibleTo($u)->where('is_demo', true)->exists())];
    }
}
