<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\SalesDocumentService;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/**
 * Bước 3.1 — nhà cung cấp, loại sản phẩm / dịch vụ, giá vốn (chỉ người sửa sản phẩm thấy), tính tiền chính xác.
 */
class SupplierCatalogTest extends TestCase
{
    public function test_supplier_crud_search_export_and_trash(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('suppliers.store'), [
            'name' => 'Công ty Linh Kiện Sài Gòn', 'contact_name' => 'Anh Tâm', 'phone' => '0281234567', 'email' => 'lk@example.com',
            'tax_code' => '0312345678', 'website' => 'https://linhkien.example.com', 'status' => 'active',
        ])->assertRedirect();
        $s = Supplier::firstOrFail();
        $this->assertSame('NCC'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT), $s->code);
        $this->assertSame($admin->id, $s->created_by);

        $this->get(route('suppliers.index', ['q' => '0312345678']))->assertOk()->assertSee('Công ty Linh Kiện Sài Gòn');
        $this->get(route('suppliers.index', ['q' => 'không có']))->assertOk()->assertDontSee('Công ty Linh Kiện Sài Gòn');
        $csv = $this->get(route('suppliers.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Linh Kiện', $csv);
        $this->getJson(route('search', ['q' => 'Linh Kiện']))->assertJsonFragment(['title' => 'Công ty Linh Kiện Sài Gòn']);

        $this->put(route('suppliers.update', $s), ['name' => 'Linh Kiện SG', 'status' => 'inactive', 'website' => 'javascript:alert(1)'])->assertSessionHasErrors('website');
        $this->put(route('suppliers.update', $s), ['name' => 'Linh Kiện SG', 'status' => 'inactive'])->assertRedirect(route('suppliers.show', $s));
        $this->delete(route('suppliers.destroy', $s))->assertRedirect(route('suppliers.index'));
        $this->assertSoftDeleted($s);
        $this->post(route('trash.restore', ['suppliers', $s->id]))->assertSessionHas('success');
        $this->assertNotSoftDeleted($s);
    }

    public function test_supplier_permissions(): void
    {
        $sale = $this->userWithRole('Sales Employee');            // không có quyền nhà cung cấp
        $this->actingAs($sale)->get(route('suppliers.index'))->assertForbidden();
        $this->assertNotContains('suppliers.index', \App\Support\Menu::flatRoutes($sale));

        $manager = $this->userWithRole('Sales Manager');          // chỉ xem
        $this->actingAs($manager)->get(route('suppliers.index'))->assertOk();
        $this->get(route('suppliers.create'))->assertForbidden();

        $inventory = $this->userWithRole('Inventory Manager');    // toàn quyền
        $this->actingAs($inventory)->post(route('suppliers.store'), ['name' => 'NCC Kho', 'status' => 'active'])->assertRedirect();
    }

    public function test_suppliers_are_isolated_per_organization(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $theirs = app(CurrentOrganization::class)->run($orgB->id, fn () => Supplier::create(['name' => 'NCC của B', 'status' => 'active']));

        $this->actingAs($owner)->get(route('suppliers.index'))->assertOk()->assertDontSee('NCC của B');
        $this->get(route('suppliers.show', $theirs->id))->assertNotFound();
        // Không gắn được sản phẩm vào nhà cung cấp của tổ chức khác
        $this->post(route('products.store'), ['sku' => 'X-1', 'name' => 'SP', 'type' => 'product', 'price' => 1000, 'unit' => 'cái', 'status' => 'active', 'supplier_id' => $theirs->id])
            ->assertSessionHasErrors('supplier_id');
    }

    public function test_product_type_purchase_price_and_margin_visibility(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['name' => 'NCC Phần Mềm', 'status' => 'active']);
        $this->actingAs($admin)->post(route('products.store'), [
            'sku' => 'SV-01', 'name' => 'Gói tư vấn', 'type' => 'service', 'price' => 2000000, 'purchase_price' => 1500000,
            'unit' => 'giờ', 'status' => 'active', 'supplier_id' => $supplier->id,
        ])->assertRedirect();
        $p = Product::where('sku', 'SV-01')->firstOrFail();
        $this->assertSame(['service', '1500000.00', $supplier->id], [$p->type, $p->purchase_price, $p->supplier_id]);
        $this->assertSame(25.0, $p->margin_percent);   // (2tr − 1,5tr) ÷ 2tr

        // Sửa mà không gửi "loại" (form cũ / tích hợp) → giữ nguyên dịch vụ; tạo mới không gửi → sản phẩm
        $this->put(route('products.update', $p), ['sku' => 'SV-01', 'name' => 'Gói tư vấn', 'price' => 2000000, 'unit' => 'giờ', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('service', $p->fresh()->type);
        $this->post(route('products.store'), ['sku' => 'OLD-1', 'name' => 'Kiểu cũ', 'price' => 1, 'unit' => 'cái', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('product', Product::where('sku', 'OLD-1')->value('type'));

        $this->get(route('products.show', $p))->assertSee(__('Giá vốn'))->assertSee('25,0%');
        $this->get(route('products.index', ['type' => 'service']))->assertSee('Gói tư vấn');
        $this->get(route('products.index', ['type' => 'product']))->assertDontSee('Gói tư vấn');
        $this->get(route('suppliers.show', $supplier))->assertSee('Gói tư vấn');

        // Nhân viên bán hàng xem sản phẩm nhưng KHÔNG thấy giá vốn / lợi nhuận
        $sale = $this->userWithRole('Sales Employee');
        $this->actingAs($sale)->get(route('products.show', $p))->assertOk()->assertDontSee(__('Giá vốn'))->assertDontSee('1.500.000');
        $this->post(route('products.store'), ['sku' => 'Y', 'name' => 'Y', 'type' => 'product', 'price' => 1, 'unit' => 'cái', 'status' => 'active'])->assertForbidden();
        $this->post(route('products.store'), ['sku' => 'Z', 'name' => 'Z', 'type' => 'bogus', 'price' => 1, 'unit' => 'cái', 'status' => 'active']);
        $this->assertFalse(Product::where('sku', 'Z')->exists());
    }

    public function test_document_math_uses_exact_decimals(): void
    {
        $r = app(SalesDocumentService::class)->calculate([
            ['description' => 'A', 'quantity' => '3', 'unit_price' => '0.1', 'discount_percent' => 0],
            ['description' => 'B', 'quantity' => '1.5', 'unit_price' => '33333.33', 'discount_percent' => '12.5'],
            ['description' => 'Bỏ qua', 'quantity' => '0', 'unit_price' => '999'],
            ['description' => 'Chữ', 'quantity' => 'abc', 'unit_price' => '1'],
        ], 10, 8);

        $this->assertCount(2, $r['items']);
        $this->assertSame(0.3, $r['items'][0]['line_total']);         // 3 × 0,1 = 0,30 (không 0,30000000000000004)
        $this->assertSame(43750.0, $r['items'][1]['line_total']);     // 1,5 × 33.333,33 × 87,5% = 43.749,995625 → làm tròn nửa lên = 43.750,00
        $this->assertSame(43750.3, $r['subtotal']);
        $this->assertSame(3499.22, $r['tax_amount']);                  // (43.750,30 − 10) × 8% = 3.499,224
        $this->assertSame(47239.52, $r['total']);                      // 43.750,30 − 10 + 3.499,22
    }
}
