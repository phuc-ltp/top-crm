<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\SalesDocumentService;
use Tests\TestCase;

/** Bước 3.6 — báo cáo bán hàng theo sản phẩm, nhập–xuất–tồn, công nợ theo tuổi nợ (quyền & giá vốn). */
class Phase3ReportsTest extends TestCase
{
    private User $admin;

    private Product $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->admin();
        $this->p = Product::create(['sku' => 'R-1', 'name' => 'Máy in', 'type' => 'product', 'price' => 1000000, 'purchase_price' => 600000, 'unit' => 'cái', 'status' => 'active']);
    }

    private function invoice(array $attrs, float $qty = 2): Invoice
    {
        $this->actingAs($this->admin);

        return app(SalesDocumentService::class)->save(new Invoice, $attrs + ['customer_id' => $this->customer()->id, 'issue_date' => today(), 'due_date' => today()->addDays(10),
            'status' => 'issued', 'tax_rate' => 0, 'discount_amount' => 0, 'assigned_to' => $this->admin->id, 'created_by' => $this->admin->id],
            [['product_id' => $this->p->id, 'quantity' => $qty, 'unit_price' => '']]);
    }

    public function test_products_report_shows_margin_only_to_editors(): void
    {
        $this->invoice([], 3);
        $this->invoice(['status' => 'draft'], 10);   // nháp: không tính

        $report = $this->actingAs($this->admin)->get(route('reports.show', 'products'))->assertOk()->viewData('report');
        $this->assertSame(['R-1', 'Máy in', 3.0, 1, 3000000.0, 1800000.0, 1200000.0], $report['rows'][0]);

        $manager = $this->userWithRole('Sales Manager');   // xem sản phẩm, không sửa → không có giá vốn
        $row = $this->actingAs($manager)->get(route('reports.show', 'products'))->assertOk()->viewData('report')['rows'][0];
        $this->assertCount(5, $row);
        $csv = $this->get(route('reports.export', ['products', 'csv']))->streamedContent();
        $this->assertStringNotContainsString('1800000', $csv);
    }

    public function test_inventory_movement_report_and_permissions(): void
    {
        $wh = Warehouse::where('is_default', true)->firstOrFail();
        $svc = app(InventoryService::class);
        $this->travelTo(now()->subMonths(2));
        $svc->post(['type' => 'receipt', 'warehouse_id' => $wh->id, 'document_date' => today()->toDateString()], [['product_id' => $this->p->id, 'quantity' => 10, 'unit_cost' => 600000]], $this->admin);
        $this->travelBack();
        $svc->post(['type' => 'receipt', 'warehouse_id' => $wh->id, 'document_date' => today()->toDateString()], [['product_id' => $this->p->id, 'quantity' => 5, 'unit_cost' => 600000]], $this->admin);
        $svc->post(['type' => 'issue', 'warehouse_id' => $wh->id, 'document_date' => today()->toDateString()], [['product_id' => $this->p->id, 'quantity' => 7]], $this->admin);

        $q = ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()];
        $row = $this->actingAs($this->admin)->get(route('reports.show', ['inventory'] + $q))->assertOk()->viewData('report')['rows'][0];
        $this->assertSame(['R-1', 'Máy in', 'cái', 10.0, 5.0, 7.0, 8.0, 4800000.0], $row);   // tồn đầu 10 + nhập 5 − xuất 7 = 8

        $auditor = $this->userWithRole('Read-only Auditor');   // xem kho, không xem giá trị
        $this->assertCount(7, $this->actingAs($auditor)->get(route('reports.show', ['inventory'] + $q))->assertOk()->viewData('report')['rows'][0]);

        $marketing = $this->userWithRole('Marketing Manager');   // có báo cáo, không có quyền kho
        $this->actingAs($marketing)->get(route('reports.show', 'inventory'))->assertForbidden();
        $this->get(route('reports.index'))->assertOk()->assertDontSee(route('reports.show', 'inventory'));
    }

    public function test_receivables_aging(): void
    {
        $this->invoice(['due_date' => today()->addDays(5)]);                 // chưa đến hạn
        $this->invoice(['due_date' => today()->subDays(10)]);                // 1–30
        $old = $this->invoice(['due_date' => today()->subDays(100)]);        // > 90
        $paid = $this->invoice(['due_date' => today()->subDays(40)]);
        app(\App\Services\PaymentService::class)->record($paid, ['amount' => 2000000], $this->admin);
        app(\App\Services\PaymentService::class)->credit($old, ['amount' => 500000, 'reason' => 'Giảm giá'], $this->admin);

        $report = $this->actingAs($this->admin)->get(route('reports.show', 'receivables'))->assertOk()->viewData('report');
        $this->assertCount(3, $report['rows']);   // hóa đơn đã trả đủ không còn trong danh sách
        $this->assertSame([2000000.0, 2000000.0, 0.0, 0.0, 1500000.0], $report['chart']['values']);
        $this->assertSame(100, collect($report['rows'])->firstWhere(0, $old->number)[4]);
    }
}
