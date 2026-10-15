<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Organization;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\OrganizationProvisioner;
use Tests\TestCase;

/** Bước 2.2: hoạt động (ghi nhận / lên lịch / hoàn thành / nhắc) và dòng thời gian. */
class ActivityTest extends TestCase
{
    private function sales(string $email = 'sale@example.com'): User
    {
        return $this->userWithRole('Sales Employee', ['email' => $email]);
    }

    private function planFor(Customer $c, User $creator, array $data = []): Interaction
    {
        return $c->interactions()->create($data + [
            'type' => 'call', 'status' => 'planned', 'subject' => 'Gọi lại', 'scheduled_at' => now()->addDay(),
            'user_id' => $creator->id, 'assigned_to' => $creator->id,
        ]);
    }

    public function test_log_activity_appears_in_timeline(): void
    {
        $sale = $this->sales();
        $c = $this->customer(['assigned_to' => $sale->id]);

        $this->actingAs($sale)->post(route('interactions.store', ['customer', $c->id]), [
            'mode' => 'log', 'type' => 'call', 'subject' => 'Tư vấn gói', 'content' => 'Khách quan tâm gói Pro',
        ])->assertSessionHas('success');

        $a = Interaction::first();
        $this->assertSame('done', $a->status);
        $this->assertNotNull($a->completed_at);
        $this->actingAs($sale)->get(route('customers.show', $c))->assertOk()
            ->assertSee('Tư vấn gói')->assertSee('Khách quan tâm gói Pro')->assertSee(__('Dòng thời gian'));
    }

    public function test_plan_activity_for_colleague_notifies_them(): void
    {
        $owner = $this->admin();
        $sale = $this->sales();
        $c = $this->customer(['assigned_to' => $sale->id]);

        $this->actingAs($owner)->post(route('interactions.store', ['customer', $c->id]), [
            'mode' => 'plan', 'type' => 'meeting', 'subject' => 'Hẹn demo', 'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'), 'assigned_to' => $sale->id,
        ])->assertSessionHas('success');

        $a = Interaction::first();
        $this->assertSame('planned', $a->status);
        $this->assertSame($sale->id, $a->assigned_to);
        $this->assertStringContainsString('Hẹn demo', $sale->notifications()->first()->data['message']);

