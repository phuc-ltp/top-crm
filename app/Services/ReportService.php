<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Các báo cáo — mọi số liệu tính từ MySQL, giới hạn theo phạm vi quyền của người xem.
 * Mỗi báo cáo trả về: title, definition, headings, rows, summary, chart.
 */
class ReportService
{
    public const TYPES = [
        'customers' => ['Báo cáo khách hàng', 'bi-people'],
        'sales' => ['Báo cáo bán hàng (đơn hàng)', 'bi-bag-check'],
        'revenue' => ['Báo cáo doanh thu', 'bi-cash-stack'],
        'leads' => ['Báo cáo Lead theo nguồn', 'bi-funnel'],
        'employees' => ['Hiệu suất nhân viên', 'bi-person-badge'],
        'campaigns' => ['Hiệu quả chiến dịch', 'bi-megaphone'],
        'tasks' => ['Năng suất công việc', 'bi-check2-square'],
        'tickets' => ['Báo cáo hỗ trợ khách hàng', 'bi-headset'],
    ];

    public function __construct(private User $viewer, private Carbon $from, private Carbon $to, private ?int $userId = null, private ?string $status = null) {}

    public function build(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return ['title' => self::TYPES[$type][0], 'type' => $type] + $this->{$type}();
    }

    /** Lọc khoảng ngày + nhân viên + trạng thái chung. */
    private function scope($query, string $dateColumn, ?string $userColumn = 'assigned_to', ?string $statusColumn = 'status')
    {
        return $query->whereBetween($query->getModel()->qualifyColumn($dateColumn), [$this->from, $this->to])
            ->when($this->userId && $userColumn, fn ($q) => $q->where($q->getModel()->qualifyColumn($userColumn), $this->userId))
            ->when($this->status && $statusColumn, fn ($q) => $q->where($q->getModel()->qualifyColumn($statusColumn), $this->status));
    }

    private function customers(): array
    {
        $items = $this->scope(Customer::visibleTo($this->viewer), 'created_at')->with(['source:id,name', 'assignee:id,name'])
            ->withSum(['invoices as paid_total' => fn ($q) => $q->where('status', 'issued')], 'amount_paid')
            ->orderBy('created_at')->get();

        $bySource = $items->groupBy(fn ($c) => $c->source?->name ?? 'Không rõ')->map->count()->sortDesc();

        return [
            'definition' => 'Khách hàng được tạo trong kỳ. "Đã thanh toán" = tổng tiền khách đã trả trên các hóa đơn đã phát hành (mọi thời điểm).',
            'statuses' => Customer::STATUSES,
            'headings' => ['Mã', 'Khách hàng', 'Loại', 'Nguồn', 'Trạng thái', 'Phụ trách', 'Ngày tạo', 'Đã thanh toán'],
            'money_cols' => [7],
            'rows' => $items->map(fn ($c) => [$c->code, $c->name, Customer::TYPES[$c->type] ?? '', $c->source?->name, label_of(Customer::STATUSES, $c->status),
                $c->assignee?->name, vn_date($c->created_at), (float) $c->paid_total])->all(),
            'summary' => [
                ['Khách hàng mới', $items->count(), 'bi-people'],
                ['Doanh nghiệp', $items->where('type', 'business')->count(), 'bi-building'],
                ['Cá nhân', $items->where('type', 'individual')->count(), 'bi-person'],
                ['VIP', $items->where('status', 'vip')->count(), 'bi-star'],
            ],
            'chart' => ['type' => 'bar', 'label' => 'Khách hàng mới theo nguồn', 'labels' => $bySource->keys()->all(), 'values' => $bySource->values()->all()],
        ];
    }

    private function sales(): array
    {
        $items = $this->scope(SalesOrder::visibleTo($this->viewer), 'order_date')->with(['customer:id,name', 'assignee:id,name'])->orderBy('order_date')->get();
        $byStatus = collect(SalesOrder::STATUSES)->map(fn ($s, $k) => $items->where('status', $k)->sum('total'));

        return [
            'definition' => 'Đơn hàng có ngày đặt trong kỳ. Giá trị đơn = tổng cộng sau chiết khấu và thuế (do hệ thống tính).',
            'statuses' => SalesOrder::STATUSES,
            'headings' => ['Số đơn', 'Ngày đặt', 'Khách hàng', 'Trạng thái', 'Phụ trách', 'Tạm tính', 'Thuế', 'Tổng cộng'],
            'money_cols' => [5, 6, 7],
            'rows' => $items->map(fn ($o) => [$o->number, vn_date($o->order_date), $o->customer?->name, label_of(SalesOrder::STATUSES, $o->status),
                $o->assignee?->name, (float) $o->subtotal, (float) $o->tax_amount, (float) $o->total])->all(),
            'summary' => [
                ['Số đơn hàng', $items->count(), 'bi-bag'],
                ['Tổng giá trị', money($items->where('status', '!=', 'cancelled')->sum('total')), 'bi-cash'],
                ['Đơn hoàn thành', $items->where('status', 'completed')->count(), 'bi-check2-circle'],
                ['Đơn bị hủy', $items->where('status', 'cancelled')->count(), 'bi-x-circle'],
            ],
            'chart' => ['type' => 'bar', 'money' => true, 'label' => 'Giá trị đơn theo trạng thái',
                'labels' => array_map(fn ($s) => $s[0], array_values(SalesOrder::STATUSES)), 'values' => $byStatus->values()->map(fn ($v) => (float) $v)->all()],
        ];
    }

