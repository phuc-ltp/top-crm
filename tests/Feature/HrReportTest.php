<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Services\AssetService;
use App\Services\PayrollService;
use Tests\TestCase;

/** Bước 5.7: báo cáo giờ công & tăng ca, quỹ lương theo phòng ban, giá trị tài sản — số liệu và quyền xem. */
class HrReportTest extends TestCase
{
    public function test_hr_reports_numbers_and_permissions(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $staff = $this->userWithRole('Employee');
        $month = now()->subMonthNoOverflow()->startOfMonth();
        foreach ([0, 1, 2] as $d) {
            (new AttendanceRecord(['user_id' => $staff->id, 'work_date' => $month->copy()->addDays($d)->toDateString()]))
                ->forceFill(['worked_minutes' => 480, 'late_minutes' => 5, 'status' => 'present'])->save();
        }
        (new OvertimeRequest(['user_id' => $staff->id, 'work_date' => $month->toDateString(), 'start_time' => '18:00', 'end_time' => '20:00',
            'hours' => 2, 'level' => 'medium', 'reason' => 'x', 'status' => 'approved']))->forceFill(['rate' => '70000'])->save();
        $period = PayrollPeriod::create(['period' => $month->toDateString(), 'status' => 'draft']);
        app(PayrollService::class)->calculate($period);
        $range = ['from' => $month->toDateString(), 'to' => $month->copy()->endOfMonth()->toDateString()];

        $this->actingAs($owner)->get(route('reports.show', ['type' => 'hr_attendance'] + $range))->assertOk()
            ->assertSee($staff->name)->assertSee(money('140000'));                                   // 2 giờ × 70.000
        $this->get(route('reports.show', ['type' => 'hr_payroll'] + $range))->assertOk();
        $this->assertSame(0, count($this->reportRows($owner, 'hr_payroll', $range)));              // kỳ nháp không tính
        $period->forceFill(['status' => 'approved'])->save();
        $rows = $this->reportRows($owner, 'hr_payroll', $range);
        $this->assertCount(1, $rows);
        $this->assertSame([1, 840000.0, 140000.0], [$rows[0][1], $rows[0][2], $rows[0][3]]);    // 24 giờ × 35.000; tăng ca 140.000
        $this->get(route('reports.export', ['type' => 'hr_payroll', 'format' => 'xlsx'] + $range))->assertOk();

        app(AssetService::class)->create(['name' => 'Laptop', 'purchase_cost' => 12000000, 'depreciation_months' => 0], $owner);
        $this->assertSame(12000000.0, $this->reportRows($owner, 'assets', $range)[0][5]);

        // Quyền: báo cáo nhân sự cần payroll.view_all; báo cáo tài sản cần assets.view
        $inventory = $this->userWithRole('Inventory Manager');
        $this->actingAs($inventory)->get(route('reports.show', ['type' => 'assets']))->assertOk();
        $this->get(route('reports.show', ['type' => 'hr_payroll']))->assertForbidden();
        $this->get(route('reports.index'))->assertDontSee(__('Quỹ lương theo phòng ban'));
        $this->actingAs($this->userWithRole('Accountant'))->get(route('reports.show', ['type' => 'hr_attendance'] + $range))->assertOk();
    }

    private function reportRows($user, string $type, array $range): array
    {
        return (new \App\Services\ReportService($user, \Illuminate\Support\Carbon::parse($range['from']), \Illuminate\Support\Carbon::parse($range['to'])->endOfDay()))->build($type)['rows'];
    }
}
