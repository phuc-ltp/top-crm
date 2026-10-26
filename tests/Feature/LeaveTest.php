<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Organization;
use App\Models\Payslip;
use App\Models\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Nghỉ phép & ngày lễ: tính ngày làm việc, quỹ phép, duyệt, bảng công, bảng lương trả nghỉ có lương. */
class LeaveTest extends TestCase
{
    private Carbon $mon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mon = now()->startOfWeek(Carbon::MONDAY)->addWeeks(2)->startOfDay();   // Thứ Hai cố định trong tương lai gần
    }

    private function day(int $offset): string
    {
        return $this->mon->copy()->addDays($offset)->toDateString();
    }

    private function ask(User $user, array $data = [])
    {
        return $this->actingAs($user)->post(route('leave.store'), $data + ['type' => 'annual', 'start_date' => $this->day(0), 'end_date' => $this->day(0), 'part' => 'full', 'reason' => 'Việc gia đình']);
    }

    public function test_working_days_exclude_weekends_and_holidays_and_block_overlaps(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $this->actingAs($owner)->post(route('holidays.store'), ['date' => $this->day(2), 'name' => 'Ngày lễ thử', 'is_paid' => 1])->assertSessionHas('success');
        $staff = $this->userWithRole('Employee');

        $this->ask($staff, ['end_date' => $this->day(4)])->assertSessionHasNoErrors();                                   // T2–T6 trừ lễ T4 = 4 ngày
        $this->assertSame('4.0', LeaveRequest::latest('id')->first()->days);
        $this->ask($staff, ['start_date' => $this->day(4), 'end_date' => $this->day(4)])->assertSessionHasErrors('start_date');   // trùng
        $this->ask($staff, ['start_date' => $this->day(5), 'end_date' => $this->day(6)])->assertSessionHasErrors('start_date');   // chỉ cuối tuần
        $this->ask($staff, ['start_date' => $this->day(7), 'end_date' => $this->day(8), 'part' => 'am'])->assertSessionHasErrors('part');

        $this->ask($staff, ['start_date' => $this->day(4 + 7), 'end_date' => $this->day(7 + 7)])->assertSessionHasNoErrors();   // T6 → T2 tuần sau = 2 ngày
        $this->assertSame('2.0', LeaveRequest::latest('id')->first()->days);
        $this->ask($staff, ['start_date' => $this->day(9), 'end_date' => $this->day(9), 'part' => 'am'])->assertSessionHasNoErrors();
        $this->ask($staff, ['start_date' => $this->day(9), 'end_date' => $this->day(9), 'part' => 'pm'])->assertSessionHasNoErrors();   // sáng + chiều cùng ngày
        $this->ask($staff, ['start_date' => $this->day(9), 'end_date' => $this->day(9), 'part' => 'am'])->assertSessionHasErrors('start_date');
        $this->assertSame('0.5', LeaveRequest::latest('id')->first()->days);

        // Thêm nhanh ngày lễ dương lịch, không trùng lặp
        $this->actingAs($owner)->post(route('holidays.fixed'), ['year' => 2030])->assertSessionHas('success');
        $this->post(route('holidays.fixed'), ['year' => 2030]);
        $this->assertSame(4, Holiday::whereYear('date', 2030)->count());
        $this->actingAs($staff)->get(route('holidays.index'))->assertForbidden();
    }

    public function test_annual_quota_and_approval_flow(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $staff = $this->userWithRole('Employee');
        EmployeeProfile::create(['user_id' => $staff->id, 'manager_id' => $manager->id, 'employment_status' => 'active', 'annual_leave_days' => 3]);

        $this->ask($staff, ['end_date' => $this->day(3)])->assertSessionHasErrors('start_date');                         // 4 ngày > quỹ 3
        $this->ask($staff, ['type' => 'unpaid', 'end_date' => $this->day(3)])->assertSessionHasNoErrors();               // không lương: không trừ quỹ
        $this->ask($staff, ['start_date' => $this->day(7), 'end_date' => $this->day(8)])->assertSessionHasNoErrors();    // 2 ngày phép năm
        $this->ask($staff, ['start_date' => $this->day(14), 'end_date' => $this->day(15)])->assertSessionHasErrors('start_date');   // 2 + 2 đang chờ > 3
        $leave = LeaveRequest::where('type', 'annual')->firstOrFail();
        $this->assertSame(2, $manager->notifications()->where('data->kind', 'leave_request')->count());

        $this->actingAs($staff)->post(route('leave.approve', $leave))->assertForbidden();                               // không tự duyệt
        $this->actingAs($this->userWithRole('Employee'))->post(route('leave.approve', $leave))->assertForbidden();
        $this->actingAs($manager)->post(route('leave.reject', $leave))->assertSessionHasErrors('decision_note');
        $this->post(route('leave.approve', $leave))->assertSessionHas('success');
        $this->assertSame(['approved', $manager->id], [$leave->fresh()->status, $leave->fresh()->approver_id]);
        $this->assertSame(1, $staff->notifications()->where('data->kind', 'leave_decision')->count());
        $this->post(route('leave.approve', $leave))->assertStatus(422);

        $this->actingAs($staff)->get(route('leave.index'))->assertOk()->assertSee(__('Còn lại'));
        $this->assertSame('2.0', app(\App\Services\LeaveService::class)->used($staff, $this->mon->year));
        Setting::put(['leave_annual_days' => '14'], 'leave');
        $this->assertSame('3.0', app(\App\Services\LeaveService::class)->quota($staff));                                 // quỹ riêng ưu tiên
    }

    public function test_payroll_pays_paid_leave_and_holidays_and_board_shows_codes(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $staff = $this->userWithRole('Employee');                                                                       // 35.000 đ/giờ
        EmployeeProfile::create(['user_id' => $staff->id, 'employment_status' => 'active']);
        $month = now()->subMonthNoOverflow()->startOfMonth();
        $weekdays = collect(range(0, 27))->map(fn ($i) => $month->copy()->addDays($i))->filter(fn ($d) => $d->isWeekday())->values();
        Holiday::create(['date' => $weekdays[0]->toDateString(), 'name' => 'Lễ thử', 'is_paid' => true]);
        Holiday::create(['date' => $weekdays[5]->toDateString(), 'name' => 'Lễ không lương', 'is_paid' => false]);
        $mk = fn ($type, $d, $part = 'full') => (new LeaveRequest(['user_id' => $staff->id, 'type' => $type, 'start_date' => $d, 'end_date' => $d, 'part' => $part,
            'days' => $part === 'full' ? 1 : 0.5, 'reason' => 'x', 'status' => 'approved']))->save();
        $mk('annual', $weekdays[1]->toDateString());
        $mk('personal', $weekdays[2]->toDateString(), 'pm');
        $mk('unpaid', $weekdays[3]->toDateString());
        $mk('sick', $weekdays[4]->toDateString());
        $mk('annual', $weekdays[0]->toDateString());                                                                     // trùng ngày lễ: không tính hai lần

        $period = PayrollPeriod::create(['period' => $month->toDateString(), 'status' => 'draft']);
        app(PayrollService::class)->calculate($period);
        $slip = Payslip::where('user_id', $staff->id)->firstOrFail();
        $this->assertSame([1200, '700000.00'], [$slip->paid_leave_minutes, $slip->paid_leave_pay]);                      // lễ 480 + phép 480 + chiều 240
        $this->assertSame('700000.00', $slip->gross_pay);
        $this->assertSame('700000.00', $slip->insurance_base);
        $this->assertSame([1.5, 1], [$slip->meta['paid_leave_days'], $slip->meta['holiday_days']]);
        $this->actingAs($owner)->get(route('payslips.show', $slip))->assertSee(__('Nghỉ phép / lễ có lương'));

        $this->get(route('attendance.board', ['month' => $month->format('Y-m')]))->assertOk()->assertSee('>L</span>', false)->assertSee('>P</span>', false)->assertSee('P½', false);

        // Ngày lễ của tổ chức khác không ảnh hưởng
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        app(CurrentOrganization::class)->run($orgB->id, fn () => Holiday::create(['date' => $weekdays[6]->toDateString(), 'name' => 'Lễ B', 'is_paid' => true]));
        app(PayrollService::class)->calculate($period->fresh());
        $this->assertSame(1200, $slip->fresh()->paid_leave_minutes);
        $this->get(route('holidays.index', ['year' => $month->year]))->assertDontSee('Lễ B');
    }
}