    private function revenue(): array
    {
        $invoiceIds = Invoice::visibleTo($this->viewer)->where('status', 'issued')
            ->when($this->userId, fn ($q) => $q->where('assigned_to', $this->userId))->select('id');

        $payments = Payment::whereIn('invoice_id', $invoiceIds)
            ->whereBetween('payment_date', [$this->from->toDateString(), $this->to->toDateString()])->get();
        $byMonth = $payments->groupBy(fn ($p) => $p->payment_date->format('m/Y'));
        $invoiced = Invoice::visibleTo($this->viewer)->where('status', 'issued')
            ->when($this->userId, fn ($q) => $q->where('assigned_to', $this->userId))
            ->whereBetween('issue_date', [$this->from->toDateString(), $this->to->toDateString()]);

        return [
            'definition' => 'Doanh thu = tiền khách ĐÃ THANH TOÁN (ghi nhận theo ngày thanh toán). "Giá trị hóa đơn" = tổng hóa đơn đã phát hành có ngày lập trong kỳ.',
            'statuses' => [],
            'headings' => ['Tháng', 'Số giao dịch', 'Tiền mặt', 'Chuyển khoản', 'Thẻ / khác', 'Tổng doanh thu'],
            'money_cols' => [2, 3, 4, 5],
            'rows' => $byMonth->map(fn ($g, $m) => [$m, $g->count(), (float) $g->where('method', 'cash')->sum('amount'),
                (float) $g->where('method', 'bank_transfer')->sum('amount'), (float) $g->whereIn('method', ['card', 'other'])->sum('amount'), (float) $g->sum('amount')])->values()->all(),
            'summary' => [
                ['Doanh thu thực thu', money($payments->sum('amount')), 'bi-cash-stack'],
                ['Giá trị hóa đơn phát hành', money((clone $invoiced)->sum('total')), 'bi-receipt'],
                ['Số hóa đơn', (clone $invoiced)->count(), 'bi-files'],
                ['Công nợ hiện tại', money(Invoice::visibleTo($this->viewer)->where('status', 'issued')->when($this->userId, fn ($q) => $q->where('assigned_to', $this->userId))->get()->sum('balance')), 'bi-wallet2'],
            ],
            'chart' => ['type' => 'line', 'money' => true, 'label' => 'Doanh thu theo tháng', 'labels' => $byMonth->keys()->all(), 'values' => $byMonth->map(fn ($g) => (float) $g->sum('amount'))->values()->all()],
        ];
    }

    private function leads(): array
    {
        $leads = $this->scope(Lead::visibleTo($this->viewer), 'created_at')->get(['id', 'lead_source_id', 'status', 'estimated_value']);
        $sources = LeadSource::pluck('name', 'id');
        $grouped = $leads->groupBy('lead_source_id');

        $rows = $grouped->map(function ($g, $sourceId) use ($sources) {
            $total = $g->count();
            $converted = $g->where('status', 'converted')->count();

            return [$sources[$sourceId] ?? 'Không rõ', $total, $g->whereIn('status', ['new', 'contacted', 'qualified', 'proposal'])->count(),
                $converted, $g->where('status', 'lost')->count(), $total ? round($converted / $total * 100, 1).'%' : '0%', (float) $g->sum('estimated_value')];
        })->sortByDesc(1)->values();

        $total = $leads->count();
        $converted = $leads->where('status', 'converted')->count();

        return [
            'definition' => 'Lead tạo trong kỳ, nhóm theo nguồn. Tỷ lệ chuyển đổi = lead đã chuyển đổi / tổng lead của nguồn.',
            'statuses' => Lead::STATUSES,
            'headings' => ['Nguồn', 'Tổng lead', 'Đang xử lý', 'Đã chuyển đổi', 'Thất bại', 'Tỷ lệ chuyển đổi', 'Giá trị ước tính'],
            'money_cols' => [6],
            'rows' => $rows->all(),
            'summary' => [
                ['Tổng lead', $total, 'bi-funnel'],
                ['Đã chuyển đổi', $converted, 'bi-arrow-repeat'],
                ['Tỷ lệ chuyển đổi', ($total ? round($converted / $total * 100, 1) : 0).'%', 'bi-percent'],
                ['Thất bại', $leads->where('status', 'lost')->count(), 'bi-x-circle'],
            ],
            'chart' => ['type' => 'bar', 'label' => 'Số lead theo nguồn', 'labels' => $rows->pluck(0)->all(), 'values' => $rows->pluck(1)->all()],
        ];
    }

