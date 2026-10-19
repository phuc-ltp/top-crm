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
        // Phase 3
        'products' => ['Bán hàng theo sản phẩm', 'bi-box-seam'],
        'inventory' => ['Nhập – xuất – tồn kho', 'bi-boxes'],
        'receivables' => ['Công nợ phải thu (tuổi nợ)', 'bi-wallet2'],
    ];

    /** Quyền module cần thêm (ngoài reports.view) cho từng báo cáo. */
    public static function permissionFor(string $type): ?string
    {
        return ['products' => 'invoices.view', 'inventory' => 'inventory.view', 'receivables' => 'invoices.view'][$type] ?? null;
    }

    /** Báo cáo người dùng được xem. */
    public static function allowedTypes(User $user): array
    {
        return array_filter(self::TYPES, fn ($k) => ($p = self::permissionFor($k)) === null || $user->can($p), ARRAY_FILTER_USE_KEY);
    }

    public function __construct(private User $viewer, private Carbon $from, private Carbon $to, private ?int $userId = null, private ?string $status = null) {}

    public function build(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        abort_unless(isset(self::allowedTypes($this->viewer)[$type]), 403);

        return ['title' => __(self::TYPES[$type][0]), 'type' => $type] + $this->{$type}();
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

        $bySource = $items->groupBy(fn ($c) => $c->source?->label ?? __('Không rõ'))->map->count()->sortDesc();

        return [
            'definition' => __('Khách hàng được tạo trong kỳ. "Đã thanh toán" = tổng tiền khách đã trả trên các hóa đơn đã phát hành (mọi thời điểm).'),
            'statuses' => Customer::STATUSES,
            'headings' => [__('Mã'), __('Khách hàng'), __('Loại'), __('Nguồn'), __('Trạng thái'), __('Phụ trách'), __('Ngày tạo'), __('Đã thanh toán')],
            'money_cols' => [7],
            'rows' => $items->map(fn ($c) => [$c->code, $c->name, __(Customer::TYPES[$c->type] ?? ''), $c->source?->label, label_of(Customer::STATUSES, $c->status),
                $c->assignee?->name, vn_date($c->created_at), (float) $c->paid_total])->all(),
            'summary' => [
                [__('Khách hàng mới'), $items->count(), 'bi-people'],
                [__('Doanh nghiệp'), $items->where('type', 'business')->count(), 'bi-building'],
                [__('Cá nhân'), $items->where('type', 'individual')->count(), 'bi-person'],
                ['VIP', $items->where('status', 'vip')->count(), 'bi-star'],
            ],
            'chart' => ['type' => 'bar', 'label' => __('Khách hàng mới theo nguồn'), 'labels' => $bySource->keys()->all(), 'values' => $bySource->values()->all()],
        ];
    }

    private function sales(): array
    {
        $items = $this->scope(SalesOrder::visibleTo($this->viewer), 'order_date')->with(['customer:id,name', 'assignee:id,name'])->orderBy('order_date')->get();
        $byStatus = collect(SalesOrder::STATUSES)->map(fn ($s, $k) => $items->where('status', $k)->sum('total'));

        return [
            'definition' => __('Đơn hàng có ngày đặt trong kỳ. Giá trị đơn = tổng cộng sau chiết khấu và thuế (do hệ thống tính).'),
            'statuses' => SalesOrder::STATUSES,
            'headings' => [__('Số đơn'), __('Ngày đặt'), __('Khách hàng'), __('Trạng thái'), __('Phụ trách'), __('Tạm tính'), __('Thuế'), __('Tổng cộng')],
            'money_cols' => [5, 6, 7],
            'rows' => $items->map(fn ($o) => [$o->number, vn_date($o->order_date), $o->customer?->name, label_of(SalesOrder::STATUSES, $o->status),
                $o->assignee?->name, (float) $o->subtotal, (float) $o->tax_amount, (float) $o->total])->all(),
            'summary' => [
                [__('Số đơn hàng'), $items->count(), 'bi-bag'],
                [__('Tổng giá trị'), money($items->where('status', '!=', 'cancelled')->sum('total')), 'bi-cash'],
                [__('Đơn hoàn thành'), $items->where('status', 'completed')->count(), 'bi-check2-circle'],
                [__('Đơn bị hủy'), $items->where('status', 'cancelled')->count(), 'bi-x-circle'],
            ],
            'chart' => ['type' => 'bar', 'money' => true, 'label' => __('Giá trị đơn theo trạng thái'),
                'labels' => array_map(fn ($s) => __($s[0]), array_values(SalesOrder::STATUSES)), 'values' => $byStatus->values()->map(fn ($v) => (float) $v)->all()],
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
            'definition' => __('Doanh thu = tiền khách ĐÃ THANH TOÁN (ghi nhận theo ngày thanh toán). "Giá trị hóa đơn" = tổng hóa đơn đã phát hành có ngày lập trong kỳ.'),
            'statuses' => [],
            'headings' => [__('Tháng'), __('Số giao dịch'), __('Tiền mặt'), __('Chuyển khoản'), __('Thẻ / khác'), __('Tổng doanh thu')],
            'money_cols' => [2, 3, 4, 5],
            'rows' => $byMonth->map(fn ($g, $m) => [$m, $g->count(), (float) $g->where('method', 'cash')->sum('amount'),
                (float) $g->where('method', 'bank_transfer')->sum('amount'), (float) $g->whereIn('method', ['card', 'other'])->sum('amount'), (float) $g->sum('amount')])->values()->all(),
            'summary' => [
                [__('Doanh thu thực thu'), money($payments->sum('amount')), 'bi-cash-stack'],
                [__('Giá trị hóa đơn phát hành'), money((clone $invoiced)->sum('total')), 'bi-receipt'],
                [__('Số hóa đơn'), (clone $invoiced)->count(), 'bi-files'],
                [__('Công nợ hiện tại'), money(Invoice::visibleTo($this->viewer)->where('status', 'issued')->when($this->userId, fn ($q) => $q->where('assigned_to', $this->userId))->get()->sum('balance')), 'bi-wallet2'],
            ],
            'chart' => ['type' => 'line', 'money' => true, 'label' => __('Doanh thu theo tháng'), 'labels' => $byMonth->keys()->all(), 'values' => $byMonth->map(fn ($g) => (float) $g->sum('amount'))->values()->all()],
        ];
    }

    /** Bán hàng theo sản phẩm: dòng hóa đơn đã phát hành có ngày lập trong kỳ. Lãi gộp chỉ cho người sửa sản phẩm. */
    private function products(): array
    {
        $invoiceIds = Invoice::visibleTo($this->viewer)->where('status', 'issued')
            ->when($this->userId, fn ($q) => $q->where('assigned_to', $this->userId))
            ->whereBetween('issue_date', [$this->from->toDateString(), $this->to->toDateString()])->select('id');
        $lines = \App\Models\InvoiceItem::whereIn('invoice_id', $invoiceIds)->whereNotNull('product_id')
            ->selectRaw('product_id, SUM(quantity) as qty, SUM(line_total) as revenue, COUNT(DISTINCT invoice_id) as invoices')
            ->groupBy('product_id')->orderByDesc('revenue')->get();
        $products = \App\Models\Product::withTrashed()->whereIn('id', $lines->pluck('product_id'))->get()->keyBy('id');
        $cost = $this->viewer->can('products.update');

        $rows = $lines->map(function ($l) use ($products, $cost) {
            $p = $products[$l->product_id] ?? null;
            $unitCost = (float) ($p?->average_cost ?? $p?->purchase_price ?? 0);
            $row = [$p?->sku, $p?->display_name ?? '—', (float) $l->qty, (int) $l->invoices, (float) $l->revenue];
            if ($cost) {
                $row[] = round((float) $l->qty * $unitCost, 2);
                $row[] = round((float) $l->revenue - (float) $l->qty * $unitCost, 2);
            }

            return $row;
        })->values();

        return [
            'definition' => __('Theo dòng hóa đơn đã phát hành có ngày lập trong kỳ (trước thuế, sau chiết khấu dòng). Giá vốn = giá vốn bình quân hiện tại (hoặc giá vốn nhập tay); lãi gộp = doanh số − SL × giá vốn.'),
            'statuses' => [],
            'headings' => array_merge([__('SKU'), __('Sản phẩm'), __('SL bán'), __('Số hóa đơn'), __('Doanh số')], $cost ? [__('Giá vốn'), __('Lãi gộp')] : []),
            'money_cols' => $cost ? [4, 5, 6] : [4],
            'rows' => $rows->all(),
            'summary' => array_values(array_filter([
                [__('Doanh số'), money($rows->sum(4)), 'bi-cash-stack'],
                [__('Số sản phẩm bán ra'), $rows->count(), 'bi-box-seam'],
                $cost ? [__('Lãi gộp'), money($rows->sum(6)), 'bi-graph-up'] : null,
            ])),
            'chart' => ['type' => 'bar', 'money' => true, 'horizontal' => true, 'label' => __('Top sản phẩm theo doanh số'),
                'labels' => $rows->take(10)->pluck(1)->all(), 'values' => $rows->take(10)->pluck(4)->all()],
        ];
    }

    /** Nhập – xuất – tồn theo sản phẩm trong kỳ (từ sổ kho). Giá trị tồn cuối chỉ cho inventory.manage. */
    private function inventory(): array
    {
        $from = $this->from->copy()->startOfDay();
        $to = $this->to->copy()->endOfDay();
        $mv = fn () => \App\Models\StockMovement::query();
        $opening = $mv()->where('created_at', '<', $from)->selectRaw('product_id, SUM(quantity) as q')->groupBy('product_id')->pluck('q', 'product_id');
        $in = $mv()->whereBetween('created_at', [$from, $to])->where('quantity', '>', 0)->whereNotIn('type', ['transfer_in'])
            ->selectRaw('product_id, SUM(quantity) as q')->groupBy('product_id')->pluck('q', 'product_id');
        $out = $mv()->whereBetween('created_at', [$from, $to])->where('quantity', '<', 0)->whereNotIn('type', ['transfer_out'])
            ->selectRaw('product_id, SUM(quantity) as q')->groupBy('product_id')->pluck('q', 'product_id');
        $ids = collect([$opening, $in, $out])->flatMap(fn ($c) => $c->keys())->unique();
        $products = \App\Models\Product::withTrashed()->whereIn('id', $ids)->orderBy('name')->get();
        $value = $this->viewer->can('inventory.manage');

        $rows = $products->map(function ($p) use ($opening, $in, $out, $value) {
            $o = (float) ($opening[$p->id] ?? 0);
            $i = (float) ($in[$p->id] ?? 0);
            $x = abs((float) ($out[$p->id] ?? 0));
            $row = [$p->sku, $p->display_name, $p->unit, $o, $i, $x, round($o + $i - $x, 3)];
            if ($value) {
                $row[] = round(($o + $i - $x) * (float) ($p->average_cost ?? 0), 2);
            }

            return $row;
        })->values();

        return [
            'definition' => __('Từ sổ kho, mọi kho cộng lại. Nhập = phát sinh tăng (nhập, hàng trả lại, điều chỉnh tăng); xuất = phát sinh giảm (xuất, điều chỉnh giảm); chuyển kho nội bộ không tính. Tồn cuối = tồn đầu + nhập − xuất.'),
            'statuses' => [],
            'headings' => array_merge([__('SKU'), __('Sản phẩm'), __('ĐVT'), __('Tồn đầu kỳ'), __('Nhập trong kỳ'), __('Xuất trong kỳ'), __('Tồn cuối kỳ')], $value ? [__('Giá trị tồn cuối')] : []),
            'money_cols' => $value ? [7] : [],
            'rows' => $rows->all(),
            'summary' => array_values(array_filter([
                [__('Số sản phẩm có phát sinh'), $rows->count(), 'bi-boxes'],
                [__('Tổng nhập trong kỳ'), \App\Services\InventoryService::qty($rows->sum(4)), 'bi-box-arrow-in-down'],
                [__('Tổng xuất trong kỳ'), \App\Services\InventoryService::qty($rows->sum(5)), 'bi-box-arrow-up'],
                $value ? [__('Giá trị tồn cuối'), money($rows->sum(7)), 'bi-cash-stack'] : null,
            ])),
            'chart' => ['type' => 'bar', 'label' => __('Nhập / xuất theo sản phẩm (SL xuất)'), 'labels' => $rows->take(10)->pluck(1)->all(), 'values' => $rows->take(10)->pluck(5)->all()],
        ];
    }

    /** Công nợ phải thu theo tuổi nợ (tại thời điểm xem, không phụ thuộc kỳ). */
    private function receivables(): array
    {
        $buckets = ['current' => __('Chưa đến hạn'), 'd30' => __('Quá hạn 1–30 ngày'), 'd60' => __('Quá hạn 31–60 ngày'), 'd90' => __('Quá hạn 61–90 ngày'), 'over' => __('Quá hạn trên 90 ngày')];
        $invoices = Invoice::visibleTo($this->viewer)->with('customer:id,name')->where('status', 'issued')->where('payment_status', '!=', 'paid')
            ->when($this->userId, fn ($q) => $q->where('assigned_to', $this->userId))->orderBy('due_date')->get()
            ->filter(fn ($i) => $i->balance > 0);
        $bucketOf = function ($inv) {
            $days = $inv->due_date ? (int) $inv->due_date->diffInDays(today(), false) : 0;

            return match (true) { $days <= 0 => 'current', $days <= 30 => 'd30', $days <= 60 => 'd60', $days <= 90 => 'd90', default => 'over' };
        };
        $rows = $invoices->map(function ($inv) use ($bucketOf, $buckets) {
            $days = $inv->due_date ? max(0, (int) $inv->due_date->diffInDays(today(), false)) : 0;

            return [$inv->number, $inv->customer?->name, vn_date($inv->issue_date), $inv->due_date ? vn_date($inv->due_date) : '—', $days, $buckets[$bucketOf($inv)], (float) $inv->total, $inv->balance];
        })->values();
        $byBucket = collect($buckets)->map(fn ($label, $key) => $invoices->filter(fn ($i) => $bucketOf($i) === $key)->sum('balance'));
        $byBucket = $byBucket->map(fn ($v) => (float) $v);

        return [
            'definition' => __('Hóa đơn đã phát hành còn phải thu (tổng − giảm trừ − đã thu) tại thời điểm xem; tuổi nợ tính từ hạn thanh toán. Không phụ thuộc khoảng ngày.'),
            'statuses' => [],
            'headings' => [__('Số hóa đơn'), __('Khách hàng'), __('Ngày lập'), __('Hạn thanh toán'), __('Số ngày quá hạn'), __('Nhóm tuổi nợ'), __('Tổng hóa đơn'), __('Còn phải thu')],
            'money_cols' => [6, 7],
            'rows' => $rows->all(),
            'summary' => [
                [__('Tổng công nợ'), money($byBucket->sum()), 'bi-wallet2'],
                [__('Chưa đến hạn'), money($byBucket['current']), 'bi-calendar-check'],
                [__('Đã quá hạn'), money($byBucket->except('current')->sum()), 'bi-exclamation-triangle'],
                [__('Số hóa đơn còn nợ'), $rows->count(), 'bi-receipt'],
            ],
            'chart' => ['type' => 'bar', 'money' => true, 'label' => __('Công nợ theo tuổi nợ'), 'labels' => array_values($buckets), 'values' => $byBucket->values()->all()],
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

            return [isset($sources[$sourceId]) ? __($sources[$sourceId]) : __('Không rõ'), $total, $g->whereIn('status', Lead::openStatuses())->count(),
                $converted, $g->where('status', 'lost')->count(), $total ? round($converted / $total * 100, 1).'%' : '0%', (float) $g->sum('estimated_value')];
        })->sortByDesc(1)->values();

        $total = $leads->count();
        $converted = $leads->where('status', 'converted')->count();

        return [
            'definition' => __('Lead tạo trong kỳ, nhóm theo nguồn. Tỷ lệ chuyển đổi = lead đã chuyển đổi / tổng lead của nguồn.'),
            'statuses' => Lead::statuses(),
            'headings' => [__('Nguồn'), __('Tổng lead'), __('Đang xử lý'), __('Đã chuyển đổi'), __('Thất bại'), __('Tỷ lệ chuyển đổi'), __('Giá trị ước tính')],
            'money_cols' => [6],
            'rows' => $rows->all(),
            'summary' => [
                [__('Tổng lead'), $total, 'bi-funnel'],
                [__('Đã chuyển đổi'), $converted, 'bi-arrow-repeat'],
                [__('Tỷ lệ chuyển đổi'), ($total ? round($converted / $total * 100, 1) : 0).'%', 'bi-percent'],
                [__('Thất bại'), $leads->where('status', 'lost')->count(), 'bi-x-circle'],
            ],
            'chart' => ['type' => 'bar', 'label' => __('Số lead theo nguồn'), 'labels' => $rows->pluck(0)->all(), 'values' => $rows->pluck(1)->all()],
        ];
    }

    private function employees(): array
    {
        $canAll = $this->viewer->can('invoices.view_all') && $this->viewer->can('leads.view_all');
        $users = User::inOrganization()->active()->when(! $canAll, fn ($q) => $q->whereKey($this->viewer->id))
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
            'definition' => __('Theo nhân viên phụ trách: lead được giao tạo trong kỳ, cơ hội THẮNG có ngày đóng trong kỳ, doanh thu thực thu từ hóa đơn họ phụ trách, công việc hoàn thành trong kỳ.'),
            'statuses' => [],
            'headings' => [__('Nhân viên'), __('Lead được giao'), __('Lead chuyển đổi'), __('Cơ hội thắng'), __('Giá trị thắng'), __('Doanh thu thực thu'), __('Việc hoàn thành')],
            'money_cols' => [4, 5],
            'rows' => $rows->all(),
            'summary' => [
                [__('Nhân viên có hoạt động'), $rows->count(), 'bi-people'],
                [__('Tổng doanh thu'), money($rows->sum(5)), 'bi-cash-stack'],
                [__('Cơ hội thắng'), $rows->sum(3), 'bi-trophy'],
                [__('Việc hoàn thành'), $rows->sum(6), 'bi-check2-all'],
            ],
            'chart' => ['type' => 'bar', 'money' => true, 'label' => __('Doanh thu theo nhân viên'), 'labels' => $rows->pluck(0)->all(), 'values' => $rows->pluck(5)->all(), 'horizontal' => true],
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
            'definition' => __('Chiến dịch diễn ra trong kỳ. Lead thực tế = số lead gắn với chiến dịch. Chi phí/lead = ngân sách / lead thực tế.'),
            'statuses' => Campaign::STATUSES,
            'headings' => [__('Chiến dịch'), __('Loại'), __('Thời gian'), __('Ngân sách'), __('Lead mục tiêu'), __('Lead thực tế'), __('Chuyển đổi'), __('Tỷ lệ'), __('Chi phí/lead')],
            'money_cols' => [3, 8],
            'rows' => $items->map(fn ($c) => [$c->name, __(Campaign::TYPES[$c->type] ?? ''), vn_date($c->start_date).' - '.vn_date($c->end_date), (float) $c->budget,
                $c->target_leads, $c->actual_leads, $c->converted_leads, $c->conversion_rate.'%', $c->cost_per_lead])->all(),
            'summary' => [
                [__('Số chiến dịch'), $items->count(), 'bi-megaphone'],
                [__('Tổng ngân sách'), money($items->sum('budget')), 'bi-wallet2'],
                [__('Lead thu được'), $items->sum('leads_count'), 'bi-funnel'],
                [__('Lead chuyển đổi'), $items->sum('converted_leads_count'), 'bi-arrow-repeat'],
            ],
            'chart' => ['type' => 'bar', 'label' => __('Lead thực tế theo chiến dịch'), 'labels' => $items->pluck('name')->all(), 'values' => $items->pluck('leads_count')->all()],
        ];
    }

    private function tasks(): array
    {
        $tasks = $this->scope(Task::visibleTo($this->viewer), 'due_date')->with('assignee:id,name')->get();
        $rows = $tasks->groupBy(fn ($t) => $t->assignee?->name ?? __('Chưa giao'))->map(function ($g, $name) {
            $done = $g->where('status', 'completed');
            $onTime = $done->filter(fn ($t) => ! $t->due_date || ($t->completed_at && $t->completed_at->lte($t->due_date)))->count();

            return [$name, $g->count(), $done->count(), $g->filter->is_late->count(), $g->where('status', 'cancelled')->count(),
                $g->count() ? round($done->count() / $g->count() * 100, 1).'%' : '0%', $done->count() ? round($onTime / $done->count() * 100, 1).'%' : '—'];
        })->sortByDesc(1)->values();

        return [
            'definition' => __('Công việc có hạn chót trong kỳ. Tỷ lệ hoàn thành = hoàn thành / tổng. Đúng hạn = hoàn thành trước hoặc bằng hạn chót.'),
            'statuses' => Task::STATUSES,
            'headings' => [__('Nhân viên'), __('Tổng việc'), __('Hoàn thành'), __('Đang trễ hạn'), __('Đã hủy'), __('Tỷ lệ hoàn thành'), __('Tỷ lệ đúng hạn')],
            'money_cols' => [],
            'rows' => $rows->all(),
            'summary' => [
                [__('Tổng công việc'), $tasks->count(), 'bi-list-check'],
                [__('Hoàn thành'), $tasks->where('status', 'completed')->count(), 'bi-check2-circle'],
                [__('Đang trễ hạn'), $tasks->filter->is_late->count(), 'bi-alarm'],
                [__('Tỷ lệ hoàn thành'), ($tasks->count() ? round($tasks->where('status', 'completed')->count() / $tasks->count() * 100, 1) : 0).'%', 'bi-percent'],
            ],
            'chart' => ['type' => 'bar', 'label' => __('Việc hoàn thành theo nhân viên'), 'labels' => $rows->pluck(0)->all(), 'values' => $rows->pluck(2)->all()],
        ];
    }

    private function tickets(): array
    {
        $tickets = $this->scope(SupportTicket::visibleTo($this->viewer), 'created_at')->with('assignee:id,name')->get();
        $rows = $tickets->groupBy(fn ($t) => $t->assignee?->name ?? __('Chưa giao'))->map(function ($g, $name) {
            $resolved = $g->whereNotNull('resolved_at');
            $avg = $resolved->count() ? round($resolved->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at)) / 60, 1) : null;

            return [$name, $g->count(), $g->whereIn('status', ['open', 'in_progress', 'waiting'])->count(), $resolved->count(),
                $g->whereIn('priority', ['high', 'urgent'])->count(), $avg !== null ? __(':h giờ', ['h' => $avg]) : '—'];
        })->sortByDesc(1)->values();
        $byStatus = collect(SupportTicket::STATUSES)->map(fn ($s, $k) => $tickets->where('status', $k)->count());

        return [
            'definition' => __('Ticket tạo trong kỳ. Thời gian xử lý trung bình = từ lúc tạo đến lúc giải quyết.'),
            'statuses' => SupportTicket::STATUSES,
            'headings' => [__('Nhân viên'), __('Tổng ticket'), __('Đang mở'), __('Đã giải quyết'), __('Ưu tiên cao/khẩn'), __('Thời gian xử lý TB')],
            'money_cols' => [],
            'rows' => $rows->all(),
            'summary' => [
                [__('Tổng ticket'), $tickets->count(), 'bi-headset'],
                [__('Đang mở'), $tickets->whereIn('status', ['open', 'in_progress', 'waiting'])->count(), 'bi-hourglass'],
                [__('Đã giải quyết'), $tickets->whereNotNull('resolved_at')->count(), 'bi-check2-circle'],
                [__('Khẩn cấp'), $tickets->where('priority', 'urgent')->count(), 'bi-exclamation-octagon'],
            ],
            'chart' => ['type' => 'doughnut', 'label' => __('Ticket theo trạng thái'), 'labels' => array_map(fn ($s) => __($s[0]), array_values(SupportTicket::STATUSES)), 'values' => $byStatus->values()->all()],
        ];
    }
}