        // Xuất hiện trong "Hoạt động của tôi" của người được giao và mục Sắp tới
        $this->actingAs($sale)->get(route('activities.index'))->assertOk()->assertSee('Hẹn demo');
        $this->actingAs($sale)->get(route('customers.show', $c))->assertSee(__('Sắp tới (:count)', ['count' => 1]));
    }

    public function test_plan_validation_and_member_check(): void
    {
        $owner = $this->admin();
        $c = $this->customer();
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        $outsider = app(CurrentOrganization::class)->run($orgB->id, fn () => User::factory()->create(['status' => 'active']));
        $outsider->organizations()->detach(Organization::DEFAULT_ID);

        $this->actingAs($owner)->post(route('interactions.store', ['customer', $c->id]), ['mode' => 'plan', 'type' => 'call'])
            ->assertSessionHasErrors('scheduled_at');
        $this->actingAs($owner)->post(route('interactions.store', ['customer', $c->id]), [
            'mode' => 'plan', 'type' => 'call', 'scheduled_at' => now()->addDay()->toDateTimeString(), 'assigned_to' => $outsider->id,
        ])->assertSessionHasErrors('assigned_to');
        $this->assertSame(0, Interaction::count());
    }

    public function test_only_permitted_users_can_add_activities(): void
    {
        $sale = $this->sales();
        $other = $this->sales('other@example.com');
        $c = $this->customer(['assigned_to' => $sale->id]);

        $this->actingAs($other)->post(route('interactions.store', ['customer', $c->id]), ['mode' => 'log', 'type' => 'note', 'content' => 'x'])->assertForbidden();
        $this->assertSame(0, Interaction::count());
    }

    public function test_complete_rules(): void
    {
        $owner = $this->admin();
        $sale = $this->sales();
        $stranger = $this->sales('stranger@example.com');
        $c = $this->customer(['assigned_to' => $owner->id]);
        $a = $this->planFor($c, $owner, ['assigned_to' => $sale->id]);

        $this->actingAs($stranger)->patch(route('interactions.complete', $a), ['outcome' => 'x'])->assertForbidden();
        $this->actingAs($sale)->patch(route('interactions.complete', $a), ['outcome' => 'Khách đồng ý demo'])->assertSessionHas('success');
        $a->refresh();
        $this->assertSame('done', $a->status);
        $this->assertSame('Khách đồng ý demo', $a->outcome);
        $this->assertNotNull($a->completed_at);
        $this->actingAs($sale)->patch(route('interactions.complete', $a))->assertStatus(422);

        $this->actingAs($owner)->get(route('customers.show', $c))->assertSee('Khách đồng ý demo');
    }

    public function test_edit_resets_reminder_and_is_limited_to_creator(): void
    {
        $owner = $this->admin();
        $sale = $this->sales();
        $c = $this->customer(['assigned_to' => $sale->id]);
        $a = $this->planFor($c, $sale);
        $a->forceFill(['reminded_at' => now()])->save();

        $other = $this->sales('other@example.com');
        $this->actingAs($other)->put(route('interactions.update', $a), ['type' => 'call', 'scheduled_at' => now()->addDays(3)->toDateTimeString()])->assertForbidden();

        $this->actingAs($sale)->put(route('interactions.update', $a), [
            'type' => 'meeting', 'subject' => 'Hẹn lại', 'scheduled_at' => now()->addDays(3)->toDateTimeString(), 'assigned_to' => $sale->id,
        ])->assertSessionHas('success');
        $a->refresh();
        $this->assertSame('meeting', $a->type);
        $this->assertNull($a->reminded_at);

        $this->actingAs($owner)->delete(route('interactions.destroy', $a))->assertSessionHas('success');   // chủ sở hữu xóa được
        $this->assertNull(Interaction::find($a->id));
    }

    public function test_activity_list_respects_visibility(): void
    {
        $owner = $this->admin();
        $sale = $this->sales();
        $mine = $this->customer(['assigned_to' => $sale->id, 'name' => 'Khách của Sale']);
        $theirs = $this->customer(['assigned_to' => $owner->id, 'name' => 'Khách của Chủ']);
        $this->planFor($mine, $owner, ['subject' => 'Việc trên khách của Sale', 'assigned_to' => $owner->id]);
        $this->planFor($theirs, $owner, ['subject' => 'Việc BÍ MẬT của chủ', 'assigned_to' => $owner->id]);

        $page = $this->actingAs($sale)->get(route('activities.index', ['view' => 'all', 'status' => 'planned']))->assertOk();
        $page->assertSee('Việc trên khách của Sale')->assertDontSee('Việc BÍ MẬT của chủ');
        // "Của tôi": chỉ việc được giao cho mình
        $this->actingAs($sale)->get(route('activities.index'))->assertDontSee('Việc trên khách của Sale');
        $this->actingAs($owner)->get(route('activities.index'))->assertSee('Việc BÍ MẬT của chủ')->assertSee('Việc trên khách của Sale');
    }

    public function test_activities_of_other_organization_are_unreachable(): void
    {
        $owner = $this->userWithRole(PermissionCatalog::OWNER, ['email' => 'a@example.com']);
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        $b = app(CurrentOrganization::class)->run($orgB->id, function () {
            $userB = User::factory()->create(['status' => 'active']);
            $c = Customer::create(['name' => 'Khách B', 'type' => 'individual', 'status' => 'active']);

            return $c->interactions()->create(['type' => 'call', 'status' => 'planned', 'subject' => 'Việc tổ chức B', 'scheduled_at' => now()->addHour(), 'user_id' => $userB->id]);
        });

        $this->actingAs($owner)->patch(route('interactions.complete', $b->id))->assertNotFound();
        $this->actingAs($owner)->delete(route('interactions.destroy', $b->id))->assertNotFound();
        $this->actingAs($owner)->get(route('activities.index', ['view' => 'all']))->assertDontSee('Việc tổ chức B');
    }

    public function test_reminder_is_sent_once_before_scheduled_time(): void
    {
        $sale = $this->sales();
        $c = $this->customer(['assigned_to' => $sale->id, 'name' => 'Khách Nhắc']);
        $soon = $this->planFor($c, $sale, ['subject' => 'Gọi chốt đơn', 'scheduled_at' => now()->addMinutes(30)]);
        $this->planFor($c, $sale, ['subject' => 'Việc tuần sau', 'scheduled_at' => now()->addWeek()]);

        $this->artisan('crm:task-reminders')->assertSuccessful();
        $this->artisan('crm:task-reminders')->assertSuccessful();

        $messages = $sale->notifications()->get()->pluck('data.message');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Gọi chốt đơn', $messages->first());
        $this->assertNotNull($soon->fresh()->reminded_at);
    }

    public function test_timeline_hides_records_the_viewer_cannot_see(): void
    {
        $owner = $this->admin();
        $marketing = $this->userWithRole('Marketing Employee', ['email' => 'mk@example.com']);   // xem được mọi khách hàng, không xem được ticket
        $c = $this->customer(['name' => 'Khách Có Ticket']);
        SupportTicket::create(['customer_id' => $c->id, 'subject' => 'Lỗi đăng nhập BÍ MẬT', 'description' => 'x', 'priority' => 'high', 'status' => 'open']);

        $this->actingAs($owner)->get(route('customers.show', $c))->assertSee('Lỗi đăng nhập BÍ MẬT');
        $this->actingAs($marketing)->get(route('customers.show', $c))->assertOk()->assertDontSee('Lỗi đăng nhập BÍ MẬT');
    }

    public function test_activity_pages_render_for_every_role(): void
    {
        foreach (PermissionCatalog::ORG_ROLES as $i => $role) {
            $user = $this->userWithRole($role, ['email' => "r$i@example.com"]);
            $this->actingAs($user)->get(route('activities.index'))->assertOk();
        }
    }
}
