<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quotation;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Bước 3.1b — biến thể sản phẩm, bảng giá theo nhóm khách hàng, lịch sử sản phẩm. */
class VariantPriceListTest extends TestCase
{
    private function product(array $attrs = []): Product
    {
        return Product::create($attrs + ['sku' => 'P-'.uniqid(), 'name' => 'Áo thun', 'price' => 200000, 'unit' => 'cái', 'status' => 'active']);
    }

    private function productData(array $attrs = []): array
    {
        return $attrs + ['sku' => 'V-'.uniqid(), 'name' => 'Áo thun', 'type' => 'product', 'price' => 220000, 'unit' => 'cái', 'status' => 'active'];
    }

    public function test_variants_are_one_level_and_shown_on_parent(): void
    {
        $admin = $this->admin();
        $parent = $this->product();

        $this->actingAs($admin)->get(route('products.create', ['parent' => $parent->id]))->assertOk()->assertSee('value="'.$parent->id.'" selected', false);
        $this->post(route('products.store'), $this->productData(['sku' => 'AO-DO-XL', 'parent_id' => $parent->id]))->assertSessionHasErrors('variant_name');
        $this->post(route('products.store'), $this->productData(['sku' => 'AO-DO-XL', 'parent_id' => $parent->id, 'variant_name' => 'Đỏ / XL']))->assertSessionHasNoErrors();
        $variant = Product::where('sku', 'AO-DO-XL')->firstOrFail();
        $this->assertSame('Áo thun — Đỏ / XL', $variant->display_name);

        // Biến thể không làm cha được; cha có biến thể không làm biến thể được; không tự làm cha mình
        $this->post(route('products.store'), $this->productData(['parent_id' => $variant->id, 'variant_name' => 'Cháu']))->assertSessionHasErrors('parent_id');
        $other = $this->product(['name' => 'Quần']);
        $this->put(route('products.update', $parent), $this->productData(['sku' => $parent->sku, 'parent_id' => $other->id, 'variant_name' => 'X']))->assertSessionHasErrors('parent_id');
        $this->put(route('products.update', $other), $this->productData(['sku' => $other->sku, 'parent_id' => $other->id, 'variant_name' => 'X']))->assertSessionHasErrors('parent_id');

        $this->get(route('products.show', $parent))->assertSee('Đỏ / XL')->assertSee(__('Thêm biến thể'));
        $this->get(route('products.show', $variant))->assertSee(__('Biến thể của'))->assertDontSee(__('Thêm biến thể'));
        $this->get(route('quotations.create'))->assertSee('AO-DO-XL — Áo thun — Đỏ / XL');
        $this->get(route('products.index'))->assertOk()->assertSee('Đỏ / XL');
    }

