<?php

namespace Tests\Feature;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExecutiveMetrics;
use App\Services\SalesDocumentService;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Bước 3.5 — thanh toán, hoàn tiền, phiếu giảm trừ, đối soát, nhắc thanh toán, thuế suất cấu hình. */
class BillingTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->admin();
    }

    private function invoice(array $attrs = [], float $price = 10000000): Invoice
    {
        $this->actingAs($this->admin);

        return app(SalesDocumentService::class)->save(new Invoice, $attrs + ['customer_id' => $this->customer()->id, 'issue_date' => today(),
            'due_date' => today()->addDays(15), 'status' => 'issued', 'tax_rate' => 10, 'discount_amount' => 0, 'assigned_to' => $this->admin->id, 'created_by' => $this->admin->id],
            [['description' => 'Dịch vụ', 'quantity' => 1, 'unit_price' => $price]]);
    }

    private function pay(Invoice $inv, float $amount, string $route = 'payments.store', array $extra = [])
    {
        return $this->post(route($route, $inv), $extra + ['amount' => $amount, 'payment_date' => today()->toDateString(), 'method' => 'bank_transfer']);
    }

    public function test_payments_refunds_and_credit_notes_keep_balance_exact(): void
    {
        $inv = $this->invoice();   // tổng 11.000.000
        $this->pay($inv, 5000000)->assertSessionHasNoErrors();
        $this->assertSame(['partial', 6000000.0], [$inv->fresh()->payment_status, $inv->fresh()->balance]);

        $this->post(route('payments.credit', $inv), ['amount' => 1000000, 'issue_date' => today()->toDateString(), 'reason' => 'Giảm giá sau bán'])->assertSessionHasNoErrors();
        $this->assertSame(5000000.0, $inv->fresh()->balance);
        $this->assertSame('GT', substr(CreditNote::first()->number, 0, 2));

        $this->pay($inv, 5000001)->assertSessionHasErrors('amount');   // vượt số còn phải thu
        $this->pay($inv, 5000000)->assertSessionHasNoErrors();
        $this->assertSame(['paid', 0.0], [$inv->fresh()->payment_status, $inv->fresh()->balance]);

        $this->pay($inv, 10000001, 'payments.refund', ['notes' => 'x'])->assertSessionHasErrors('amount');   // vượt số đã thu
        $this->pay($inv, 2000000, 'payments.refund')->assertSessionHasErrors('notes');                    // phải có lý do
        $this->pay($inv, 2000000, 'payments.refund', ['notes' => 'Khách trả lại 1 gói'])->assertSessionHasNoErrors();
        $fresh = $inv->fresh();
        $this->assertSame(['8000000.00', '1000000.00', 'partial', 2000000.0], [$fresh->amount_paid, $fresh->credited_amount, $fresh->payment_status, $fresh->balance]);
        $this->assertSame('-2000000.00', Payment::where('type', 'refund')->value('amount'));

        $this->post(route('payments.credit', $inv), ['amount' => 10000001, 'issue_date' => today()->toDateString(), 'reason' => 'x'])->assertSessionHasErrors('amount');

        // Doanh thu đã thu trừ hoàn tiền; công nợ = tổng − đã thu − giảm trừ
        [$from, $to] = \App\Services\DashboardService::range('this_month', null, null);
        $m = collect((new ExecutiveMetrics($this->admin, $from, $to))->groups())->flatMap(fn ($g) => collect($g['metrics'])->keyBy('key'));
        $this->assertEquals(8000000, $m['revenue']['value']);
        $this->assertEquals(2000000, $m['receivable']['value']);

        // Có phiếu giảm trừ → không hủy hóa đơn được; phiếu giảm trừ bất biến
        $this->post(route('invoices.cancel', $inv))->assertStatus(422);
        $this->expectException(\LogicException::class);
        CreditNote::first()->delete();
    }

    public function test_reconciliation(): void
    {
        $inv = $this->invoice();
        $this->pay($inv, 3000000, 'payments.store', ['reference' => 'VCB-123']);
        $payment = Payment::firstOrFail();

        $accountant = $this->userWithRole('Accountant');
        $this->actingAs($accountant)->get(route('payments.index'))->assertOk()->assertSee('VCB-123');
        $this->assertContains('payments.index', \App\Support\Menu::flatRoutes($accountant));
        $this->post(route('payments.reconcile'), ['ids' => [$payment->id]])->assertSessionHas('success');
        $this->assertSame($accountant->id, $payment->fresh()->reconciled_by);
        $this->get(route('payments.index'))->assertDontSee('VCB-123');   // mặc định: chưa đối soát
        $this->get(route('payments.index', ['state' => 'done']))->assertSee('VCB-123');

        $this->actingAs($this->admin)->delete(route('payments.destroy', $payment))->assertSessionHasErrors('payment');
        $this->assertNotNull($payment->fresh());
        $this->post(route('payments.reconcile'), ['ids' => [$payment->id], 'undo' => 1]);
        $this->assertNull($payment->fresh()->reconciled_at);

        $sale = $this->userWithRole('Sales Employee');
        $this->actingAs($sale)->get(route('payments.index'))->assertForbidden();
        $this->post(route('payments.reconcile'), ['ids' => [$payment->id]])->assertForbidden();
    }

    public function test_payment_reminders_are_sent_once(): void
    {
        $finance = $this->userWithRole('Finance Manager');
        $soon = $this->invoice(['due_date' => today()->addDays(2)]);
        $late = $this->invoice(['due_date' => today()->subDays(5)]);
        $paidLate = $this->invoice(['due_date' => today()->subDays(5)]);
        $this->pay($paidLate, 11000000);

        $this->artisan('crm:sales-automation')->assertSuccessful();
        $this->assertNotNull($soon->fresh()->due_reminder_sent_at);
        $this->assertNotNull($late->fresh()->overdue_notified_at);
        $this->assertNull($paidLate->fresh()->overdue_notified_at);
        $this->assertSame(1, $finance->notifications()->count());   // chỉ hóa đơn quá hạn
        $this->assertSame(2, $this->admin->notifications()->count());   // người phụ trách: sắp đến hạn + quá hạn

        $this->artisan('crm:sales-automation')->assertSuccessful();
        $this->assertSame(1, $finance->notifications()->count(), 'Không nhắc lặp');
    }

    public function test_configured_tax_rates_are_enforced(): void
    {
        $this->actingAs($this->admin);
        $legacy = $this->invoice(['status' => 'draft', 'tax_rate' => 5]);
        $settings = fn ($default) => ['company_name' => 'TOP', 'brand_color' => '#FF6B00', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND',
            'date_format' => 'd/m/Y', 'default_tax_rate' => $default, 'tax_rates' => '0, 8, 10'];
        $this->put(route('settings.update'), $settings(7))->assertSessionHasErrors('default_tax_rate');
        $this->put(route('settings.update'), $settings(8))->assertSessionHasNoErrors();
        $this->assertSame([0.0, 8.0, 10.0], Setting::taxRates());

        $data = fn ($rate) => ['customer_id' => $this->customer()->id, 'issue_date' => today()->toDateString(), 'status' => 'draft', 'tax_rate' => $rate,
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]]];
        $this->post(route('quotations.store'), $data(5))->assertSessionHasErrors('tax_rate');
        $this->post(route('quotations.store'), $data(8))->assertSessionHasNoErrors();
        $this->get(route('quotations.create'))->assertSee('<option value="8"', false);
        // Hóa đơn cũ thuế 5% vẫn sửa được với thuế đang dùng
        $this->put(route('invoices.update', $legacy), $data(5) + ['due_date' => today()->addDays(5)->toDateString()])->assertSessionHasNoErrors();
    }

    public function test_internal_document_notice_and_isolation(): void
    {
        $inv = $this->invoice();
        $this->get(route('invoices.show', $inv))->assertOk()->assertSee(__('Hóa đơn trong CRM là chứng từ nội bộ để theo dõi công nợ — không phải hóa đơn điện tử hợp lệ theo quy định thuế Việt Nam.'));
        $this->get(route('invoices.pdf', $inv))->assertOk();

        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $invB = app(CurrentOrganization::class)->run($orgB->id, fn () => Invoice::create(['customer_id' => \App\Models\Customer::create(['name' => 'Khách B', 'type' => 'individual', 'status' => 'active'])->id,
            'issue_date' => today(), 'status' => 'issued', 'subtotal' => 100, 'total' => 100]));
        $this->actingAs($owner)->post(route('payments.refund', $invB->id), ['amount' => 1, 'payment_date' => today()->toDateString(), 'method' => 'cash', 'notes' => 'x'])->assertNotFound();
        $this->post(route('payments.credit', $invB->id), ['amount' => 1, 'issue_date' => today()->toDateString(), 'reason' => 'x'])->assertNotFound();
    }
}
