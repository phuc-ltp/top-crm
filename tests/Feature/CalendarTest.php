<?php

namespace Tests\Feature;

use App\Models\Interaction;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskReminderService;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Bước 4.4: lịch hẹn có giờ kết thúc, địa điểm, người tham dự; cảnh báo trùng lịch; lịch chung; nhắc lịch; tệp .ics. */
class CalendarTest extends TestCase
{
    private function plan(User $actor, int $customerId, array $data)
    {
        return $this->actingAs($actor)->post(route('interactions.store', ['customer', $customerId]), $data + ['mode' => 'plan', 'type' => 'meeting']);
    }

    public function test_meeting_with_attendees_location_and_ics(): void
    {
        $sale = $this->userWithRole('Sales Employee');
        $mate = $this->userWithRole('Sales Employee', ['name' => 'Đồng nghiệp A']);
        $customer = $this->customer(['assigned_to' => $sale->id]);
        $start = now()->addDay()->setTime(9, 0);

        $this->plan($sale, $customer->id, ['subject' => 'Demo sản phẩm; bước 1', 'scheduled_at' => $start->format('Y-m-d H:i'),
            'ends_at' => $start->copy()->addHour()->format('Y-m-d H:i'), 'location' => 'Phòng họp 2, Tầng 3', 'attendees' => [$mate->id]])
            ->assertSessionHas('success')->assertSessionMissing('warning');
        $a = Interaction::firstOrFail();
        $this->assertSame([$mate->id], $a->attendees()->pluck('users.id')->all());
        $this->assertSame('task_assigned', $mate->notifications()->first()->data['kind']);

        // Người tham dự thấy lịch hẹn trên lịch chung và tải được .ics
        $this->actingAs($mate)->get(route('calendar.index', ['view' => 'week', 'date' => $start->toDateString()]))->assertOk()->assertSee('Demo sản phẩm');
        $ics = $this->get(route('calendar.ics', $a))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        $this->assertStringContainsString('DTSTART:'.$start->copy()->utc()->format('Ymd\THis\Z'), $ics);
        $this->assertStringContainsString('SUMMARY:Demo sản phẩm\; bước 1', $ics);
        $this->assertStringContainsString('LOCATION:Phòng họp 2\, Tầng 3', $ics);

        // Người ngoài cuộc không tải được
        $other = $this->userWithRole('Sales Employee');
        $this->actingAs($other)->get(route('calendar.ics', $a))->assertNotFound();
        $this->get(route('calendar.index', ['view' => 'week', 'date' => $start->toDateString()]))->assertDontSee('Demo sản phẩm');
    }

    public function test_conflict_warning_for_overlapping_meetings_of_the_same_person(): void
    {
        $sale = $this->userWithRole('Sales Employee', ['name' => 'Nhân viên Trùng']);
        $customer = $this->customer(['assigned_to' => $sale->id]);
        $at = now()->addDays(2)->setTime(14, 0);
        $this->plan($sale, $customer->id, ['subject' => 'Họp 1', 'scheduled_at' => $at->format('Y-m-d H:i'), 'ends_at' => $at->copy()->addHour()->format('Y-m-d H:i')]);

        $this->plan($sale, $customer->id, ['subject' => 'Họp 2', 'scheduled_at' => $at->copy()->addMinutes(30)->format('Y-m-d H:i')])
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'Nhân viên Trùng') && str_contains($w, 'Họp 1'));
        $this->plan($sale, $customer->id, ['subject' => 'Họp 3', 'scheduled_at' => $at->copy()->addHours(2)->format('Y-m-d H:i')])
            ->assertSessionMissing('warning');
        $this->plan($sale, $customer->id, ['subject' => 'Sai giờ', 'scheduled_at' => $at->format('Y-m-d H:i'), 'ends_at' => $at->copy()->subHour()->format('Y-m-d H:i')])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_calendar_merges_tasks_and_restricts_viewing_others(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $sale = $this->userWithRole('Sales Employee');
        Task::create(['title' => 'Nộp báo cáo tháng', 'priority' => 'low', 'status' => 'pending', 'assigned_to' => $sale->id, 'due_date' => now()->addDay()->setTime(10, 0)]);

        $this->actingAs($sale)->get(route('calendar.index'))->assertOk()->assertSee('Nộp báo cáo tháng');
        $this->actingAs($manager)->get(route('calendar.index'))->assertDontSee('Nộp báo cáo tháng');
        $this->get(route('calendar.index', ['user_id' => $sale->id]))->assertSee('Nộp báo cáo tháng');   // quản lý xem lịch nhân viên

        // Nhân viên không xem được lịch người khác (tham số bị bỏ qua → lịch của chính mình)
        $this->actingAs($sale)->get(route('calendar.index', ['user_id' => $manager->id]))->assertOk()->assertViewHas('whoId', $sale->id);
    }

    public function test_reminder_reaches_attendees_and_edit_syncs_attendees(): void
    {
        $sale = $this->userWithRole('Sales Employee');
        $mate = $this->userWithRole('Sales Employee');
        $mate2 = $this->userWithRole('Sales Employee');
        $customer = $this->customer(['assigned_to' => $sale->id]);
        $this->plan($sale, $customer->id, ['subject' => 'Gọi khách', 'scheduled_at' => now()->addMinutes(40)->format('Y-m-d H:i'), 'attendees' => [$mate->id]]);
        $a = Interaction::firstOrFail();
        $mate->notifications()->delete();

        app(TaskReminderService::class)->run();
        $this->assertSame(1, $mate->notifications()->count());
        $this->assertSame(1, $sale->notifications()->count());

        $this->actingAs($sale)->put(route('interactions.update', $a), ['type' => 'meeting', 'subject' => 'Gọi khách', 'scheduled_at' => now()->addMinutes(40)->format('Y-m-d H:i'),
            'assigned_to' => $sale->id, 'attendees_present' => 1, 'attendees' => [$mate2->id]])->assertSessionHas('success');
        $this->assertSame([$mate2->id], $a->attendees()->pluck('users.id')->all());
        $this->assertSame(1, $mate2->notifications()->count());
    }

    public function test_attendees_must_belong_to_the_organization(): void
    {
        $sale = $this->userWithRole('Sales Employee');
        $customer = $this->customer(['assigned_to' => $sale->id]);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $outsider = app(CurrentOrganization::class)->run($orgB->id, fn () => User::factory()->create(['status' => 'active']));

        $this->plan($sale, $customer->id, ['subject' => 'X', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'attendees' => [$outsider->id]])
            ->assertSessionHasErrors('attendees.0');
        $this->assertSame(0, Interaction::count());
    }
}