    public function test_price_list_prices_are_used_for_the_customer(): void
    {
        $admin = $this->admin();
        $p1 = $this->product(['sku' => 'P1', 'price' => 1000000]);
        $p2 = $this->product(['sku' => 'P2', 'price' => 500000]);

        $this->actingAs($admin)->post(route('price-lists.store'), ['name' => 'Đại lý', 'is_active' => 1,
            'items' => [['product_id' => $p1->id, 'price' => 800000], ['product_id' => $p1->id, 'price' => 1]]])->assertSessionHasErrors('items.1.product_id');
        $this->post(route('price-lists.store'), ['name' => 'Đại lý', 'is_active' => 1, 'items' => [['product_id' => $p1->id, 'price' => 800000]]])->assertRedirect();
        $list = PriceList::firstOrFail();

        $agent = $this->customer(['name' => 'Đại lý Minh', 'price_list_id' => $list->id]);
        $retail = $this->customer(['name' => 'Khách lẻ']);
        $this->getJson(route('price-lists.for-customer', ['customer_id' => $agent->id]))->assertOk()
            ->assertJson(['price_list' => 'Đại lý', 'prices' => [$p1->id => '800000.00']]);
        $this->getJson(route('price-lists.for-customer', ['customer_id' => $retail->id]))->assertJson(['price_list' => null]);

        // Dòng để trống đơn giá → máy chủ dùng giá bảng giá (P1) hoặc giá chuẩn (P2); giá nhập tay vẫn được giữ
        $make = fn ($customer, $items) => $this->post(route('quotations.store'), ['customer_id' => $customer->id, 'issue_date' => today()->toDateString(),
            'valid_until' => today()->addDays(10)->toDateString(), 'status' => 'draft', 'tax_rate' => 0, 'items' => $items]);
        $make($agent, [['product_id' => $p1->id, 'quantity' => 2, 'unit_price' => ''], ['product_id' => $p2->id, 'quantity' => 1, 'unit_price' => ''],
            ['product_id' => $p1->id, 'quantity' => 1, 'unit_price' => 750000]])->assertSessionHasNoErrors();
        $q = Quotation::with('items')->latest('id')->firstOrFail();
        $this->assertEquals([800000, 500000, 750000], $q->items->pluck('unit_price')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals(2850000, (float) $q->total);

        $make($retail, [['product_id' => $p1->id, 'quantity' => 1, 'unit_price' => '']]);
        $this->assertEquals(1000000, (float) Quotation::latest('id')->first()->total);

        // Tắt bảng giá → giá chuẩn
        $this->put(route('price-lists.update', $list), ['name' => 'Đại lý', 'is_active' => 0, 'items' => [['product_id' => $p1->id, 'price' => 800000]]]);
        $make($agent, [['product_id' => $p1->id, 'quantity' => 1, 'unit_price' => '']]);
        $this->assertEquals(1000000, (float) Quotation::latest('id')->first()->total);

        // Xóa bảng giá → khách hàng về giá chuẩn, không lỗi
        $this->delete(route('price-lists.destroy', $list))->assertRedirect(route('price-lists.index'));
        $this->assertNull($agent->fresh()->price_list_id);
    }

    public function test_price_list_permissions_and_isolation(): void
    {
        $sale = $this->userWithRole('Sales Employee');
        $this->actingAs($sale)->get(route('price-lists.index'))->assertForbidden();
        $this->post(route('price-lists.store'), ['name' => 'X'])->assertForbidden();
        $this->assertNotContains('price-lists.index', \App\Support\Menu::flatRoutes($sale));

        // Giá theo khách hàng: chỉ khách hàng mình được xem
        $theirs = $this->customer(['assigned_to' => $this->admin()->id]);
        $this->actingAs($sale)->getJson(route('price-lists.for-customer', ['customer_id' => $theirs->id]))->assertNotFound();
        $this->actingAs($this->userWithRole('Read-only Auditor'))->getJson(route('price-lists.for-customer', ['customer_id' => $theirs->id]))->assertForbidden();

        // Không gắn được bảng giá của tổ chức khác
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $listB = app(CurrentOrganization::class)->run($orgB->id, fn () => PriceList::create(['name' => 'Bảng giá B', 'is_active' => true]));
        $c = $this->customer(['name' => 'Khách A']);
        $this->actingAs($owner)->put(route('customers.update', $c), ['name' => 'Khách A', 'type' => 'individual', 'status' => 'active', 'price_list_id' => $listB->id])
            ->assertSessionHasErrors('price_list_id');
        $this->get(route('price-lists.index'))->assertOk()->assertDontSee('Bảng giá B');
        $this->get(route('price-lists.edit', $listB->id))->assertNotFound();
    }

    public function test_product_history_is_only_for_editors(): void
    {
        $admin = $this->admin();
        $p = $this->product(['sku' => 'H-1', 'price' => 100000]);
        $this->actingAs($admin)->put(route('products.update', $p), $this->productData(['sku' => 'H-1', 'name' => 'Áo thun', 'price' => 120000, 'purchase_price' => 70000]))->assertSessionHasNoErrors();

        $this->get(route('products.show', $p))->assertSee(__('Lịch sử thay đổi'))->assertSee('<code>price</code>', false);
        $this->actingAs($this->userWithRole('Sales Employee'))->get(route('products.show', $p))->assertOk()
            ->assertDontSee(__('Lịch sử thay đổi'))->assertDontSee('70000');
    }
}
