<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Organization;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Bước 4.3: SLA ticket (hạn, tạm dừng khi chờ khách, phản hồi đầu, leo thang), danh mục, lịch sử, hài lòng, phân tích. */
class TicketSlaTest extends TestCase
{
    private function ticket(array $attributes = []): SupportTicket
    {
        return SupportTicket::create($attributes + ['customer_id' => $this->customer()->id, 'subject' => 'Lỗi đăng nhập', 'description' => 'x',
            'priority' => 'high', 'status' => 'open']);
    }

    public function test_due_dates_follow_priority_policy_and_org_settings(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $t = $this->ticket();
        $this->assertEquals(now()->addHours(4), $t->first_response_due_at);
        $this->assertEquals(now()->addHours(24), $t->resolution_due_at);
        $this->assertSame(['open'], $t->statusHistories()->pluck('to_status')->all());

        // Tổ chức chỉnh SLA cho mức "cao": 2 giờ / 12 giờ
        $owner = $this->admin();
        $this->actingAs($owner)->put(route('settings.update'), ['company_name' => 'Công ty', 'brand_color' => '#D9823B', 'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND', 'date_format' => 'd/m/Y', 'default_tax_rate' => 10,
            'sla' => ['urgent' => ['response' => 1, 'resolve' => 8], 'high' => ['response' => 2, 'resolve' => 12], 'medium' => ['response' => 8, 'resolve' => 48], 'low' => ['response' => 24, 'resolve' => 96]]])
            ->assertSessionHas('success');
        $this->assertEquals(now()->addHours(12), $this->ticket()->resolution_due_at);
        $this->put(route('settings.update'), ['company_name' => 'Công ty', 'brand_color' => '#D9823B', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND',
            'date_format' => 'd/m/Y', 'default_tax_rate' => 10, 'sla' => ['high' => ['response' => 10, 'resolve' => 5]]])->assertSessionHasErrors('sla.high.resolve');

        // Đổi mức ưu tiên → hạn chưa đạt được tính lại theo mức mới
        $t->update(['priority' => 'low']);
        $this->assertEquals($t->created_at->copy()->addHours(96), $t->fresh()->resolution_due_at);
    }

    public function test_waiting_for_customer_pauses_the_clock_and_shifts_due_dates(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $t = $this->ticket(['priority' => 'urgent']);   // 1 giờ / 8 giờ
        $due = $t->resolution_due_at->copy();

        $this->travel(30)->minutes();
        $t->update(['status' => 'waiting']);
        $this->travel(3)->hours();
        $this->assertSame('paused', $t->fresh()->sla_state['resolution']);
        $t->fresh()->update(['status' => 'in_progress']);

        $t->refresh();
        $this->assertSame(180, $t->paused_minutes);
        $this->assertEquals($due->copy()->addMinutes(180), $t->resolution_due_at);
        $this->assertSame(['in_progress', 'waiting', 'open'], $t->statusHistories()->pluck('to_status')->all());
    }

    public function test_first_response_counts_only_customer_facing_replies(): void
    {
        $agent = $this->userWithRole('Support Agent');
        $t = $this->ticket(['assigned_to' => $agent->id]);
        $this->actingAs($agent)->post(route('tickets.reply', $t), ['body' => 'Ghi chú nội bộ', 'is_internal' => 1]);
        $this->assertNull($t->fresh()->first_responded_at);

        $this->travel(5)->hours();   // hạn phản hồi đầu mức cao = 4 giờ
        $this->post(route('tickets.reply', $t), ['body' => 'Chào anh, bên em đang kiểm tra']);
        $t->refresh();
        $this->assertNotNull($t->first_responded_at);
        $this->assertSame('breached', $t->sla_state['response']);
        $this->assertSame('in_progress', $t->status);
        $this->get(route('tickets.show', $t))->assertOk()->assertSee(__('Vi phạm'));
    }