    private function employees(): array
    {
        $canAll = $this->viewer->can('invoices.view_all') && $this->viewer->can('leads.view_all');
        $users = User::active()->when(! $canAll, fn ($q) => $q->whereKey($this->viewer->id))
            ->when($this->userId, fn ($q) => $q->whereKey($this->userId))->orderBy('name')->get();
        [$f, $t] = [$this->from, $this->to];

        $rows = $users->map(function (User $u) use ($f, $t) {
            $leads = Lead::where('assigned_to', $u->id)->whereBetween('created_at', [$f, $t]);
            $won = Opportunity::where('assigned_to', $u->id)->whereBetween('closed_at', [$f, $t])->whereHas('stage', fn ($q) => $q->where('is_won', true));
            $revenue = Payment::join('invoices', 'invoices.id', '=', 'payments.invoice_id')->where('invoices.assigned_to', $u->id)
                ->where('invoices.status', 'issued')->whereNull('invoices.deleted_at')
                ->whereBetween('payments.payment_date', [$f->toDateString(), $t->toDateString()])->sum('payments.amount');
            $tasksDone = Task::where('assigned_to', $u->id)->where('status', 'completed')->whereBetween('completed_at', [$f, $t])->count();

            return [$u->name, (clone $leads)->count(), (clone $leads)->where('status', 'converted')->count(), (clone $won)->count(),
                (float) (clone $won)->sum('value'), (float) $revenue, $tasksDone];
        })->filter(fn ($r) => array_sum(array_slice($r, 1)) > 0)->sortByDesc(5)->values();

        return [
            'definition' => 'Theo nhân viên phụ trách: lead được giao tạo trong kỳ, cơ hội THẮNG có ngày đóng trong kỳ, doanh thu thực thu từ hóa đơn họ phụ trách, công việc hoàn thành trong kỳ.',
            'statuses' => [],
            'headings' => ['Nhân viên', 'Lead được giao', 'Lead chuyển đổi', 'Cơ hội thắng', 'Giá trị thắng', 'Doanh thu thực thu', 'Việc hoàn thành'],
            'money_cols' => [4, 5],
            'rows' => $rows->all(),
            'summary' => [
                ['Nhân viên có hoạt động', $rows->count(), 'bi-people'],
                ['Tổng doanh thu', money($rows->sum(5)), 'bi-cash-stack'],
                ['Cơ hội thắng', $rows->sum(3), 'bi-trophy'],
                ['Việc hoàn thành', $rows->sum(6), 'bi-check2-all'],
            ],
            'chart' => ['type' => 'bar', 'money' => true, 'label' => 'Doanh thu theo nhân viên', 'labels' => $rows->pluck(0)->all(), 'values' => $rows->pluck(5)->all(), 'horizontal' => true],
        ];
    }

