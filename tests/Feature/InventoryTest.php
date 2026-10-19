<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockDocument;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Bước 3.2 — kho hàng: phiếu nhập/xuất/điều chỉnh/chuyển, sổ kho bất biến, giá vốn bình quân, tồn âm, quyền, cách ly. */
class InventoryTest extends TestCase
{
    private User $admin;

    private Product $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->admin();
        $this->p = Product::create(['sku' => 'INV-1', 'name' => 'Chuột không dây', 'type' => 'product', 'price' => 200000, 'unit' => 'cái', 'status' => 'active']);
    }

    private function main(): Warehouse
    {
        return Warehouse::where('is_default', true)->firstOrFail();
    }

    private function postDoc(string $type, array $items, array $extra = [])
    {
        return $this->post(route('stock-documents.store'), $extra + ['type' => $type, 'warehouse_id' => $this->main()->id,
            'document_date' => today()->toDateString(), 'items' => $items]);
    }

    private function onHand(?Warehouse $w = null): float
    {
        return (float) StockLevel::where('product_id', $this->p->id)->where('warehouse_id', ($w ?? $this->main())->id)->value('quantity');
    }

    public function test_receipts_update_weighted_average_cost_and_ledger(): void
    {
        $this->actingAs($this->admin);
        $this->postDoc('receipt', [['product_id' => $this->p->id, 'quantity' => 10, 'unit_cost' => 100]])->assertSessionHasNoErrors();
        $this->postDoc('receipt', [['product_id' => $this->p->id, 'quantity' => 20, 'unit_cost' => 130]])->assertSessionHasNoErrors();

        $this->assertSame(30.0, $this->onHand());
        $this->assertSame('120.00', $this->p->fresh()->average_cost);   // (10×100 + 20×130) ÷ 30
        $this->assertSame([10.0, 30.0], StockMovement::orderBy('id')->pluck('balance_after')->map(fn ($v) => (float) $v)->all());
        $doc = StockDocument::latest('id')->first();
        $this->assertSame('PN'.str_pad((string) $doc->id, 5, '0', STR_PAD_LEFT), $doc->number);
        $this->get(route('stock-documents.show', $doc))->assertOk()->assertSee('INV-1');
        $this->get(route('inventory.index'))->assertOk()->assertSee('Chuột không dây')->assertSee(__('Giá vốn BQ'));
    }

    public function test_negative_stock_is_blocked_and_rolled_back_unless_allowed(): void
    {
        $this->actingAs($this->admin);
        $this->postDoc('receipt', [['product_id' => $this->p->id, 'quantity' => 5, 'unit_cost' => 100]]);
        $docs = StockDocument::count();
        $moves = StockMovement::count();

        $this->postDoc('issue', [['product_id' => $this->p->id, 'quantity' => 6]])->assertSessionHasErrors('items');
        $this->assertSame(5.0, $this->onHand());
        $this->assertSame([$docs, $moves], [StockDocument::count(), StockMovement::count()], 'Phiếu lỗi không được ghi gì');

        $this->postDoc('issue', [['product_id' => $this->p->id, 'quantity' => 5]])->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->onHand());

        Setting::put(['inventory_allow_negative' => '1'], 'inventory');
        $this->postDoc('issue', [['product_id' => $this->p->id, 'quantity' => 2]])->assertSessionHasNoErrors();
        $this->assertSame(-2.0, $this->onHand());
    }

    public function test_adjustment_and_transfer(): void
    {
        $this->actingAs($this->admin);
        $second = Warehouse::create(['code' => 'KHO-2', 'name' => 'Kho Hà Nội', 'is_active' => true]);
        $this->postDoc('receipt', [['product_id' => $this->p->id, 'quantity' => 5, 'unit_cost' => 100]]);

        // Kiểm kê: đếm được 8 → +3; đếm lại 8 → không phát sinh
        $this->postDoc('adjustment', [['product_id' => $this->p->id, 'counted_quantity' => 8]])->assertSessionHasNoErrors();
        $this->assertSame(8.0, $this->onHand());
        $this->assertSame('adjustment_in', StockMovement::latest('id')->value('type'));
        $moves = StockMovement::count();
        $this->postDoc('adjustment', [['product_id' => $this->p->id, 'counted_quantity' => 8]])->assertSessionHasNoErrors();
        $this->assertSame($moves, StockMovement::count());

        // Chuyển kho
        $this->postDoc('transfer', [['product_id' => $this->p->id, 'quantity' => 3]], ['to_warehouse_id' => $second->id])->assertSessionHasNoErrors();
        $this->assertSame([5.0, 3.0], [$this->onHand(), $this->onHand($second)]);
        $this->postDoc('transfer', [['product_id' => $this->p->id, 'quantity' => 6]], ['to_warehouse_id' => $second->id])->assertSessionHasErrors('items');
        $this->postDoc('transfer', [['product_id' => $this->p->id, 'quantity' => 1]], ['to_warehouse_id' => $this->main()->id])->assertSessionHasErrors('to_warehouse_id');

        // Sổ kho khớp tồn kho ở mọi kho
        foreach (StockLevel::all() as $level) {
            $this->assertEquals((float) $level->quantity, (float) StockMovement::where('warehouse_id', $level->warehouse_id)->where('product_id', $level->product_id)->sum('quantity'));
        }
    }

    public function test_documents_and_ledger_are_immutable_and_services_have_no_stock(): void
    {
        $this->actingAs($this->admin);
        $this->postDoc('receipt', [['product_id' => $this->p->id, 'quantity' => 1, 'unit_cost' => 1]]);
        $doc = StockDocument::firstOrFail();
        $this->put('/stock-documents/'.$doc->id, [])->assertStatus(405);
        $this->delete('/stock-documents/'.$doc->id)->assertStatus(405);

        try {
            StockMovement::first()->update(['quantity' => 999]);
            $this->fail('Sổ kho phải bất biến');
        } catch (\LogicException) {
        }
        try {
            $doc->delete();
            $this->fail('Phiếu kho phải bất biến');
        } catch (\LogicException) {
        }

        $service = Product::create(['sku' => 'SV', 'name' => 'Tư vấn', 'type' => 'service', 'price' => 1, 'unit' => 'giờ', 'status' => 'active']);
        $this->postDoc('receipt', [['product_id' => $service->id, 'quantity' => 1]])->assertSessionHasErrors('items.0.product_id');
    }

    public function test_permissions_and_cost_visibility(): void
    {
        $sale = $this->userWithRole('Sales Employee');   // chỉ xem
        $this->actingAs($sale)->get(route('inventory.index'))->assertOk()->assertDontSee(__('Giá vốn BQ'))->assertDontSee(__('Giá trị tồn kho (theo bộ lọc)'));
        $this->get(route('stock-documents.create', ['type' => 'receipt']))->assertForbidden();
        $this->postDoc('receipt', [['product_id' => $this->p->id, 'quantity' => 1]])->assertForbidden();
        $this->get(route('warehouses.index'))->assertForbidden();
        $this->assertContains('inventory.index', \App\Support\Menu::flatRoutes($sale));
        $this->assertNotContains('warehouses.index', \App\Support\Menu::flatRoutes($sale));

        // Chỉ được lập phiếu, không được kiểm kê
        $clerk = User::factory()->create(['status' => 'active']);
        $clerk->givePermissionTo(['inventory.view', 'inventory.create']);
        $this->actingAs($clerk)->post('receipt', [['product_id' => $this->p->id, 'quantity' => 2, 'unit_cost' => 50]])->assertSessionHasNoErrors();
        $this->postDoc('adjustment', [['product_id' => $this->p->id, 'counted_quantity' => 0]])->assertForbidden();
        // Không xem được giá vốn → đơn giá nhập gửi lên bị bỏ qua (dùng giá vốn hiện tại)
        $this->assertNotEquals('50.00', StockMovement::latest('id')->value('unit_cost'));

        $this->actingAs($this->userWithRole('Inventory Manager'))->get(route('warehouses.index'))->assertOk();
    }

    public function test_low_stock_alert_is_sent_once_per_day(): void
    {
        $this->actingAs($this->admin);
        $manager = $this->userWithRole('Inventory Manager');
        $this->p->update(['reorder_level' => 3]);
        $this->postDoc('receipt', [['product_id' => $this->p->id, 'quantity' => 10, 'unit_cost' => 1]]);
        $this->assertSame(0, $manager->notifications()->count());

        $this->postDoc('issue', [['product_id' => $this->p->id, 'quantity' => 8]]);
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertStringContainsString('Chuột không dây', $manager->notifications()->first()->data['message']);
        $this->postDoc('issue', [['product_id' => $this->p->id, 'quantity' => 1]]);
        $this->assertSame(1, $manager->notifications()->count(), 'Không gửi lặp trong ngày');
    }

    public function test_warehouse_rules_and_organization_isolation(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('warehouses.store'), ['code' => 'KHO-HCM', 'name' => 'Kho HCM', 'is_default' => 1])->assertSessionHasNoErrors();
        $hcm = Warehouse::where('code', 'KHO-HCM')->firstOrFail();
        $this->assertTrue($hcm->is_default);
        $this->assertSame(1, Warehouse::where('is_default', true)->count());
        $this->put(route('warehouses.update', $hcm), ['code' => 'KHO-HCM', 'name' => 'Kho HCM', 'is_active' => 0, 'is_default' => 0])->assertSessionHasErrors('is_default');

        $this->postDoc('receipt', [['product_id' => $this->p->id, 'quantity' => 1, 'unit_cost' => 1]], ['warehouse_id' => $hcm->id]);
        $this->delete(route('warehouses.destroy', $hcm))->assertSessionHasErrors('warehouse');

        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        [$whB, $docB] = app(CurrentOrganization::class)->run($orgB->id, function () {
            $w = Warehouse::create(['code' => 'KHO-B', 'name' => 'Kho B', 'is_default' => true, 'is_active' => true]);
            $p = Product::create(['sku' => 'B-1', 'name' => 'Hàng B', 'type' => 'product', 'price' => 1, 'unit' => 'cái', 'status' => 'active']);

            return [$w, app(\App\Services\InventoryService::class)->post(['type' => 'receipt', 'warehouse_id' => $w->id, 'document_date' => today()->toDateString()],
                [['product_id' => $p->id, 'quantity' => 5, 'unit_cost' => 1]], User::first())];
        });

        $this->actingAs($owner)->get(route('stock-documents.show', $docB->id))->assertNotFound();
        $this->get(route('inventory.index'))->assertDontSee('Hàng B');
        $this->get(route('inventory.movements'))->assertDontSee('Hàng B');
        $this->postDoc('receipt', [['product_id' => $this->p->id, 'quantity' => 1]], ['warehouse_id' => $whB->id])->assertSessionHasErrors('warehouse_id');
    }
}
