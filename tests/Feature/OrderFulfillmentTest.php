<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\StockDocument;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Bước 3.3 — đơn hàng ↔ kho: giữ hàng khi duyệt, xuất kho khi hoàn thành, trả phần giữ khi hủy, trả hàng. */
class OrderFulfillmentTest extends TestCase
{
    private User $admin;

    private Product $p;

    private Product $svc;

    private Warehouse $wh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->admin();
        \App\Models\Setting::put(['inventory_track_orders' => '1'], 'inventory');
        $this->wh = Warehouse::where('is_default', true)->firstOrFail();
        $this->p = Product::create(['sku' => 'BAN-PHIM', 'name' => 'Bàn phím', 'type' => 'product', 'price' => 500000, 'unit' => 'cái', 'status' => 'active']);
        $this->svc = Product::create(['sku' => 'LAP-DAT', 'name' => 'Lắp đặt', 'type' => 'service', 'price' => 100000, 'unit' => 'lần', 'status' => 'active']);
        app(InventoryService::class)->post(['type' => 'receipt', 'warehouse_id' => $this->wh->id, 'document_date' => today()->toDateString()],
            [['product_id' => $this->p->id, 'quantity' => 10, 'unit_cost' => 300000]], $this->admin);
    }

    private function payload(array $extra = [], float $qty = 4): array
    {
        return $extra + ['customer_id' => $this->customer()->id, 'order_date' => today()->toDateString(), 'status' => 'pending', 'tax_rate' => 0,
            'warehouse_id' => $this->wh->id, 'items' => [
                ['product_id' => $this->p->id, 'quantity' => $qty, 'unit_price' => ''],
                ['product_id' => $this->svc->id, 'quantity' => 1, 'unit_price' => ''],
            ]];
    }

    private function level(): StockLevel
    {
        return StockLevel::where('warehouse_id', $this->wh->id)->where('product_id', $this->p->id)->firstOrFail();
    }

    public function test_full_flow_reserve_edit_deliver_and_return(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload())->assertSessionHasNoErrors();
        $order = SalesOrder::latest('id')->firstOrFail();
        $this->assertSame($this->wh->id, $order->warehouse_id);
        $this->assertSame(0, StockReservation::count(), 'Chờ duyệt: chưa giữ hàng');

        $this->post(route('orders.approve', $order))->assertSessionHasNoErrors();
        $this->assertEquals([10, 4], [(float) $this->level()->quantity, (float) $this->level()->reserved]);   // dịch vụ không giữ
        $this->assertSame(6.0, (float) InventoryService::stockOf($this->p)['available']);

        // Sửa dòng khi đang giữ → tính lại phần giữ; lịch sử sửa dòng được ghi
        $data = $this->payload(['customer_id' => $order->customer_id, 'status' => 'confirmed'], 6);
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->assertSame(6.0, (float) $this->level()->reserved);
        $this->assertTrue(ActivityLog::where('subject_id', $order->id)->where('description', 'like', 'Sửa dòng%')->exists());

        // Hoàn thành → phiếu xuất kho (một lần), trả phần giữ
        $this->put(route('orders.update', $order), ['status' => 'completed'] + $data)->assertSessionHasNoErrors();
        $this->assertEquals([4, 0], [(float) $this->level()->quantity, (float) $this->level()->reserved]);
        $issue = StockDocument::where('sales_order_id', $order->id)->where('type', 'issue')->firstOrFail();
        $this->assertSame($order->number, $issue->reference);
        $this->assertSame(1, $issue->items()->count());
        $this->put(route('orders.update', $order), ['status' => 'completed'] + $data)->assertForbidden();   // đơn hoàn thành bị khóa
        $this->get(route('orders.show', $order))->assertOk()->assertSee($issue->number)->assertSee(__('Trả hàng (nhập lại kho)'));

        // Trả hàng: tối đa số đã giao chưa trả
        $this->post(route('orders.return', $order), ['reason' => 'Lỗi phím', 'items' => [['product_id' => $this->p->id, 'quantity' => 2]]])->assertSessionHasNoErrors();
        $this->assertSame(6.0, (float) $this->level()->quantity);
        $this->assertSame('NT', substr(StockDocument::where('type', 'return')->value('number'), 0, 2));
        $this->post(route('orders.return', $order), ['reason' => 'Trả thêm', 'items' => [['product_id' => $this->p->id, 'quantity' => 5]]])->assertSessionHasErrors('items');
        $this->assertSame(6.0, (float) $this->level()->quantity);
    }

    public function test_not_enough_stock_blocks_approval_and_cancel_releases(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload([], 12));
        $big = SalesOrder::latest('id')->firstOrFail();
        $this->post(route('orders.approve', $big))->assertSessionHasErrors('status');
        $this->assertSame('pending', $big->fresh()->status);
        $this->assertSame(0.0, (float) $this->level()->reserved);

        $this->post(route('orders.store'), $this->payload([], 3));
        $order = SalesOrder::latest('id')->firstOrFail();
        $this->post(route('orders.approve', $order));
        $this->assertSame(3.0, (float) $this->level()->reserved);
        $this->delete(route('orders.destroy', $order))->assertSessionHasErrors('status');   // đang giữ hàng → phải hủy trước
        $this->put(route('orders.update', $order), $this->payload(['customer_id' => $order->customer_id, 'status' => 'cancelled'], 3))->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) $this->level()->reserved);
        $this->assertSame(0, StockReservation::count());
        $this->delete(route('orders.destroy', $order))->assertSessionHasNoErrors();
    }

    public function test_status_guard_and_direct_completion_by_approver(): void
    {
        $sale = $this->userWithRole('Sales Employee');
        $c = $this->customer(['assigned_to' => $sale->id]);
        $this->actingAs($sale)->post(route('orders.store'), $this->payload(['customer_id' => $c->id]));
        $order = SalesOrder::latest('id')->firstOrFail();
        $this->put(route('orders.update', $order), $this->payload(['customer_id' => $c->id, 'status' => 'completed']))->assertSessionHasErrors('status');
        $this->put(route('orders.update', $order), $this->payload(['customer_id' => $c->id, 'status' => 'processing']))->assertSessionHasErrors('status');
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(10.0, (float) $this->level()->quantity);

        // Người có quyền duyệt tạo đơn "hoàn thành" ngay → coi như duyệt + giao
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload(['status' => 'completed'], 2))->assertSessionHasNoErrors();
        $done = SalesOrder::latest('id')->firstOrFail();
        $this->assertSame($this->admin->id, $done->approved_by);
        $this->assertSame(8.0, (float) $this->level()->quantity);
    }

    public function test_tracking_is_off_by_default_and_legacy_orders_never_touch_stock(): void
    {
        // Tắt theo dõi kho cho đơn hàng → đơn mới không gắn kho, duyệt không cần tồn
        \App\Models\Setting::put(['inventory_track_orders' => '0'], 'inventory');
        $this->actingAs($this->admin)->post(route('orders.store'), array_diff_key($this->payload([], 50), ['warehouse_id' => 1]))->assertSessionHasNoErrors();
        $free = SalesOrder::latest('id')->firstOrFail();
        $this->assertNull($free->warehouse_id);
        $this->post(route('orders.approve', $free))->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) $this->level()->reserved);
        \App\Models\Setting::put(['inventory_track_orders' => '1'], 'inventory');

        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload(['warehouse_id' => null, 'status' => 'completed'], 5))->assertSessionHasNoErrors();
        $order = SalesOrder::latest('id')->firstOrFail();
        $this->assertNull($order->warehouse_id);
        $this->assertSame(10.0, (float) $this->level()->quantity);
        $this->assertSame(0, StockDocument::where('sales_order_id', $order->id)->count());
    }

    public function test_quotation_conversion_uses_default_warehouse_and_isolation(): void
    {
        $this->actingAs($this->admin)->post(route('quotations.store'), ['customer_id' => $this->customer()->id, 'issue_date' => today()->toDateString(),
            'valid_until' => today()->addDays(5)->toDateString(), 'status' => 'sent', 'tax_rate' => 0, 'items' => [['product_id' => $this->p->id, 'quantity' => 1, 'unit_price' => '']]]);
        $this->post(route('quotations.convert', Quotation::latest('id')->first()))->assertRedirect();
        $this->assertSame($this->wh->id, SalesOrder::latest('id')->value('warehouse_id'));

        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $whB = app(CurrentOrganization::class)->run($orgB->id, fn () => Warehouse::create(['code' => 'KHO-B', 'name' => 'Kho B', 'is_active' => true]));
        $this->post(route('orders.store'), $this->payload(['warehouse_id' => $whB->id]))->assertSessionHasErrors('warehouse_id');
    }
}