    private function campaigns(): array
    {
        $items = Campaign::visibleTo($this->viewer)
            ->where(fn ($q) => $q->whereBetween('start_date', [$this->from->toDateString(), $this->to->toDateString()])
                ->orWhereBetween('end_date', [$this->from->toDateString(), $this->to->toDateString()])
                ->orWhere(fn ($w) => $w->where('start_date', '<=', $this->from)->where('end_date', '>=', $this->to)))
            ->when($this->userId, fn ($q) => $q->where('owner_id', $this->userId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->withCount(['leads', 'leads as converted_leads_count' => fn ($q) => $q->where('status', 'converted')])
            ->orderBy('start_date')->get();

        return [
            'definition' => 'Chiến dịch diễn ra trong kỳ. Lead thực tế = số lead gắn với chiến dịch. Chi phí/lead = ngân sách / lead thực tế.',
            'statuses' => Campaign::STATUSES,
            'headings' => ['Chiến dịch', 'Loại', 'Thời gian', 'Ngân sách', 'Lead mục tiêu', 'Lead thực tế', 'Chuyển đổi', 'Tỷ lệ', 'Chi phí/lead'],
            'money_cols' => [3, 8],
            'rows' => $items->map(fn ($c) => [$c->name, Campaign::TYPES[$c->type] ?? '', vn_date($c->start_date).' - '.vn_date($c->end_date), (float) $c->budget,
                $c->target_leads, $c->actual_leads, $c->converted_leads, $c->conversion_rate.'%', $c->cost_per_lead])->all(),
            'summary' => [
                ['Số chiến dịch', $items->count(), 'bi-megaphone'],
                ['Tổng ngân sách', money($items->sum('budget')), 'bi-wallet2'],
                ['Lead thu được', $items->sum('leads_count'), 'bi-funnel'],
                ['Lead chuyển đổi', $items->sum('converted_leads_count'), 'bi-arrow-repeat'],
            ],
            'chart' => ['type' => 'bar', 'label' => 'Lead thực tế theo chiến dịch', 'labels' => $items->pluck('name')->all(), 'values' => $items->pluck('leads_count')->all()],
        ];
    }

    private function tasks(): array
    {
        $tasks = $this->scope(Task::visibleTo($this->viewer), 'due_date')->with('assignee:id,name')->get();
        $rows = $tasks->groupBy(fn ($t) => $t->assignee?->name ?? 'Chưa giao')->map(function ($g, $name) {
            $done = $g->where('status', 'completed');
            $onTime = $done->filter(fn ($t) => ! $t->due_date || ($t->completed_at && $t->completed_at->lte($t->due_date)))->count();

            return [$name, $g->count(), $done->count(), $g->filter->is_late->count(), $g->where('status', 'cancelled')->count(),
                $g->count() ? round($done->count() / $g->count() * 100, 1).'%' : '0%', $done->count() ? round($onTime / $done->count() * 100, 1).'%' : '—'];
        })->sortByDesc(1)->values();

        return [
            'definition' => 'Công việc có hạn chót trong kỳ. Tỷ lệ hoàn thành = hoàn thành / tổng. Đúng hạn = hoàn thành trước hoặc bằng hạn chót.',
            'statuses' => Task::STATUSES,
            'headings' => ['Nhân viên', 'Tổng việc', 'Hoàn thành', 'Đang trễ hạn', 'Đã hủy', 'Tỷ lệ hoàn thành', 'Tỷ lệ đúng hạn'],
            'money_cols' => [],
            'rows' => $rows->all(),
            'summary' => [
                ['Tổng công việc', $tasks->count(), 'bi-list-check'],
                ['Hoàn thành', $tasks->where('status', 'completed')->count(), 'bi-check2-circle'],
                ['Đang trễ hạn', $tasks->filter->is_late->count(), 'bi-alarm'],
                ['Tỷ lệ hoàn thành', ($tasks->count() ? round($tasks->where('status', 'completed')->count() / $tasks->count() * 100, 1) : 0).'%', 'bi-percent'],
            ],
            'chart' => ['type' => 'bar', 'label' => 'Việc hoàn thành theo nhân viên', 'labels' => $rows->pluck(0)->all(), 'values' => $rows->pluck(2)->all()],
        ];
    }

    private function tickets(): array
    {
        $tickets = $this->scope(SupportTicket::visibleTo($this->viewer), 'created_at')->with('assignee:id,name')->get();
        $rows = $tickets->groupBy(fn ($t) => $t->assignee?->name ?? 'Chưa giao')->map(function ($g, $name) {
            $resolved = $g->whereNotNull('resolved_at');
            $avg = $resolved->count() ? round($resolved->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at)) / 60, 1) : null;

            return [$name, $g->count(), $g->whereIn('status', ['open', 'in_progress', 'waiting'])->count(), $resolved->count(),
                $g->whereIn('priority', ['high', 'urgent'])->count(), $avg !== null ? $avg.' giờ' : '—'];
        })->sortByDesc(1)->values();
        $byStatus = collect(SupportTicket::STATUSES)->map(fn ($s, $k) => $tickets->where('status', $k)->count());

        return [
            'definition' => 'Ticket tạo trong kỳ. Thời gian xử lý trung bình = từ lúc tạo đến lúc giải quyết.',
            'statuses' => SupportTicket::STATUSES,
            'headings' => ['Nhân viên', 'Tổng ticket', 'Đang mở', 'Đã giải quyết', 'Ưu tiên cao/khẩn', 'Thời gian xử lý TB'],
            'money_cols' => [],
            'rows' => $rows->all(),
            'summary' => [
                ['Tổng ticket', $tickets->count(), 'bi-headset'],
                ['Đang mở', $tickets->whereIn('status', ['open', 'in_progress', 'waiting'])->count(), 'bi-hourglass'],
                ['Đã giải quyết', $tickets->whereNotNull('resolved_at')->count(), 'bi-check2-circle'],
                ['Khẩn cấp', $tickets->where('priority', 'urgent')->count(), 'bi-exclamation-octagon'],
            ],
            'chart' => ['type' => 'doughnut', 'label' => 'Ticket theo trạng thái', 'labels' => array_map(fn ($s) => $s[0], array_values(SupportTicket::STATUSES)), 'values' => $byStatus->values()->all()],
        ];
    }
}
