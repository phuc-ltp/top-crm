<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationTemplate;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Services\QuotationApprovalService;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Bước 3.4 — duyệt báo giá chiết khấu vượt ngưỡng, mẫu báo giá, tự hết hạn. */
class QuotationWorkflowTest extends TestCase
{
    private Product $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->p = Product::create(['sku' => 'GOI-CRM', 'name' => 'Gói CRM', 'type' => 'service', 'price' => 10000000, 'unit' => 'gói', 'status' => 'active']);
    }

    private function quote($customer, float $discount = 0, string $status = 'sent', float $docDiscount = 0): array
    {
        return ['customer_id' => $customer->id, 'issue_date' => today()->toDateString(), 'valid_until' => today()->addDays(10)->toDateString(),
            'status' => $status, 'tax_rate' => 0, 'discount_amount' => $docDiscount,
            'items' => [['product_id' => $this->p->id, 'quantity' => 1, 'unit_price' => 10000000, 'discount_percent' => $discount]]];
    }

    public function test_over_threshold_requires_manager_approval_before_order(): void
    {
        Setting::put(['quotation_discount_threshold' => '10']);
        $sale = $this->userWithRole('Sales Employee');
        $manager = $this->userWithRole('Sales Manager');
        $c = $this->customer(['assigned_to' => $sale->id]);

        $this->actingAs($sale)->post(route('quotations.store'), $this->quote($c, 15))->assertSessionHas('warning');
        $q = Quotation::latest('id')->firstOrFail();
        $this->assertSame(['draft', 'pending'], [$q->status, $q->approval_status], 'Chờ duyệt: giữ ở Nháp');
        $this->assertSame(1, $manager->notifications()->count());

        $this->post(route('quotations.convert', $q))->assertSessionHasErrors('status');
        $this->assertSame(0, SalesOrder::count());
        $this->post(route('quotations.approve', $q))->assertForbidden();
        $this->put(route('quotations.update', $q), $this->quote($c, 20))->assertSessionHas('warning');   // vẫn vượt, vẫn chờ
        $this->assertSame('pending', $q->fresh()->approval_status);

        $this->actingAs($manager)->post(route('quotations.approve', $q))->assertSessionHas('success');
        $this->assertSame(['approved', $manager->id], [$q->fresh()->approval_status, $q->fresh()->approved_by]);
        $this->assertTrue($sale->notifications()->where('data->message', 'like', '%đã được duyệt%')->exists());
        $this->actingAs($sale)->post(route('quotations.convert', $q))->assertRedirect();
        $this->assertSame(1, SalesOrder::count());
    }

    public function test_reject_needs_reason_and_lowering_discount_clears_approval(): void
    {
        Setting::put(['quotation_discount_threshold' => '10']);
        $sale = $this->userWithRole('Sales Employee');
        $manager = $this->userWithRole('Sales Manager');
        $c = $this->customer(['assigned_to' => $sale->id]);
        $this->actingAs($sale)->post(route('quotations.store'), $this->quote($c, 0, 'draft', 2000000));   // chiết khấu chung 20%
        $q = Quotation::latest('id')->firstOrFail();
        $this->assertSame(20.0, QuotationApprovalService::discountPercent($q->load('items')));
        $this->assertSame('pending', $q->approval_status);

        $this->actingAs($manager)->post(route('quotations.reject', $q), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('quotations.reject', $q), ['note' => 'Giảm quá sâu'])->assertSessionHas('success');
        $this->assertSame('rejected', $q->fresh()->approval_status);
        $this->actingAs($sale)->post(route('quotations.convert', $q))->assertSessionHasErrors('status');

        $this->put(route('quotations.update', $q), $this->quote($c, 5, 'sent'))->assertSessionMissing('warning');
        $this->assertSame([null, 'sent'], [$q->fresh()->approval_status, $q->fresh()->status]);
    }

    public function test_approver_is_self_approved_and_threshold_off_means_no_approval(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $this->actingAs($manager)->post(route('quotations.store'), $this->quote($this->customer(), 50));
        $this->assertNull(Quotation::latest('id')->value('approval_status'), 'Tắt ngưỡng: không duyệt');

        Setting::put(['quotation_discount_threshold' => '10']);
        $this->post(route('quotations.store'), $this->quote($this->customer(), 50));
        $q = Quotation::latest('id')->firstOrFail();
        $this->assertSame(['approved', 'sent', $manager->id], [$q->approval_status, $q->status, $q->approved_by]);
    }

    public function test_templates_prefill_and_permissions(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $this->actingAs($manager)->post(route('quotations.store'), $this->quote($this->customer(), 0, 'draft') + ['notes' => 'Bảo hành 12 tháng']);
        $q = Quotation::latest('id')->firstOrFail();
        $this->post(route('quotation-templates.store'), ['quotation_id' => $q->id, 'name' => 'Gói chuẩn'])->assertSessionHasNoErrors();
        $this->post(route('quotation-templates.store'), ['quotation_id' => $q->id, 'name' => 'Gói chuẩn'])->assertSessionHasErrors('name');
        $tpl = QuotationTemplate::firstOrFail();
        $this->assertSame('Bảo hành 12 tháng', $tpl->notes);
        $this->assertCount(1, $tpl->items);

        $sale = $this->userWithRole('Sales Employee');
        $this->actingAs($sale)->get(route('quotation-templates.index'))->assertForbidden();
        $this->get(route('quotations.create'))->assertSee('Gói chuẩn');
        $this->get(route('quotations.create', ['template' => $tpl->id]))->assertOk()->assertSee('Bảo hành 12 tháng')->assertSee('value="'.$this->p->id.'" data-price', false);

        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $tplB = app(CurrentOrganization::class)->run($orgB->id, fn () => QuotationTemplate::create(['name' => 'Mẫu B', 'notes' => 'BÍ MẬT B', 'items' => [['description' => 'X', 'quantity' => 1]]]));
        $this->get(route('quotations.create', ['template' => $tplB->id]))->assertOk()->assertDontSee('BÍ MẬT B');
    }

    public function test_expired_quotations_are_marked_by_scheduler(): void
    {
        $sale = $this->userWithRole('Sales Employee');
        $make = fn (string $status, $until) => Quotation::create(['customer_id' => $this->customer()->id, 'issue_date' => today()->subDays(40), 'valid_until' => $until,
            'status' => $status, 'subtotal' => 1, 'total' => 1, 'assigned_to' => $sale->id]);
        $old = $make('sent', today()->subDay());
        $draft = $make('draft', today()->subDays(3));
        $accepted = $make('accepted', today()->subDay());
        $future = $make('sent', today()->addDay());

        $this->artisan('crm:sales-automation')->expectsOutputToContain('2')->assertSuccessful();
        $this->assertSame(['expired', 'expired', 'accepted', 'sent'], [$old->fresh()->status, $draft->fresh()->status, $accepted->fresh()->status, $future->fresh()->status]);
        $this->assertSame(2, $sale->notifications()->count());
    }
}
