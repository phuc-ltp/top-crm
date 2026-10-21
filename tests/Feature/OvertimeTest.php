<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Organization;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\OvertimeService;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Bước 5.3: đề nghị tăng ca trong khung 18:00–21:00, đơn giá ≥ 150% lương giờ, duyệt bởi quản lý / người quản lý lương. */
class OvertimeTest extends TestCase
{
    private function submit(User $user, array $data = [])
    {
        return $this->actingAs($user)->post(route('overtime.store'), $data + [
            'work_date' => now()->toDateString(), 'start_time' => '18:00', 'end_time' => '21:00', 'level' => 'medium', 'reason' => 'Chạy báo cáo cuối tháng',
        ]);
    }

    public function test_window_end_after_start_and_overlap_are_validated(): void
    {
        $staff = $this->userWithRole('Employee');
        $this->submit($staff, ['start_time' => '17:30'])->assertSessionHasErrors('start_time');
        $this->submit($staff, ['end_time' => '21:30'])->assertSessionHasErrors('start_time');
        $this->submit($staff, ['start_time' => '20:00', 'end_time' => '19:00'])->assertSessionHasErrors('end_time');
        $this->submit($staff, ['work_date' => now()->subDays(40)->toDateString()])->assertSessionHasErrors('work_date');
        $this->submit($staff, ['level' => 'extreme'])->assertSessionHasErrors('level');
        $this->assertSame(0, OvertimeRequest::count());

        $this->submit($staff, ['start_time' => '18:00', 'end_time' => '19:30'])->assertSessionHasNoErrors();
        $this->assertSame('1.50', OvertimeRequest::first()->hours);
        $this->submit($staff, ['start_time' => '19:00', 'end_time' => '20:00'])->assertSessionHasErrors('start_time');   // chồng giờ
        $this->submit($staff, ['start_time' => '19:30', 'end_time' => '21:00'])->assertSessionHasNoErrors();             // nối tiếp: hợp lệ
        $this->assertSame(2, OvertimeRequest::count());
    }

    public function test_rate_is_never_below_150_percent_of_hourly_wage(): void
    {
        $service = app(OvertimeService::class);
        $staff = $this->userWithRole('Employee');                                   // 35.000 đ/giờ
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);        // 200.000 đ/giờ

        $this->assertSame('52500.00', $service->rateFor($staff, 'low'));             // 40.000 < 150% × 35.000
        $this->assertSame('70000.00', $service->rateFor($staff, 'medium'));
        $this->assertSame('100000.00', $service->rateFor($staff, 'high'));
        $this->assertSame('300000.00', $service->rateFor($owner, 'high'));          // 150% × 200.000
    }

    public function test_manager_approves_and_rate_is_locked_at_approval(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $staff = $this->userWithRole('Employee');
        EmployeeProfile::create(['user_id' => $staff->id, 'manager_id' => $manager->id, 'employment_status' => 'active']);

        $this->submit($staff, ['level' => 'low'])->assertRedirect(route('overtime.index'));
        $ot = OvertimeRequest::firstOrFail();
        $this->assertSame('pending', $ot->status);
        $this->assertSame(1, $manager->notifications()->where('data->kind', 'overtime_request')->count());

        $this->actingAs($staff)->post(route('overtime.approve', $ot))->assertForbidden();                 // không tự duyệt
        $this->actingAs($this->userWithRole('Employee'))->post(route('overtime.approve', $ot))->assertForbidden();
        $this->actingAs($manager)->get(route('overtime.index', ['tab' => 'approve']))->assertOk()->assertSee('Chạy báo cáo cuối tháng');
        $this->post(route('overtime.approve', $ot))->assertSessionHas('success');

        $ot->refresh();
        $this->assertSame(['approved', $manager->id, '52500.00', '157500'], [$ot->status, $ot->approver_id, $ot->rate, $ot->amount]);
        $this->assertSame(1, $staff->notifications()->where('data->kind', 'overtime_decision')->count());

        // Tăng lương sau khi duyệt không làm đổi đơn đã chốt
        $staff->employeeProfile()->update(['hourly_rate' => 150000]);
        $this->assertSame('52500.00', $ot->fresh()->rate);
        $this->post(route('overtime.approve', $ot))->assertStatus(422);                                   // đã xử lý
        $this->actingAs($staff)->get(route('overtime.index'))->assertOk()->assertSee(money('157500'));
    }

    public function test_reject_requires_note_and_owner_can_cancel_only_pending(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $staff = $this->userWithRole('Employee');
        $this->submit($staff);
        $ot = OvertimeRequest::firstOrFail();

        $this->actingAs($owner)->post(route('overtime.reject', $ot))->assertSessionHasErrors('decision_note');
        $this->post(route('overtime.reject', $ot), ['decision_note' => 'Không cần thiết'])->assertSessionHas('success');
        $this->assertSame(['rejected', null], [$ot->fresh()->status, $ot->fresh()->rate]);
        $this->actingAs($staff)->post(route('overtime.cancel', $ot))->assertStatus(422);

        $this->submit($staff);
        $second = OvertimeRequest::latest('id')->first();
        $this->actingAs($this->userWithRole('Employee'))->post(route('overtime.cancel', $second))->assertNotFound();   // đơn người khác
        $this->actingAs($staff)->post(route('overtime.cancel', $second))->assertSessionHas('success');
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->actingAs($staff)->get(route('overtime.index', ['tab' => 'all']))->assertForbidden();
    }

    public function test_requests_are_isolated_between_organizations(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $userB = User::factory()->create(['name' => 'Nhân viên B']);
        $otB = app(CurrentOrganization::class)->run($orgB->id, fn () => OvertimeRequest::create(['user_id' => $userB->id, 'work_date' => now()->toDateString(),
            'start_time' => '18:00', 'end_time' => '19:00', 'hours' => 1, 'level' => 'high', 'reason' => 'Bí mật công ty B', 'status' => 'pending']));

        $this->actingAs($owner)->get(route('overtime.index', ['tab' => 'all']))->assertOk()->assertDontSee('Bí mật công ty B');
        $this->post(route('overtime.approve', $otB))->assertNotFound();
        $this->assertSame('pending', OvertimeRequest::withoutGlobalScopes()->find($otB->id)->status);
    }
}
