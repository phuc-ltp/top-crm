<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\PaymentService;
use App\Services\SalesDocumentService;
use Tests\TestCase;

/** Tiền VND không có xu: tổng / thuế / thành tiền tròn tới đồng; hóa đơn cũ còn lẻ < 0,5 đ coi như đã thu đủ. */
class VndRoundingTest extends TestCase
{
    public function test_vnd_document_amounts_are_whole_dong(): void
    {
        $this->actingAs($this->admin());
        // 3 × 33.333 × (100 − 7,5)% = 92.499,075 → 92.499 ; thuế 8% = 7.399,92 → 7.400
        $r = app(SalesDocumentService::class)->calculate([['description' => 'Dịch vụ', 'quantity' => 3, 'unit_price' => 33333, 'discount_percent' => 7.5]], 0, 8);
        $this->assertSame(92499.0, $r['items'][0]['line_total']);
        $this->assertSame(7400.0, $r['tax_amount']);
        $this->assertSame(99899.0, $r['total']);
        $this->assertSame(1.5, app(SalesDocumentService::class)->calculate([['description' => 'X', 'quantity' => 1.5, 'unit_price' => 1]])['items'][0]['quantity']);   // số lượng vẫn có lẻ
    }

    public function test_foreign_currency_keeps_cents(): void
    {
        $this->actingAs($this->admin());
        Setting::put(['currency' => 'USD']);
        $r = app(SalesDocumentService::class)->calculate([['description' => 'Service', 'quantity' => 3, 'unit_price' => 33.33, 'discount_percent' => 0]], 0, 10);
        $this->assertSame(99.99, $r['subtotal']);
        $this->assertSame(10.0, $r['tax_amount']);
        $this->assertSame(109.99, $r['total']);
    }

    public function test_old_invoice_with_fraction_left_is_paid_and_form_hidden(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $invoice = app(SalesDocumentService::class)->save(new Invoice, [
            'customer_id' => $this->customer()->id, 'issue_date' => today(), 'status' => 'issued', 'tax_rate' => 0,
        ], [['description' => 'Dịch vụ', 'quantity' => 1, 'unit_price' => 1000]]);
        $invoice->forceFill(['total' => 102599897.40, 'subtotal' => 102599897.40])->saveQuietly();   // hóa đơn cũ có xu

        $this->post(route('payments.store', $invoice), ['amount' => 102599897, 'payment_date' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame('0', PaymentService::due($invoice));
        $this->get(route('invoices.show', $invoice))->assertOk()->assertDontSee(route('payments.store', $invoice));   // không còn form ghi nhận
        $this->post(route('payments.store', $invoice), ['amount' => 1, 'payment_date' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasErrors('amount');
    }

    public function test_fraction_of_half_dong_or_more_still_counts(): void
    {
        $this->actingAs($this->admin());
        $invoice = app(SalesDocumentService::class)->save(new Invoice, [
            'customer_id' => $this->customer()->id, 'issue_date' => today(), 'status' => 'issued', 'tax_rate' => 0,
        ], [['description' => 'Dịch vụ', 'quantity' => 1, 'unit_price' => 1000]]);
        $invoice->forceFill(['total' => 1000.60])->saveQuietly();
        app(PaymentService::class)->record($invoice, ['amount' => 1000], auth()->user());
        $invoice->refresh();
        $this->assertSame('partial', $invoice->payment_status);   // 1000,60 → làm tròn 1.001 ₫: còn 1 ₫
        $this->assertSame('1', PaymentService::due($invoice));
    }

    public function test_migration_repairs_stuck_invoices_only(): void
    {
        $this->actingAs($this->admin());
        $make = function (float $total, float $paid, string $status) {
            $inv = app(SalesDocumentService::class)->save(new Invoice, ['customer_id' => $this->customer()->id, 'issue_date' => today(), 'status' => 'issued', 'tax_rate' => 0],
                [['description' => 'D', 'quantity' => 1, 'unit_price' => 1000]]);
            $inv->forceFill(['total' => $total, 'amount_paid' => $paid, 'payment_status' => $status])->saveQuietly();

            return $inv;
        };
        $stuck = $make(500.40, 500, 'partial');
        $real = $make(500, 300, 'partial');
        $unpaid = $make(0.40, 0, 'unpaid');

        (require database_path('migrations/2026_11_17_100000_fix_vnd_fraction_invoice_status.php'))->up();

        $this->assertSame('paid', $stuck->fresh()->payment_status);
        $this->assertSame('partial', $real->fresh()->payment_status);
        $this->assertSame('unpaid', $unpaid->fresh()->payment_status);
        $this->assertEquals(500.40, (float) $stuck->fresh()->total);   // không đổi số tiền
    }
}
