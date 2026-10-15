<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Services\CustomerService;
use App\Services\LeadService;
use App\Services\SalesDocumentService;
use App\Support\PermissionCatalog;
use Tests\TestCase;

class BusinessLogicTest extends TestCase
{
    public function test_document_calculation(): void
    {
        $calc = app(SalesDocumentService::class)->calculate([
            ['description' => 'A', 'quantity' => 3, 'unit_price' => 100000, 'discount_percent' => 0],
            ['description' => 'B', 'quantity' => 1, 'unit_price' => 200000, 'discount_percent' => 50],
            ['description' => 'Bỏ qua vì SL = 0', 'quantity' => 0, 'unit_price' => 999],
        ], 50000, 8);

        $this->assertCount(2, $calc['items']);
        $this->assertEquals(400000, $calc['subtotal']);
        $this->assertEquals(50000, $calc['discount_amount']);
        $this->assertEquals(28000, $calc['tax_amount']);
        $this->assertEquals(378000, $calc['total']);
    }

    public function test_document_calculation_clamps_bad_input(): void
    {
        $calc = app(SalesDocumentService::class)->calculate([
            ['description' => 'X', 'quantity' => 2, 'unit_price' => -500, 'discount_percent' => 150],
            ['description' => 'Y', 'quantity' => 1, 'unit_price' => 1000],
        ], 999999, 200);

        $this->assertEquals(0, $calc['items'][0]['line_total'], 'Giá âm và giảm giá > 100% bị chặn');
        $this->assertEquals(1000, $calc['subtotal']);
        $this->assertEquals(1000, $calc['discount_amount'], 'Chiết khấu không vượt tạm tính');
        $this->assertEquals(100, $calc['tax_rate']);
        $this->assertEquals(0, $calc['total']);
    }

    public function test_phone_normalization(): void
    {
        $this->assertSame('0901234567', CustomerService::normalizePhone('+84 901 234 567'));
        $this->assertSame('0901234567', CustomerService::normalizePhone('(090) 123-4567'));
        $this->assertNull(CustomerService::normalizePhone(''));
    }

    public function test_lead_scoring_rules(): void
    {
        $service = app(LeadService::class);
        $this->assertSame(0, $service->score(new Lead(['status' => 'new'])));

        $referral = LeadSource::where('name', 'Giới thiệu')->first();
        $lead = new Lead(['email' => 'a@b.c', 'phone' => '1', 'company' => 'X', 'estimated_value' => 200000000, 'status' => 'proposal', 'lead_source_id' => $referral->id]);
        $this->assertSame(100, $service->score($lead), 'Điểm tối đa 100');

        $this->assertSame(10 + 15 + 10, $service->score(new Lead(['email' => 'a@b.c', 'estimated_value' => 50000000, 'status' => 'contacted'])));
    }

    public function test_permission_catalog_and_role_matrix(): void
    {
        $all = PermissionCatalog::all();
        $this->assertContains('customers.view_all', $all);
        $this->assertSame(count($all), count(array_unique($all)));
        $this->assertSame($all, PermissionCatalog::defaultsFor('Administrator'));
        $this->assertNotContains('customers.create', PermissionCatalog::defaultsFor('Viewer'));
        $this->assertNotContains('customers.view_all', PermissionCatalog::defaultsFor('Sales Staff'));
        foreach (PermissionCatalog::ROLES as $role) {
            foreach (PermissionCatalog::defaultsFor($role) as $p) {
                $this->assertContains($p, $all, "$role có quyền không tồn tại: $p");
            }
        }
    }

    public function test_money_and_date_helpers(): void
    {
        $this->assertSame('1.250.000 ₫', money(1250000));
        $this->assertSame('295,4 tr ₫', money_short(295432500));
        $this->assertSame('1,5 tỷ ₫', money_short(1542000000));
        $this->assertSame('2 tr ₫', money_short(2000000));
        $this->assertSame('850.000 ₫', money_short(850000));
        $this->assertSame('31/12/2026', vn_date('2026-12-31'));
        $this->assertSame('—', vn_date(null));
        $this->assertStringContainsString('badge-soft-success', badge(['ok' => ['Tốt', 'success']], 'ok'));
        $this->assertStringNotContainsString('<script>', badge(['x' => ['<script>', 'danger']], 'x'));
    }

    public function test_code_generation(): void
    {
        $c = $this->customer();
        $this->assertSame('KH'.str_pad((string) $c->id, 5, '0', STR_PAD_LEFT), $c->fresh()->code);
    }
}
