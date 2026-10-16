<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LostReason;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\OrganizationProvisioner;
use Tests\TestCase;

/** Bước 2.4: giai đoạn lead cấu hình được, lý do thất bại, theo dõi, phát hiện trùng, form website, phân tích. */
class LeadManagementTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + ['name' => 'Lead Thử', 'status' => 'new', 'confirm_duplicate' => '1'];
    }

    private function enableForm(User $admin): string
    {
        $this->actingAs($admin)->put(route('settings.leads.form'), ['lead_form_enabled' => '1'])->assertRedirect();

        return Setting::get('lead_form_token');
    }

    // ---------------- Giai đoạn & lý do ----------------

    public function test_default_stages_exist_and_old_status_keys_keep_working(): void
    {
        $this->assertSame(['new', 'contacted', 'qualified', 'proposal', 'converted', 'lost'], LeadStage::orderBy('position')->pluck('key')->all());
        $this->assertSame('Đàm phán', Lead::statuses()['proposal'][0]);
        $admin = $this->admin();
        Lead::create(['name' => 'Lead cũ', 'status' => 'proposal']);
        $this->actingAs($admin)->get(route('leads.index'))->assertOk()->assertSee('Lead cũ')->assertSee('Đàm phán');
    }

    public function test_lost_requires_reason_and_reason_is_cleared_when_reopened(): void
    {
        $admin = $this->admin();
        $lead = Lead::create(['name' => 'Lead A', 'status' => 'contacted']);
        $reason = LostReason::first();

        $this->actingAs($admin)->put(route('leads.update', $lead), $this->payload(['name' => 'Lead A', 'status' => 'lost']))->assertSessionHasErrors('lost_reason_id');
        $this->actingAs($admin)->put(route('leads.update', $lead), $this->payload(['name' => 'Lead A', 'status' => 'lost', 'lost_reason_id' => $reason->id, 'lost_note' => 'Chê đắt']))->assertRedirect();
        $this->assertSame($reason->id, $lead->fresh()->lost_reason_id);
        $this->actingAs($admin)->get(route('leads.show', $lead))->assertSee($reason->name)->assertSee('Chê đắt');

        $this->actingAs($admin)->put(route('leads.update', $lead), $this->payload(['name' => 'Lead A', 'status' => 'contacted']))->assertRedirect();
        $this->assertNull($lead->fresh()->lost_reason_id);
        $this->assertNull($lead->fresh()->lost_note);
    }

    public function test_qualified_time_is_recorded_once(): void
    {
        $admin = $this->admin();
        $lead = Lead::create(['name' => 'Lead Q', 'status' => 'new']);
        $this->assertNull($lead->qualified_at);
        $this->actingAs($admin)->put(route('leads.update', $lead), $this->payload(['name' => 'Lead Q', 'status' => 'qualified']));
        $first = $lead->fresh()->qualified_at;
        $this->assertNotNull($first);
        $this->travel(1)->day();
        $this->actingAs($admin)->put(route('leads.update', $lead), $this->payload(['name' => 'Lead Q', 'status' => 'proposal']));
        $this->assertEquals($first->timestamp, $lead->fresh()->qualified_at->timestamp);
    }

    public function test_organization_manages_its_own_stages(): void
    {
        $admin = $this->admin();
        $sale = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $this->actingAs($sale)->get(route('settings.leads'))->assertForbidden();

        $this->actingAs($admin)->post(route('settings.leads.stages.store'), ['name' => 'Đã hẹn gặp', 'color' => 'purple'])->assertRedirect();
        $stage = LeadStage::where('name', 'Đã hẹn gặp')->firstOrFail();
        $this->assertSame('da_hen_gap', $stage->key);
        $this->assertLessThan(LeadStage::where('key', 'converted')->value('position'), $stage->position);   // đặt trước "Đã chuyển đổi"

        // Dùng được ngay trong lead
        $lead = Lead::create(['name' => 'Lead dùng giai đoạn mới', 'status' => 'new']);
        $this->actingAs($admin)->put(route('leads.update', $lead), $this->payload(['name' => $lead->name, 'status' => 'da_hen_gap']))->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('leads.update', $lead), $this->payload(['name' => $lead->name, 'status' => 'khong_ton_tai']))->assertSessionHasErrors('status');

        // Xóa giai đoạn → lead chuyển sang giai đoạn được chọn
        $this->actingAs($admin)->delete(route('settings.leads.stages.destroy', $stage), ['move_to' => 'contacted'])->assertRedirect();
        $this->assertSame('contacted', $lead->fresh()->status);
        $this->assertNull(LeadStage::find($stage->id));

        // Giai đoạn hệ thống không xóa được; đổi tên được
        $converted = LeadStage::where('key', 'converted')->first();
        $this->actingAs($admin)->delete(route('settings.leads.stages.destroy', $converted), ['move_to' => 'new'])->assertStatus(422);
        $this->actingAs($admin)->put(route('settings.leads.stages.update', $converted), ['name' => 'Thành khách hàng', 'color' => 'success'])->assertRedirect();
        $this->assertSame('Thành khách hàng', Lead::statuses()['converted'][0]);
    }

    public function test_stages_and_reasons_are_isolated_per_organization(): void
    {
        $owner = $this->userWithRole(PermissionCatalog::OWNER, ['email' => 'a@example.com']);
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        [$stageB, $reasonB] = app(CurrentOrganization::class)->run($orgB->id, function () {
            $s = LeadStage::where('key', 'contacted')->first();
            $s->update(['name' => 'Giai đoạn BÍ MẬT B']);

            return [$s, LostReason::first()];
        });
        $this->assertSame(6, app(CurrentOrganization::class)->run($orgB->id, fn () => LeadStage::count()));   // tổ chức mới có bộ mặc định

        $this->actingAs($owner)->get(route('settings.leads'))->assertDontSee('Giai đoạn BÍ MẬT B');
        $this->actingAs($owner)->put(route('settings.leads.stages.update', $stageB->id), ['name' => 'Hack', 'color' => 'danger'])->assertNotFound();
        $this->actingAs($owner)->delete(route('settings.leads.reasons.destroy', $reasonB->id))->assertNotFound();
        // Lý do của tổ chức B không dùng được ở tổ chức A
        $lead = Lead::create(['name' => 'Lead A', 'status' => 'new']);
        $this->actingAs($owner)->put(route('leads.update', $lead), $this->payload(['name' => 'Lead A', 'status' => 'lost', 'lost_reason_id' => $reasonB->id]))->assertSessionHasErrors('lost_reason_id');
    }

    // ---------------- Trùng lặp & theo dõi ----------------

    public function test_duplicate_detection_against_leads_and_customers(): void
    {
        $admin = $this->admin();
        $this->customer(['name' => 'Khách Có Sẵn', 'email' => 'trung@example.com']);
        Lead::create(['name' => 'Lead Có Sẵn', 'status' => 'new', 'phone' => '0909000111']);

        $this->actingAs($admin)->post(route('leads.store'), ['name' => 'Lead mới', 'status' => 'new', 'email' => 'TRUNG@example.com'])
            ->assertRedirect()->assertSessionHas('lead_duplicates');
        $this->actingAs($admin)->post(route('leads.store'), ['name' => 'Lead mới 2', 'status' => 'new', 'phone' => '+84 909 000 111'])
            ->assertSessionHas('lead_duplicates');
        $this->assertSame(1, Lead::count());

        $this->actingAs($admin)->post(route('leads.store'), ['name' => 'Lead mới', 'status' => 'new', 'email' => 'trung@example.com', 'confirm_duplicate' => '1'])->assertRedirect();
        $this->assertSame(2, Lead::count());
    }

    public function test_follow_up_creates_activity_and_filters_work(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('leads.store'), $this->payload(['name' => 'Lead Có Hẹn', 'follow_up_at' => now()->addDay()->format('Y-m-d H:i:s')]))->assertRedirect();
        $lead = Lead::where('name', 'Lead Có Hẹn')->firstOrFail();
        $activity = Interaction::where('interactable_type', 'lead')->where('interactable_id', $lead->id)->firstOrFail();
        $this->assertSame(['follow_up', 'planned'], [$activity->type, $activity->status]);

        Lead::create(['name' => 'Lead Không Hẹn', 'status' => 'contacted']);
        $overdue = Lead::create(['name' => 'Lead Trễ Hẹn', 'status' => 'contacted']);
        $overdue->interactions()->create(['type' => 'follow_up', 'status' => 'planned', 'scheduled_at' => now()->subDay(), 'user_id' => $admin->id]);

        $this->actingAs($admin)->get(route('leads.index', ['follow_up' => 'overdue']))->assertSee('Lead Trễ Hẹn')->assertDontSee('Lead Không Hẹn')->assertDontSee('Lead Có Hẹn');
        $this->actingAs($admin)->get(route('leads.index', ['follow_up' => 'none']))->assertSee('Lead Không Hẹn')->assertDontSee('Lead Trễ Hẹn');
        $this->actingAs($admin)->post(route('leads.store'), $this->payload(['name' => 'Hẹn quá khứ', 'follow_up_at' => now()->subHour()->format('Y-m-d H:i:s')]))->assertSessionHasErrors('follow_up_at');
    }

    // ---------------- Form website ----------------

    public function test_web_form_creates_lead_in_the_right_organization(): void
    {
        $admin = $this->admin();
        $owner = $this->userWithRole(PermissionCatalog::OWNER, ['email' => 'owner@example.com']);
        Organization::whereKey(Organization::DEFAULT_ID)->update(['owner_id' => $owner->id]);
        $this->get(route('lead-form.show', str_repeat('a', 40)))->assertNotFound();   // token không tồn tại

        $token = $this->enableForm($admin);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $token);
        $page = $this->get(route('lead-form.show', $token))->assertOk()->assertSee(Setting::get('company_name'));
        preg_match('/name="stamp" value="([^"]+)"/', $page->getContent(), $m);
        $stamp = html_entity_decode($m[1]);

        $this->travel(5)->seconds();
        $this->post(route('lead-form.submit', $token), ['stamp' => $stamp, 'name' => 'Khách Web', 'phone' => '0912 345 678', 'message' => 'Cần báo giá', 'consent' => '1'])
            ->assertRedirect(route('lead-form.show', $token));
        $lead = Lead::where('name', 'Khách Web')->firstOrFail();
        $this->assertSame(Organization::DEFAULT_ID, (int) $lead->organization_id);
        $this->assertSame('0912345678', $lead->phone);
        $this->assertSame($owner->id, $lead->assigned_to);              // mặc định: chủ sở hữu tổ chức
        $this->assertSame('Website', $lead->source?->name);
        $this->assertTrue($lead->marketing_consent);
        $this->assertStringContainsString('Cần báo giá', $lead->notes);
        $this->assertStringContainsString('Khách Web', $owner->notifications()->first()->data['message']);

        // Đồng ý marketing được chuyển sang khách hàng khi chuyển đổi
        $this->actingAs($admin)->post(route('leads.convert.store', $lead), ['mode' => 'new', 'customer_name' => 'Khách Web'])->assertRedirect();
        $customer = Customer::where('name', 'Khách Web')->firstOrFail();
        $this->assertTrue($customer->marketing_consent);
        $this->assertSame('Form website', $customer->marketing_consent_source);
    }

    public function test_web_form_spam_protection_and_validation(): void
    {
        $admin = $this->admin();
        $token = $this->enableForm($admin);
        $page = $this->get(route('lead-form.show', $token));
        preg_match('/name="stamp" value="([^"]+)"/', $page->getContent(), $m);
        $stamp = html_entity_decode($m[1]);

        // Gửi quá nhanh (robot) → báo thành công nhưng không tạo lead
        $this->post(route('lead-form.submit', $token), ['stamp' => $stamp, 'name' => 'Robot nhanh', 'email' => 'r@x.com'])->assertRedirect();
        $this->travel(5)->seconds();
        // Điền trường ẩn → không tạo lead
        $this->post(route('lead-form.submit', $token), ['stamp' => $stamp, 'name' => 'Robot honeypot', 'email' => 'r@x.com', 'website' => 'spam.com'])->assertRedirect();
        // Dấu thời gian giả → không tạo lead
        $this->post(route('lead-form.submit', $token), ['stamp' => 'gia-mao', 'name' => 'Robot giả', 'email' => 'r@x.com'])->assertRedirect();
        $this->assertSame(0, Lead::count());

        // Thiếu cả email lẫn điện thoại
        $this->post(route('lead-form.submit', $token), ['stamp' => $stamp, 'name' => 'Người thật'])->assertSessionHasErrors(['email', 'phone']);
        $this->assertSame(0, Lead::count());

        // Tạo đường dẫn mới → đường dẫn cũ ngừng hoạt động; tắt form → 404
        $this->actingAs($admin)->post(route('settings.leads.form.token'))->assertRedirect();
        $this->get(route('lead-form.show', $token))->assertNotFound();
        $newToken = Setting::get('lead_form_token');
        $this->get(route('lead-form.show', $newToken))->assertOk();
        $this->actingAs($admin)->put(route('settings.leads.form'), ['lead_form_enabled' => '0'])->assertRedirect();
        $this->get(route('lead-form.show', $newToken))->assertNotFound();
    }

    // ---------------- Phân tích ----------------

    public function test_analytics_respects_visibility(): void
    {
        $admin = $this->admin();
        $sale = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $src = LeadSource::first();
        Lead::create(['name' => 'L1', 'status' => 'converted', 'assigned_to' => $sale->id, 'lead_source_id' => $src->id, 'converted_at' => now()]);
        Lead::create(['name' => 'L2', 'status' => 'lost', 'assigned_to' => $sale->id, 'lost_reason_id' => LostReason::first()->id]);
        Lead::create(['name' => 'L3', 'status' => 'new', 'assigned_to' => $admin->id]);

        $page = $this->actingAs($admin)->get(route('leads.analytics', ['period' => 'this_month']))->assertOk();
        $this->assertSame(3, $page->viewData('kpi')['total']);
        $this->assertSame(33.3, $page->viewData('kpi')['conversion_rate']);
        $this->assertTrue($page->viewData('byOwner')->isNotEmpty());
        $this->assertSame(LostReason::first()->name, $page->viewData('byReason')->first()['label']);

        $own = $this->actingAs($sale)->get(route('leads.analytics', ['period' => 'this_month']))->assertOk();
        $this->assertSame(2, $own->viewData('kpi')['total']);          // chỉ lead của mình
        $this->assertTrue($own->viewData('byOwner')->isEmpty());       // không xem được bảng theo nhân viên
    }
}