    public function test_escalation_warns_then_escalates_once_to_the_right_people(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        $agent = $this->userWithRole('Support Agent');
        $boss = $this->userWithRole('Sales Manager');
        $csm = $this->userWithRole('Customer Support Manager');
        EmployeeProfile::create(['user_id' => $agent->id, 'manager_id' => $boss->id, 'employment_status' => 'active']);
        $t = $this->ticket(['assigned_to' => $agent->id]);   // phản hồi 4 giờ, xử lý 24 giờ

        $this->travel(190)->minutes();   // còn 50 phút tới hạn phản hồi đầu
        $this->artisan('crm:ticket-sla')->expectsOutputToContain('Cảnh báo sắp trễ: 1')->assertSuccessful();
        $this->assertSame(['ticket_sla_warning'], $agent->notifications()->pluck('data')->map(fn ($d) => $d['kind'])->all());
        $this->artisan('crm:ticket-sla')->expectsOutputToContain('Cảnh báo sắp trễ: 0');

        $this->travel(1)->hours();   // quá hạn phản hồi đầu
        $this->artisan('crm:ticket-sla')->expectsOutputToContain('Leo thang: 1');
        $this->artisan('crm:ticket-sla')->expectsOutputToContain('Leo thang: 0');
        $this->assertSame(1, $boss->notifications()->count());
        $this->assertSame(1, $csm->notifications()->count());
        $this->assertSame('ticket_escalated', $csm->notifications()->first()->data['kind']);
        $this->assertSame(2, $t->fresh()->escalation_level);
        $this->actingAs($csm)->get(route('tickets.index', ['sla' => 'breached']))->assertOk()->assertSee($t->code);
    }

    public function test_satisfaction_categories_and_analytics(): void
    {
        $csm = $this->userWithRole('Customer Support Manager');
        $this->actingAs($csm)->post(route('ticket-categories.store'), ['name' => 'Kỹ thuật', 'is_active' => 1])->assertSessionHas('success');
        $cat = TicketCategory::where('name', 'Kỹ thuật')->firstOrFail();
        $this->post(route('ticket-categories.store'), ['name' => 'Kỹ thuật'])->assertSessionHasErrors('name');

        $t = $this->ticket(['category_id' => $cat->id, 'assigned_to' => $csm->id]);
        $this->post(route('tickets.satisfaction', $t), ['satisfaction_rating' => 5])->assertStatus(422);   // chưa giải quyết
        $this->post(route('tickets.reply', $t), ['body' => 'Đã xử lý xong', 'status' => 'resolved']);
        $this->post(route('tickets.satisfaction', $t), ['satisfaction_rating' => 4, 'satisfaction_comment' => 'Nhanh'])->assertSessionHas('success');
        $this->post(route('tickets.satisfaction', $t), ['satisfaction_rating' => 9])->assertSessionHasErrors('satisfaction_rating');

        $page = $this->get(route('tickets.analytics'))->assertOk()->assertSee('Kỹ thuật');
        $s = $page->viewData('summary');
        $this->assertSame([1, 1, 100, 100, 4.0], [$s['created'], $s['resolved'], $s['response_rate'], $s['resolution_rate'], $s['csat']]);

        $agent = $this->userWithRole('Employee');
        $this->actingAs($agent)->get(route('ticket-categories.index'))->assertForbidden();

        // Xóa danh mục → ticket về "Chưa phân loại"
        $this->actingAs($csm)->delete(route('ticket-categories.destroy', $cat))->assertSessionHas('success');
        $this->assertNull($t->fresh()->category_id);
    }

    public function test_categories_are_isolated_between_organizations(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $catB = app(CurrentOrganization::class)->run($orgB->id, fn () => TicketCategory::create(['name' => 'Danh mục bí mật B']));

        $this->actingAs($owner)->get(route('ticket-categories.index'))->assertOk()->assertDontSee('Danh mục bí mật B');
        $this->put(route('ticket-categories.update', $catB), ['name' => 'Bị sửa'])->assertNotFound();
        $this->post(route('tickets.store'), ['customer_id' => $this->customer()->id, 'subject' => 'X', 'description' => 'x', 'priority' => 'low',
            'status' => 'open', 'category_id' => $catB->id])->assertSessionHasErrors('category_id');
    }
}
