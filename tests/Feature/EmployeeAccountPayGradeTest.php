<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Organization;
use App\Models\PayGrade;
use App\Models\User;
use App\Services\PayRateService;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Bước 5.1: tạo nhanh tài khoản nhân viên (mật khẩu tạm, bắt buộc đổi), bậc lương theo giờ, quyền xem lương. */
class EmployeeAccountPayGradeTest extends TestCase
{
    public function test_quick_account_shows_temp_password_once_and_forces_change(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $res = $this->actingAs($owner)->post(route('employees.accounts.store'), ['name' => 'Nguyễn Văn Mới', 'email' => 'Moi@Congty.VN', 'phone' => '0901234567',
            'role' => 'Sales Employee', 'job_title' => 'Nhân viên kinh doanh']);
        $user = User::where('email', 'moi@congty.vn')->firstOrFail();
        $res->assertRedirect(route('employees.show', $user->id));
        $temp = session('temp_password')['password'];

        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($temp, $user->password));
        $this->assertNotSame($temp, DB::table('users')->where('id', $user->id)->value('password'));
        $this->assertStringNotContainsString($temp, \App\Models\ActivityLog::all()->toJson());
        $this->assertSame(['Sales Employee'], $user->getRoleNames()->all());
        $this->get(route('employees.show', $user->id))->assertSee($temp);           // hiện một lần
        $this->get(route('employees.show', $user->id))->assertDontSee($temp);       // lần sau không còn

        // Nhân viên mới: bị chuyển về trang hồ sơ cho đến khi đổi mật khẩu
        auth()->logout();
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('profile.edit'));
        $this->getJson(route('notifications.feed'))->assertForbidden();
        $this->put(route('profile.password'), ['current_password' => $temp, 'password' => 'MatKhauMoi@2026x', 'password_confirmation' => 'MatKhauMoi@2026x'])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_only_authorized_users_create_accounts_and_owner_role_is_protected(): void
    {
        $admin = $this->userWithRole('Organization Administrator');
        $this->actingAs($admin)->post(route('employees.accounts.store'), ['name' => 'X', 'email' => 'x@congty.vn', 'role' => \App\Support\PermissionCatalog::OWNER])
            ->assertSessionHasErrors('role');
        $this->post(route('employees.accounts.store'), ['name' => 'Y', 'email' => $admin->email, 'role' => 'Employee'])->assertSessionHasErrors('email');

        $staff = $this->userWithRole('Employee');
        $this->actingAs($staff)->post(route('employees.accounts.store'), ['name' => 'Z', 'email' => 'z@congty.vn', 'role' => 'Employee'])->assertForbidden();
        $this->assertNull(User::where('email', 'z@congty.vn')->first());
    }

    public function test_hourly_rate_resolution_and_limits(): void
    {
        $owner = $this->admin();
        $sale = $this->userWithRole('Sales Employee');
        $rates = app(PayRateService::class);
        $this->assertSame('50000.00', $rates->rateFor($sale));                          // theo vai trò (Bậc 2)
        $this->assertSame('200000.00', $rates->rateFor($owner));                        // chủ sở hữu (Bậc 7)
        $this->assertSame('35000.00', $rates->rateFor($this->userWithRole('Employee')));

        $grade4 = PayGrade::where('hourly_rate', 100000)->firstOrFail();
        $this->actingAs($owner)->put(route('employees.update', $sale->id), ['employment_status' => 'active', 'pay_grade_id' => $grade4->id])->assertRedirect();
        $this->assertSame('100000.00', $rates->rateFor($sale));                         // bậc trong hồ sơ
        $this->put(route('employees.update', $sale->id), ['employment_status' => 'active', 'pay_grade_id' => $grade4->id, 'hourly_rate' => 120000]);
        $this->assertSame('120000.00', $rates->rateFor($sale));                         // đơn giá riêng
        $this->put(route('employees.update', $sale->id), ['employment_status' => 'active', 'hourly_rate' => 250000])->assertSessionHasErrors('hourly_rate');
        $this->put(route('employees.update', $sale->id), ['employment_status' => 'active', 'hourly_rate' => 20000])->assertSessionHasErrors('hourly_rate');

        // Người chỉ có quyền sửa hồ sơ (không có quyền lương) không đổi được lương
        $editor = $this->userWithRole('Employee');
        $editor->givePermissionTo('employees.update');
        $this->actingAs($editor)->put(route('employees.update', $sale->id), ['employment_status' => 'active', 'hourly_rate' => 199000])->assertRedirect();
        $this->assertSame('120000.00', $rates->rateFor($sale));
    }

    public function test_salary_is_visible_only_to_self_and_payroll_roles(): void
    {
        $sale = $this->userWithRole('Sales Employee');
        $colleague = $this->userWithRole('Sales Employee');
        $accountant = $this->userWithRole('Accountant');
        $rate = money('50000');

        $this->actingAs($sale)->get(route('employees.show', $sale->id))->assertSee(__('Lương theo giờ'))->assertSee($rate);
        $this->actingAs($colleague)->get(route('employees.show', $sale->id))->assertOk()->assertDontSee(__('Lương theo giờ'));
        $this->actingAs($accountant)->get(route('employees.show', $sale->id))->assertSee($rate);
        $this->actingAs($accountant)->get(route('pay-grades.index'))->assertForbidden();   // xem được nhưng không quản trị bậc lương
    }

    public function test_pay_grades_crud_role_mapping_and_isolation(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $this->actingAs($owner)->get(route('pay-grades.index'))->assertOk()->assertSee('Bậc 7');
        $this->assertSame(7, PayGrade::count());

        $this->post(route('pay-grades.store'), ['name' => 'Bậc kỹ sư', 'hourly_rate' => 120000, 'roles' => ['Sales Employee']])->assertSessionHas('success');
        $this->assertSame(['Sales Employee'], PayGrade::where('name', 'Bậc kỹ sư')->first()->roles);
        $this->assertNotContains('Sales Employee', PayGrade::where('hourly_rate', 50000)->first()->roles, 'Vai trò chỉ thuộc một bậc mặc định');
        $this->assertSame('120000.00', app(PayRateService::class)->rateFor($this->userWithRole('Sales Employee')));
        $this->post(route('pay-grades.store'), ['name' => 'Quá cao', 'hourly_rate' => 300000])->assertSessionHasErrors('hourly_rate');

        $staff = $this->userWithRole('Sales Employee');
        $grade = PayGrade::where('name', 'Bậc kỹ sư')->first();
        EmployeeProfile::create(['user_id' => $staff->id, 'pay_grade_id' => $grade->id, 'employment_status' => 'active']);
        $this->delete(route('pay-grades.destroy', $grade))->assertStatus(422);

        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $gradeB = app(CurrentOrganization::class)->run($orgB->id, fn () => PayGrade::create(['name' => 'Bậc bí mật B', 'hourly_rate' => 90000]));
        $this->get(route('pay-grades.index'))->assertDontSee('Bậc bí mật B');
        $this->put(route('pay-grades.update', $gradeB), ['name' => 'Sửa', 'hourly_rate' => 90000])->assertNotFound();
        $this->put(route('employees.update', $staff->id), ['employment_status' => 'active', 'pay_grade_id' => $gradeB->id])->assertSessionHasErrors('pay_grade_id');
    }
}
