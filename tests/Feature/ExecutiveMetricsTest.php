<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\SalesOrder;
use App\Models\SalesTarget;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ExecutiveMetrics;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bước 2.7 — 20 chỉ số điều hành. Dữ liệu mẫu CỐ ĐỊNH, "hôm nay" = 15/06/2026 10:00:
 * kỳ "tháng này" = 01/06 – 30/06, kỳ trước cùng độ dài = 02/05 – 31/05.
 * Mỗi con số kỳ vọng được tính tay trong chú thích.
 */
class ExecutiveMetricsTest extends TestCase
{
    private User $sale1;

    private User $sale2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 6, 15, 10));
        $this->sale1 = $this->userWithRole('Sales Employee', ['email' => 's1@example.com']);
        $this->sale2 = $this->userWithRole('Sales Employee', ['email' => 's2@example.com']);
    }

    private function at($model, array $times)
    {
        $model->forceFill($times)->saveQuietly();

        return $model;
    }

    /** Khách hàng tạo từ tháng 4 (không ảnh hưởng chỉ số "khách hàng mới"). */
    private function oldCustomer(array $attrs = []): Customer
    {
        return $this->at($this->customer($attrs), ['created_at' => '2026-04-01 09:00:00']);
    }

    private function stage(string $slug): PipelineStage
    {
        return PipelineStage::where('pipeline_id', Pipeline::default()->id)->where('slug', $slug)->firstOrFail();
    }

    private function opp(string $stage, float $value, array $attrs = []): Opportunity
    {
        $o = Opportunity::create($attrs + ['title' => 'Cơ hội '.uniqid(), 'customer_id' => $this->oldCustomer()->id,
            'pipeline_stage_id' => $this->stage($stage)->id, 'value' => $value, 'probability' => $attrs['probability'] ?? 10]);

        return $this->at($o, ['created_at' => '2026-04-02 09:00:00'] + (isset($attrs['closed_at']) ? ['closed_at' => $attrs['closed_at']] : []));
    }

    private function seedSample(): void
    {
        // --- Khách hàng: 2 trong kỳ, 1 kỳ trước, 1 tháng 4 → mới 2, kỳ trước 1 → +100%
        $this->at($this->customer(), ['created_at' => '2026-06-03 09:00:00']);
        $this->at($this->customer(), ['created_at' => '2026-06-14 09:00:00']);
        $this->at($this->customer(), ['created_at' => '2026-05-10 09:00:00']);
        $this->oldCustomer();

        // --- Lead: 3 tạo trong kỳ (1 đã chuyển đổi), 1 tạo kỳ trước nhưng đủ điều kiện trong kỳ
        $lead = fn (array $t) => $this->at(Lead::create(['name' => 'Lead '.uniqid(), 'status' => 'new']), $t);
        $lead(['created_at' => '2026-06-02 09:00:00', 'qualified_at' => '2026-06-04 09:00:00', 'converted_at' => '2026-06-05 09:00:00']);
        $lead(['created_at' => '2026-06-10 09:00:00', 'qualified_at' => '2026-06-11 09:00:00']);
        $lead(['created_at' => '2026-06-12 09:00:00']);
        $lead(['created_at' => '2026-05-20 09:00:00', 'qualified_at' => '2026-06-01 09:00:00']);
        $lead(['created_at' => '2026-04-20 09:00:00', 'converted_at' => '2026-06-07 09:00:00']);   // chuyển đổi thẳng → tính là đủ điều kiện
        $lead(['created_at' => '2026-04-21 09:00:00', 'status' => 'qualified']);                   // đang ở giai đoạn đủ điều kiện (ngày cũ không rõ)

        // --- Giữ chân: kỳ trước A, B mua (C chỉ có đơn hủy); kỳ này A, C, D mua → 1/2 = 50%
        [$a, $b, $c, $d] = [$this->oldCustomer(), $this->oldCustomer(), $this->oldCustomer(), $this->oldCustomer()];
        $order = fn ($cust, $date, $status = 'completed') => SalesOrder::create(['customer_id' => $cust->id, 'order_date' => $date, 'status' => $status, 'total' => 1000]);
        $order($a, '2026-05-05');
        $order($a, '2026-06-06');
        $order($b, '2026-05-07');
        $order($c, '2026-05-09', 'cancelled');
        $order($c, '2026-06-08');
        $order($d, '2026-06-09');

        // --- Cơ hội
        $this->opp('new', 10000000, ['probability' => 50, 'expected_close_date' => '2026-06-20']);   // mở, trọng số 5tr trong kỳ
        $this->opp('proposal', 4000000, ['probability' => 25, 'expected_close_date' => '2026-07-05']);   // mở, ngoài kỳ
        $this->opp('won', 3000000, ['closed_at' => '2026-06-10 09:00:00']);
        $this->opp('won', 5000000, ['closed_at' => '2026-06-12 09:00:00']);
        $this->opp('lost', 2000000, ['closed_at' => '2026-06-03 09:00:00']);
        $this->opp('won', 9000000, ['closed_at' => '2026-05-20 09:00:00']);   // kỳ trước — không tính

        // --- Hóa đơn & thanh toán
        $inv = fn (array $a) => Invoice::create($a + ['customer_id' => $this->oldCustomer()->id, 'issue_date' => '2026-05-01', 'status' => 'issued']);
        $i1 = $inv(['total' => 10000000, 'amount_paid' => 6000000, 'payment_status' => 'partial', 'due_date' => '2026-06-30', 'assigned_to' => $this->sale1->id]);
        $i2 = $inv(['total' => 4000000, 'amount_paid' => 4000000, 'payment_status' => 'paid', 'due_date' => '2026-05-31', 'assigned_to' => $this->sale2->id]);
        $i3 = $inv(['total' => 5000000, 'amount_paid' => 1000000, 'payment_status' => 'partial', 'due_date' => '2026-06-01']);   // quá hạn
        $inv(['total' => 7000000, 'amount_paid' => 0, 'payment_status' => 'unpaid', 'status' => 'draft']);   // nháp — không tính
        Payment::create(['invoice_id' => $i1->id, 'amount' => 6000000, 'payment_date' => '2026-06-05', 'method' => 'bank_transfer']);
        Payment::create(['invoice_id' => $i2->id, 'amount' => 4000000, 'payment_date' => '2026-05-15', 'method' => 'bank_transfer']);
        Payment::create(['invoice_id' => $i3->id, 'amount' => 1000000, 'payment_date' => '2026-06-10', 'method' => 'cash']);

        // --- Công việc
        $task = fn (string $due, string $status = 'pending', ?string $done = null) => Task::create(['title' => 'Việc '.uniqid(), 'priority' => 'low',
            'status' => $status, 'due_date' => $due, 'completed_at' => $done]);
        $task('2026-06-05 17:00:00', 'completed', '2026-06-04 10:00:00');   // đúng hạn
        $task('2026-06-06 17:00:00', 'completed', '2026-06-08 10:00:00');   // trễ
        $task('2026-06-10 17:00:00');                                        // quá hạn
        $task('2026-06-20 17:00:00');                                        // chưa đến hạn
        $task('2026-06-07 17:00:00', 'cancelled');                           // hủy — không tính
        $task('2026-05-01 17:00:00');                                        // quá hạn, ngoài kỳ

        // --- Ticket: 3 đang mở; 3 tạo trong kỳ
        $ticket = fn (string $status, string $created) => $this->at(SupportTicket::create(['customer_id' => $this->oldCustomer()->id, 'subject' => 'T '.uniqid(),
            'description' => 'x', 'priority' => 'low', 'status' => $status]), ['created_at' => $created]);
        $ticket('open', '2026-06-02 09:00:00');
        $ticket('in_progress', '2026-05-03 09:00:00');
        $ticket('resolved', '2026-06-04 09:00:00');
        $ticket('waiting', '2026-06-14 09:00:00');

        // --- Chỉ tiêu tháng 6: sale1 5tr (thu 6tr → đạt), sale2 3tr (thu 0 → chưa đạt)
        SalesTarget::create(['user_id' => $this->sale1->id, 'year' => 2026, 'month' => 6, 'target_amount' => 5000000]);
        SalesTarget::create(['user_id' => $this->sale2->id, 'year' => 2026, 'month' => 6, 'target_amount' => 3000000]);
    }

    /** @return array<string, array> chỉ số theo key */
    private function metrics(User $user, string $period = 'this_month'): array
    {
        [$from, $to] = DashboardService::range($period, null, null);

        return collect((new ExecutiveMetrics($user, $from, $to))->groups())->flatMap(fn ($g) => collect($g['metrics'])->keyBy('key'))->all();
    }

    public function test_all_twenty_metrics_are_computed_correctly(): void
    {
        $this->seedSample();
        $m = $this->metrics($this->admin());

        $this->assertCount(20, $m);
        $expect = [
            'customers_new' => 2, 'leads_new' => 3, 'leads_qualified' => 4, 'lead_conversion' => 33.3, 'retention' => 50.0,
            'opportunities_open' => 2, 'pipeline_value' => 14000000.0, 'forecast' => 13000000.0, 'won_lost' => 2, 'win_rate' => 66.7, 'avg_deal' => 4000000.0,
            'revenue' => 7000000.0, 'revenue_growth' => 75.0, 'invoices_unpaid' => 2, 'receivable' => 8000000.0,
            'tasks_overdue' => 2, 'tasks_on_time' => 25.0, 'tickets_open' => 3, 'target_progress' => 87.5, 'kpi_achievers' => 1,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $m[$key]['value'], "Chỉ số $key sai");
            $this->assertNotEmpty($m[$key]['formula'], "Chỉ số $key thiếu công thức");
        }
        $this->assertSame('2 / 1', $m['won_lost']['display']);
        $this->assertSame('1 / 2', $m['kpi_achievers']['display']);
        $this->assertEquals(100.0, $m['customers_new']['change']);   // 2 so với 1
        $this->assertEquals(200.0, $m['leads_new']['change']);       // 3 so với 1
        $this->assertNull($m['leads_qualified']['change']);           // kỳ trước 0 → không chia cho 0
        $this->assertSame(__('Đang ở giai đoạn đủ điều kiện: :n', ['n' => 1]), $m['leads_qualified']['sub']);
        $this->assertEquals(75.0, $m['revenue']['change']);          // 7tr so với 4tr
        $this->assertStringContainsString('1', $m['invoices_unpaid']['sub']);   // 1 hóa đơn quá hạn
        $this->assertSame(__('Mới trong kỳ: :n', ['n' => 3]), $m['tickets_open']['sub']);
        $this->assertTrue($m['opportunities_open']['snapshot']);
    }

    public function test_staff_only_see_their_own_numbers(): void
    {
        $this->seedSample();
        $m = $this->metrics($this->sale1);

        $this->assertEquals(6000000.0, $m['revenue']['value']);           // chỉ hóa đơn của mình
        $this->assertEquals(4000000.0, $m['receivable']['value']);        // 10tr − 6tr
        $this->assertEquals(120.0, $m['target_progress']['value']);       // 6tr / chỉ tiêu riêng 5tr
        $this->assertSame('1 / 1', $m['kpi_achievers']['display']);       // không thấy chỉ tiêu của đồng nghiệp
        $this->assertSame(0, $m['opportunities_open']['value']);          // cơ hội không giao cho mình
        $this->assertNull($m['win_rate']['value']);

        $sale2 = $this->metrics($this->sale2);
        $this->assertEquals(0.0, $sale2['target_progress']['value']);
        $this->assertSame('0 / 1', $sale2['kpi_achievers']['display']);
    }

    public function test_groups_without_permission_are_hidden(): void
    {
        $this->seedSample();
        $marketing = $this->userWithRole('Marketing Employee');   // không xem cơ hội, hóa đơn, ticket
        $m = $this->metrics($marketing);

        foreach (['opportunities_open', 'pipeline_value', 'forecast', 'revenue', 'receivable', 'target_progress', 'kpi_achievers', 'tickets_open', 'retention'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $m, "$hidden không được hiện cho Marketing");
        }
        $this->assertArrayHasKey('leads_new', $m);
        $this->assertEquals(3, $m['leads_new']['value']);

        $this->actingAs($marketing)->get(route('dashboard'))->assertOk()
            ->assertDontSee('data-metric="revenue"', false)->assertDontSee('data-metric="pipeline_value"', false)->assertSee('data-metric="leads_new"', false);
    }

    public function test_empty_organization_shows_dashes_not_fake_numbers(): void
    {
        $admin = $this->admin();
        $m = $this->metrics($admin);

        foreach (['lead_conversion', 'retention', 'win_rate', 'avg_deal', 'revenue_growth', 'tasks_on_time', 'target_progress', 'kpi_achievers'] as $key) {
            $this->assertNull($m[$key]['value'], "$key phải là — khi chưa có dữ liệu");
            $this->assertNotEmpty($m[$key]['empty'], "$key cần lý do khi trống");
        }
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee(__('Chưa đặt chỉ tiêu cho tháng này'))->assertSee(__('Chưa có cơ hội đóng trong kỳ'));
    }

    public function test_dashboard_shows_metrics_formulas_and_demo_label(): void
    {
        $this->seedSample();
        $admin = $this->admin();
        $page = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $page->assertSee(__('Chỉ số điều hành'))->assertSee(__('Tỷ lệ thắng'))->assertSee('66,7%')
            ->assertSee(e(__('Cơ hội thắng ÷ (thắng + thất bại) trong kỳ × 100%. Cơ hội còn mở không được tính.')), false)
            ->assertDontSee('>DEMO<', false);

        Customer::query()->update(['is_demo' => true]);
        $this->get(route('dashboard'))->assertSee('>DEMO<', false);
    }

    public function test_period_filters(): void
    {
        $this->assertEquals(['2026-06-14 00:00:00', '2026-06-14 23:59:59'], array_map(fn ($d) => $d->format('Y-m-d H:i:s'), DashboardService::range('yesterday', null, null)));
        $this->assertEquals(['2026-06-09', '2026-06-15'], array_map(fn ($d) => $d->toDateString(), DashboardService::range('last_7', null, null)));
        $this->assertEquals(['2026-04-01', '2026-06-30'], array_map(fn ($d) => $d->toDateString(), DashboardService::range('this_quarter', null, null)));

        $admin = $this->admin();
        foreach (['today', 'yesterday', 'last_7', 'last_30', 'this_month', 'last_month', 'this_quarter', 'this_year'] as $p) {
            $this->actingAs($admin)->get(route('dashboard', ['period' => $p]))->assertOk();
        }
        $this->get(route('dashboard', ['period' => 'custom', 'from' => '2026-05-01', 'to' => '2026-05-31']))->assertOk()
            ->assertSee(__('Kỳ :from – :to · so với :pf – :pt', ['from' => '01/05/2026', 'to' => '31/05/2026', 'pf' => '31/03/2026', 'pt' => '30/04/2026']));
        $this->get(route('dashboard', ['period' => 'hack']))->assertSessionHasErrors('period');
        $this->get(route('dashboard', ['period' => 'custom', 'from' => '2026-05-10', 'to' => '2026-05-01']))->assertSessionHasErrors('to');
    }

    public function test_numbers_never_include_other_organizations(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = \App\Models\Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        app(\App\Support\Tenancy\RoleProvisioner::class)->provision($orgB->id);
        app(\App\Support\Tenancy\CurrentOrganization::class)->run($orgB->id, function () {
            $this->customer();
            Lead::create(['name' => 'Lead B', 'status' => 'new']);
            Task::create(['title' => 'Việc B', 'priority' => 'low', 'status' => 'pending', 'due_date' => '2026-06-01 10:00:00']);
        });

        $m = $this->metrics($owner);
        $this->assertSame(0, $m['customers_new']['value']);
        $this->assertSame(0, $m['leads_new']['value']);
        $this->assertSame(0, $m['tasks_overdue']['value']);
    }
}
