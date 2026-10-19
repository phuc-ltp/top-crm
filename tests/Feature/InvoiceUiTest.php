<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\PaymentService;
use App\Services\SalesDocumentService;
use Tests\TestCase;

/** Giao diện hóa đơn: tổng quan công nợ, lọc quá hạn / sắp đến hạn, trình bày chứng từ, thông tin chuyển khoản, PDF. */
class InvoiceUiTest extends TestCase
{
    private function invoice(array $attrs = [], float $price = 1000000): Invoice
    {
        return app(SalesDocumentService::class)->save(new Invoice, $attrs + ['customer_id' => $this->customer()->id, 'issue_date' => today(),
            'due_date' => today()->addDays(20), 'status' => 'issued', 'tax_rate' => 0, 'discount_amount' => 0, 'assigned_to' => auth()->id(), 'created_by' => auth()->id()],
            [['description' => 'Dịch vụ', 'quantity' => 1, 'unit_price' => $price]]);
    }

    public function test_list_summary_and_quick_filters(): void
    {
        $this->actingAs($admin = $this->admin());
        $late = $this->invoice(['due_date' => today()->subDays(12)], 3000000);
        $soon = $this->invoice(['due_date' => today()->addDays(3)], 2000000);
        $paid = $this->invoice([], 5000000);
        app(PaymentService::class)->record($paid, ['amount' => 5000000], $admin);
        $this->invoice(['status' => 'draft'], 9000000);

        $page = $this->get(route('invoices.index'))->assertOk();
        $sum = $page->viewData('invoiceSummary');
        $this->assertEquals([5000000, 2, 3000000, 1, 2000000, 1, 5000000], [$sum['receivable'], $sum['open_count'], $sum['overdue'], $sum['overdue_count'], $sum['soon'], $sum['soon_count'], $sum['paid_month']]);
        $page->assertSee(__('Quá hạn :days ngày', ['days' => 12]));

        $this->get(route('invoices.index', ['due' => 'overdue']))->assertSee($late->number)->assertDontSee($soon->number)->assertDontSee($paid->number);
        $this->get(route('invoices.index', ['due' => 'soon']))->assertSee($soon->number)->assertDontSee($late->number);
    }

    public function test_invoice_document_shows_seller_bank_info_and_progress(): void
    {
        $this->actingAs($admin = $this->admin());
        Setting::put(['company_tax_code' => '0312345678', 'bank_name' => 'Vietcombank', 'bank_account_number' => '0071000123456', 'bank_account_name' => 'CONG TY TOP', 'invoice_terms' => 'Thanh toán trong 15 ngày.']);
        $inv = $this->invoice([], 2000000);
        app(PaymentService::class)->record($inv, ['amount' => 500000], $admin);

        $this->get(route('invoices.show', $inv))->assertOk()
            ->assertSee('0312345678')->assertSee('0071000123456')->assertSee('Thanh toán trong 15 ngày.')
            ->assertSee(__('Thanh toán một phần'))->assertSee('25%')->assertSee(__('Thông tin chuyển khoản'));
        $this->get(route('invoices.pdf', $inv))->assertOk();

        // Đã trả đủ → không hiện thông tin chuyển khoản nữa
        app(PaymentService::class)->record($inv, ['amount' => 1500000], $admin);
        $this->get(route('invoices.show', $inv))->assertDontSee(__('Thông tin chuyển khoản'))->assertSee('100%');
    }

    public function test_document_settings_are_validated_and_per_organization(): void
    {
        $this->actingAs($this->admin());
        $base = ['company_name' => 'TOP', 'brand_color' => '#FF6B00', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND', 'date_format' => 'd/m/Y', 'default_tax_rate' => 10];
        $this->put(route('settings.update'), $base + ['bank_account_number' => '<script>'])->assertSessionHasErrors('bank_account_number');
        $this->put(route('settings.update'), $base + ['bank_name' => 'ACB', 'bank_account_number' => '123 456 789', 'bank_account_name' => 'TOP'])->assertSessionHasNoErrors();
        $this->assertSame('ACB', Setting::get('bank_name'));

        $orgB = \App\Models\Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $this->assertNotSame('ACB', Setting::allCached($orgB->id)['bank_name'] ?? null);
    }
}
