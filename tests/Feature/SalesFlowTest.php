<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Tests\TestCase;

class SalesFlowTest extends TestCase
{
    private function product(float $price = 1000000): Product
    {
        return Product::create(['sku' => 'SP-'.uniqid(), 'name' => 'Sản phẩm test', 'price' => $price, 'unit' => 'cái', 'status' => 'active']);
    }

    public function test_quotation_totals_are_computed_on_backend_ignoring_client_values(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $product = $this->product(1000000);

        $this->post(route('quotations.store'), [
            'customer_id' => $customer->id, 'issue_date' => today()->toDateString(), 'status' => 'draft',
            'tax_rate' => 10, 'discount_amount' => 100000,
            'subtotal' => 1, 'total' => 1, // giá trị giả mạo từ trình duyệt — phải bị bỏ qua
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000000, 'discount_percent' => 10], // 1.800.000
                ['description' => 'Phí lắp đặt', 'quantity' => 1, 'unit_price' => 500000],                       //   500.000
            ],
        ])->assertRedirect();

        $q = Quotation::firstOrFail();
        $this->assertEquals(2300000, (float) $q->subtotal);
        $this->assertEquals(100000, (float) $q->discount_amount);
        $this->assertEquals(220000, (float) $q->tax_amount);   // (2.300.000 - 100.000) × 10%
        $this->assertEquals(2420000, (float) $q->total);
        $this->assertSame(2, $q->items()->count());
        $this->assertMatchesRegularExpression('/^BG\d{5}$/', $q->number);
    }

    public function test_quotation_requires_items(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('quotations.store'), ['customer_id' => $this->customer()->id, 'issue_date' => today()->toDateString(), 'status' => 'draft', 'tax_rate' => 10, 'items' => []])
            ->assertSessionHasErrors('items');
    }

    public function test_full_flow_quotation_order_invoice_payment(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $product = $this->product(5000000);

        $this->post(route('quotations.store'), [
            'customer_id' => $customer->id, 'issue_date' => today()->toDateString(), 'status' => 'sent', 'tax_rate' => 10,
            'items' => [['product_id' => $product->id, 'quantity' => 2]], // không gửi đơn giá → lấy giá sản phẩm
        ]);
        $quotation = Quotation::firstOrFail();
        $this->assertEquals(11000000, (float) $quotation->total);

        // Báo giá → Đơn hàng
        $this->post(route('quotations.convert', $quotation))->assertRedirect();
        $order = SalesOrder::firstOrFail();
        $this->assertSame('accepted', $quotation->fresh()->status);
        $this->assertSame('pending', $order->status);
        $this->assertEquals(11000000, (float) $order->total);

        // Chưa duyệt thì chưa lập hóa đơn
        $this->post(route('orders.invoice', $order))->assertStatus(422);
        $this->post(route('orders.approve', $order))->assertSessionHas('success');
        $this->assertSame('confirmed', $order->fresh()->status);

        // Đơn hàng → Hóa đơn nháp
        $this->post(route('orders.invoice', $order))->assertRedirect();
        $invoice = Invoice::firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->post(route('orders.invoice', $order))->assertSessionHasErrors('status'); // không lập trùng

        // Hóa đơn nháp chưa nhận thanh toán
        $this->post(route('payments.store', $invoice), ['amount' => 1000, 'payment_date' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasErrors('amount');

        $this->post(route('invoices.issue', $invoice))->assertSessionHas('success');
        $this->get(route('invoices.edit', $invoice))->assertForbidden(); // đã phát hành thì khóa

        // Thanh toán một phần → đủ → không vượt quá
        $this->post(route('payments.store', $invoice), ['amount' => 5000000, 'payment_date' => today()->toDateString(), 'method' => 'bank_transfer']);
        $this->assertSame('partial', $invoice->fresh()->payment_status);
        $this->post(route('payments.store', $invoice), ['amount' => 7000000, 'payment_date' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->post(route('payments.store', $invoice), ['amount' => 6000000, 'payment_date' => today()->toDateString(), 'method' => 'cash']);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertEquals(11000000, (float) $invoice->amount_paid);
        $this->assertEquals(0, $invoice->balance);

        // Xóa một khoản thanh toán → tính lại
        $this->delete(route('payments.destroy', $invoice->payments()->first()))->assertSessionHas('success');
        $this->assertSame('partial', $invoice->fresh()->payment_status);

        // Dashboard phản ánh doanh thu thực thu
        $kpis = (new \App\Services\DashboardService(auth()->user()))->kpis(now()->startOfMonth(), now()->endOfMonth());
        $this->assertEquals((float) $invoice->fresh()->amount_paid, $kpis['revenue']);
    }

    public function test_cancel_invoice_rules(): void
    {
        $this->actingAs($this->admin());
        $invoice = app(\App\Services\SalesDocumentService::class)->save(new Invoice, [
            'customer_id' => $this->customer()->id, 'issue_date' => today(), 'status' => 'issued', 'tax_rate' => 0,
        ], [['description' => 'Dịch vụ', 'quantity' => 1, 'unit_price' => 100]]);

        app(\App\Services\PaymentService::class)->record($invoice, ['amount' => 50], auth()->user());
        $this->post(route('invoices.cancel', $invoice))->assertStatus(422);
        $invoice->payments()->delete();
        $this->post(route('invoices.cancel', $invoice))->assertSessionHas('success');
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }

    public function test_sales_staff_cannot_approve_own_order(): void
    {
        $staff = $this->userWithRole('Sales Staff');
        $order = app(\App\Services\SalesDocumentService::class)->save(new SalesOrder, [
            'customer_id' => $this->customer(['assigned_to' => $staff->id])->id, 'order_date' => today(), 'status' => 'pending',
            'tax_rate' => 0, 'created_by' => $staff->id, 'assigned_to' => $staff->id,
        ], [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]]);

        $this->actingAs($staff)->post(route('orders.approve', $order))->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_sales_target_progress(): void
    {
        $this->actingAs($this->admin());
        $staff = $this->userWithRole('Sales Staff');
        $this->post(route('targets.store'), ['user_id' => $staff->id, 'year' => now()->year, 'month' => now()->month, 'target_amount' => 1000000])->assertRedirect();
        $this->get(route('targets.index'))->assertOk()->assertSee($staff->name);
    }
}
