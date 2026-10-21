<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\AssetService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** crm:seed-demo nạp dữ liệu mẫu (gồm Phase 4–5) vào đúng một tổ chức; crm:purge-demo xóa sạch mà không đụng dữ liệu thật. */
class DemoSeedTest extends TestCase
{
    public function test_seed_demo_covers_hr_and_assets_and_purge_removes_everything(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $org = Organization::findOrFail($owner->organizations()->value('organizations.id'));
        $other = Organization::create(['name' => 'Công ty thật', 'slug' => 'cong-ty-that']);
        $realAsset = app(AssetService::class)->create(['name' => 'Tài sản thật', 'purchase_cost' => 1], $owner);

        $this->artisan('crm:seed-demo', ['organization' => $org->id, '--force' => true])->assertSuccessful();

        $count = fn ($table, $where = []) => DB::table($table)->where($where)->count();
        foreach (['customers', 'leads', 'opportunities', 'invoices', 'projects', 'assets', 'software_licenses', 'payroll_periods'] as $t) {
            $this->assertGreaterThan(0, $count($t, ['organization_id' => $org->id, 'is_demo' => true]), "Thiếu dữ liệu demo: $t");
            $this->assertSame(0, $count($t, ['organization_id' => $other->id]), "Lọt sang tổ chức khác: $t");
        }
        foreach (['employee_profiles', 'attendance_records', 'overtime_requests', 'payslips', 'asset_events', 'license_assignments'] as $t) {
            $this->assertGreaterThan(0, $count($t, ['organization_id' => $org->id]), "Thiếu dữ liệu demo: $t");
        }
        $this->assertSame('approved', DB::table('payroll_periods')->where('is_demo', true)->value('status'));
        $this->assertTrue(DB::table('payslips')->where('net_pay', '>', 0)->exists());

        // Các màn hình chính mở được với dữ liệu demo
        foreach (['assets.overview', 'assets.index', 'licenses.index', 'payroll.index', 'attendance.board', 'overtime.index', 'projects.index', 'reports.index'] as $route) {
            $this->actingAs($owner)->get(route($route))->assertOk();
        }
        $this->artisan('crm:seed-demo', ['organization' => $org->id, '--force' => true])->assertSuccessful();   // chạy lại: bỏ qua, không nhân đôi
        $this->assertSame(10, $count('assets', ['is_demo' => true]));

        $this->artisan('crm:purge-demo', ['--force' => true])->assertSuccessful();
        foreach (['customers', 'projects', 'assets', 'software_licenses', 'payroll_periods', 'users'] as $t) {
            $this->assertSame(0, $count($t, ['is_demo' => true]), "Còn sót demo: $t");
        }
        foreach (['employee_profiles', 'attendance_records', 'overtime_requests', 'payslips', 'license_assignments'] as $t) {
            $this->assertSame(0, $count($t), "Còn sót: $t");
        }
        $this->assertSame(['Tài sản thật'], DB::table('assets')->pluck('name')->all());
        $this->assertSame(1, $count('asset_events', ['asset_id' => $realAsset->id]));
    }
}
