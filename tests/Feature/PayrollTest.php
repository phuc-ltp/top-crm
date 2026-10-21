<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\Organization;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Bước 5.4: kỳ lương tháng từ bảng công + tăng ca, bảo hiểm, thuế TNCN lũy tiến, khóa khi duyệt, quyền xem phiếu lương. */
class PayrollTest extends TestCase
{
    private Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->month = now()->subMonthNoOverflow()->startOfMonth();
    }

    /** 20 ngày × 8 giờ công và 1 đơn tăng ca 3 giờ đã duyệt với đơn giá cho trước. */
    private function workMonth(User $user, string $otRate): void
    {
        for ($d = 0; $d < 20; $d++) {
            (new AttendanceRecord(['user_id' => $user->id, 'work_date' => $this->month->copy()->addDays($d)->toDateString()]))
                ->forceFill(['worked_minutes' => 480, 'late_minutes' => $d === 0 ? 10 : 0, 'status' => 'present'])->save();
        }
        (new OvertimeRequest(['user_id' => $user->id, 'work_date' => $this->month->copy()->addDays(2)->toDateString(), 'start_time' => '18:00', 'end_time' => '21:00',
            'hours' => 3, 'level' => 'high', 'reason' => 'Tăng ca', 'status' => 'approved']))->forceFill(['rate' => $otRate])->save();
    }

    private function createPeriod(User $by)
    {
        return $this->actingAs($by)->post(route('payroll.store'), ['month' => $this->month->format('Y-m')]);
    }

    public function test_progressive_tax_brackets(): void
    {
        $this->assertSame('0', PayrollService::progressiveTax('0'));
        $this->assertSame('500000', PayrollService::progressiveTax('10000000'));
        $this->assertSame('1074000', PayrollService::progressiveTax('15740000'));
        $this->assertSame('27500000', PayrollService::progressiveTax('120000000'));   // 0,5 + 2 + 6 + 12 + 7 triệu
    }

    public function test_payslip_matches_hand_calculation(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);        // 200.000 đ/giờ
        $this->workMonth($owner, '300000');
        $this->createPeriod($owner)->assertSessionHas('success');
        $period = PayrollPeriod::firstOrFail();
        $slip = Payslip::where('user_id', $owner->id)->firstOrFail();
        $this->assertSame(['20', '9600', '10', '200000.00', '32000000.00', '3.00', '900000.00', '300000.00'],
            [(string) $slip->work_days, (string) $slip->worked_minutes, (string) $slip->late_minutes, $slip->hourly_rate, $slip->base_pay, $slip->ot_hours, $slip->ot_pay, $slip->ot_exempt]);

        $this->put(route('payslips.update', $slip), ['allowance' => 2000000, 'bonus' => 0, 'other_deduction' => 500000, 'note' => 'Tạm ứng'])->assertSessionHas('success');
        $slip->refresh();
        $this->assertSame('34900000.00', $slip->gross_pay);
        $this->assertSame(['2560000.00', '480000.00', '320000.00'], [$slip->social_insurance, $slip->health_insurance, $slip->unemployment_insurance]);
        $this->assertSame('15740000.00', $slip->taxable_income);
        $this->assertSame('1074000.00', $slip->personal_income_tax);
        $this->assertSame('29966000.00', $slip->net_pay);

        // Người phụ thuộc giảm thu nhập tính thuế; tắt bảo hiểm & thuế thì thực nhận = thu nhập − khấu trừ khác
        EmployeeProfile::updateOrCreate(['user_id' => $owner->id], ['dependents' => 1, 'employment_status' => 'active']);
        $this->post(route('payroll.recalculate', $period))->assertSessionHas('success');
        $slip->refresh();
        $this->assertSame('2000000.00', $slip->allowance);                            // tính lại giữ khoản nhập tay
        $this->assertSame('9540000.00', $slip->taxable_income);                       // − 6.200.000 giảm trừ người phụ thuộc
        $this->assertSame('477000.00', $slip->personal_income_tax);
        $this->assertSame('30563000.00', $slip->net_pay);
        $this->assertStringNotContainsString('30563000', \App\Models\ActivityLog::all()->toJson());   // số tiền lương không vào nhật ký
    }

    public function test_insurance_and_tax_can_be_disabled(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $this->workMonth($owner, '300000');
        $this->actingAs($owner)->put(route('payroll.settings'), ['payroll_personal_deduction' => 15500000, 'payroll_dependent_deduction' => 6200000, 'payroll_insurance_cap' => 46800000])
            ->assertSessionHas('success');                                            // hai công tắc không gửi = tắt
        $this->createPeriod($owner);
        $slip = Payslip::firstOrFail();
        $this->assertSame(['0.00', '0.00', '32900000.00'], [$slip->social_insurance, $slip->personal_income_tax, $slip->net_pay]);
    }

    public function test_workflow_locks_period_and_publishes_payslips(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $staff = $this->userWithRole('Employee');
        EmployeeProfile::create(['user_id' => $staff->id, 'employment_status' => 'active']);
        $this->workMonth($staff, '100000');
        $this->createPeriod($owner);
        $period = PayrollPeriod::firstOrFail();
        $slip = Payslip::where('user_id', $staff->id)->firstOrFail();
        $this->createPeriod($owner)->assertSessionHasErrors('month');                 // trùng tháng

        // Chưa duyệt: nhân viên không thấy phiếu
        $this->actingAs($staff)->get(route('payslips.show', $slip))->assertNotFound();
        $this->get(route('payslips.mine'))->assertOk()->assertDontSee($period->label);

        $this->actingAs($owner)->post(route('payroll.transition', [$period, 'approve']))->assertStatus(422);   // phải gửi duyệt trước
        $this->post(route('payroll.transition', [$period, 'submit']))->assertSessionHas('success');
        $this->put(route('payslips.update', $slip), ['allowance' => 1])->assertStatus(422);                   // chờ duyệt: không sửa
        $this->post(route('payroll.transition', [$period, 'approve']))->assertSessionHas('success');
        $this->assertSame(['approved', $owner->id], [$period->fresh()->status, $period->fresh()->approved_by]);
        $this->assertSame(1, $staff->notifications()->where('data->kind', 'payslip_ready')->count());

        // Đã duyệt: khóa tính lại / sửa / xóa
        $this->post(route('payroll.recalculate', $period))->assertStatus(422);
        $this->put(route('payslips.update', $slip), ['allowance' => 1])->assertStatus(422);
        $this->delete(route('payroll.destroy', $period))->assertStatus(422);
        $this->post(route('payroll.transition', [$period, 'pay']))->assertSessionHas('success');
        $this->assertSame('paid', $period->fresh()->status);

        $this->actingAs($staff)->get(route('payslips.mine'))->assertOk()->assertSee(money($slip->net_pay));
        $this->get(route('payslips.show', $slip))->assertOk()->assertSee(__('Thực nhận'));
        $this->get(route('payslips.pdf', $slip))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('payroll.show', $period))->assertForbidden();
        $this->get(route('payroll.export', $period))->assertForbidden();
        $this->actingAs($owner)->get(route('payroll.export', $period))->assertOk();
    }

    public function test_payslips_are_private_and_isolated(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $staff = $this->userWithRole('Employee');
        $colleague = $this->userWithRole('Employee');
        foreach ([$staff, $colleague] as $u) {
            EmployeeProfile::create(['user_id' => $u->id, 'employment_status' => 'active']);
        }
        $this->createPeriod($owner);
        $period = PayrollPeriod::firstOrFail();
        $this->post(route('payroll.transition', [$period, 'submit']));
        $this->post(route('payroll.transition', [$period, 'approve']));
        $slip = Payslip::where('user_id', $staff->id)->firstOrFail();

        $this->actingAs($colleague)->get(route('payslips.show', $slip))->assertNotFound();
        $this->get(route('payslips.pdf', $slip))->assertNotFound();
        $this->post(route('payroll.store'), ['month' => now()->format('Y-m')])->assertForbidden();
        $this->actingAs($this->userWithRole('Accountant'))->get(route('payroll.show', $period))->assertOk();    // xem được
        $this->post(route('payroll.transition', [$period, 'pay']))->assertForbidden();                          // nhưng không thao tác

        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $periodB = app(CurrentOrganization::class)->run($orgB->id, fn () => PayrollPeriod::create(['period' => $this->month->toDateString(), 'status' => 'draft']));
        $this->actingAs($owner)->get(route('payroll.show', $periodB))->assertNotFound();
        $this->get(route('payroll.index'))->assertOk()->assertSee($period->label);
    }
}
