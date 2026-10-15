<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\OrganizationProvisioner;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Bước 2.1: cài đặt và phòng ban riêng của từng tổ chức. */
class OrganizationSettingsTest extends TestCase
{
    private function orgB(): Organization
    {
        $org = Organization::create(['name' => 'Công ty Bình Minh', 'slug' => 'binh-minh']);
        app(OrganizationProvisioner::class)->provision($org);

        return $org;
    }

    private function settingsPayload(array $overrides = []): array
    {
        return $overrides + [
            'company_name' => 'Công ty A', 'brand_color' => '#D9823B', 'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND', 'date_format' => 'd/m/Y', 'default_tax_rate' => 10,
            'notify_lead_assigned' => 1, 'notify_task_due' => 1, 'notify_deal_stage' => 1, 'notify_ticket_new' => 1,
        ];
    }

    private function inOrg(int $orgId, callable $fn): mixed
    {
        return app(CurrentOrganization::class)->run($orgId, $fn);
    }

    // ---------------- Cài đặt ----------------

    public function test_new_organization_gets_its_own_company_name(): void
    {
        $orgB = $this->orgB();
        $this->assertSame('Công ty Bình Minh', Setting::allCached($orgB->id)['company_name']);
        $this->assertSame('VND', Setting::allCached($orgB->id)['currency']);   // giá trị mặc định
    }

    public function test_settings_saved_in_one_organization_do_not_leak_to_another(): void
    {
        $orgB = $this->orgB();
        $ownerA = $this->userWithRole(PermissionCatalog::OWNER, ['email' => 'a@example.com']);

        $this->actingAs($ownerA)->put(route('settings.update'), $this->settingsPayload([
            'company_name' => 'Công ty Ánh Dương', 'company_address' => '12 Lê Lợi', 'currency' => 'USD', 'date_format' => 'Y-m-d', 'default_tax_rate' => 8,
        ]))->assertSessionHas('success');

        $a = Setting::allCached(Organization::DEFAULT_ID);
        $b = Setting::allCached($orgB->id);
        $this->assertSame('Công ty Ánh Dương', $a['company_name']);
        $this->assertSame('USD', $a['currency']);
        $this->assertSame('Công ty Bình Minh', $b['company_name']);
        $this->assertSame('VND', $b['currency']);
        $this->assertSame('', $b['company_address']);   // không mượn địa chỉ của tổ chức A
        $this->assertSame(0, OrganizationSetting::where('organization_id', $orgB->id)->where('value', 'Công ty Ánh Dương')->count());

        // Giao diện của từng tổ chức hiển thị đúng tên công ty
        $this->actingAs($ownerA)->get(route('dashboard'))->assertSee('Công ty Ánh Dương');
        $ownerB = $this->inOrg($orgB->id, function () {
            $u = User::factory()->create(['status' => 'active', 'email' => 'b@example.com']);
            $u->assignRole(PermissionCatalog::OWNER);

            return $u;
        });
        $ownerB->forceFill(['current_organization_id' => $orgB->id])->saveQuietly();
        $this->actingAs($ownerB->fresh())->get(route('dashboard'))->assertSee('Công ty Bình Minh')->assertDontSee('Công ty Ánh Dương');
    }

    public function test_money_and_dates_follow_organization_settings(): void
    {
        $orgB = $this->orgB();
        $this->inOrg($orgB->id, fn () => Setting::put(['currency' => 'USD', 'date_format' => 'Y-m-d', 'timezone' => 'Asia/Tokyo']));

        $this->inOrg(Organization::DEFAULT_ID, function () {
            $this->assertSame('1.250.000 ₫', money(1250000));
            $this->assertSame('15/10/2026 10:00', vn_date(Carbon::parse('2026-10-15 10:00:00'), true));
        });
        $this->inOrg($orgB->id, function () {
            $this->assertSame('USD 1,250,000.00', money(1250000));
            $this->assertSame('2026-10-15 12:00', vn_date(Carbon::parse('2026-10-15 10:00:00'), true));   // Tokyo = +2 giờ so với Hà Nội
            $this->assertSame('2026-10-15', vn_date('2026-10-15'));   // ngày thuần không bị lệch
        });
    }

    public function test_notification_switches_are_per_organization(): void
    {
        $orgB = $this->orgB();
        $user = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);
        $this->inOrg(Organization::DEFAULT_ID, fn () => Setting::put(['notify_task_due' => '0']));

        $this->inOrg(Organization::DEFAULT_ID, fn () => CrmNotification::send($user, 'task_due', 'A tắt thông báo'));
        $this->inOrg($orgB->id, fn () => CrmNotification::send($user, 'task_due', 'B vẫn bật'));
        $messages = $user->notifications()->get()->pluck('data.message')->all();
        $this->assertSame(['B vẫn bật'], $messages);
    }

    public function test_platform_wide_settings_are_only_editable_by_platform_admin(): void
    {
        $owner = $this->userWithRole(PermissionCatalog::OWNER, ['email' => 'a@example.com']);
        $this->actingAs($owner)->get(route('settings.branding'))->assertForbidden();
        $this->actingAs($owner)->put(route('settings.branding.update'), ['brand_name' => 'HACK', 'login_headline' => 'x', 'copyright_text' => 'x'])->assertForbidden();
        $this->actingAs($owner)->get(route('settings.edit'))->assertOk()->assertDontSee(route('settings.branding'));
        $this->assertNotSame('HACK', Setting::get('brand_name'));

        $this->actingAs($this->admin())->get(route('settings.branding'))->assertOk();
    }

    public function test_global_settings_stay_shared_between_organizations(): void
    {
        $orgB = $this->orgB();
        Setting::put(['brand_name' => 'TOPX'], 'branding');
        $this->assertSame('TOPX', Setting::allCached(Organization::DEFAULT_ID)['brand_name']);
        $this->assertSame('TOPX', Setting::allCached($orgB->id)['brand_name']);
    }

    // ---------------- Phòng ban theo tổ chức ----------------

    public function test_department_is_stored_per_membership(): void
    {
        $orgB = $this->orgB();
        $deptA = $this->inOrg(Organization::DEFAULT_ID, fn () => Department::where('code', 'KD')->firstOrFail());
        $deptB = $this->inOrg($orgB->id, fn () => Department::where('code', 'MKT')->firstOrFail());

        $user = $this->userWithRole('Sales Employee', ['email' => 'chung@example.com']);
        $user->assignDepartment($deptA->id, Organization::DEFAULT_ID);
        $this->inOrg($orgB->id, function () use ($user, $deptB, $orgB) {
            $user->organizations()->attach($orgB->id, ['status' => 'active', 'joined_at' => now(), 'department_id' => $deptB->id]);
            $user->unsetRelation('roles');
            $user->assignRole('Sales Employee');
        });
        $user = $user->fresh();

        $this->assertSame($deptA->id, $user->departmentIdIn(Organization::DEFAULT_ID));
        $this->assertSame($deptB->id, $user->departmentIdIn($orgB->id));
        $this->inOrg(Organization::DEFAULT_ID, fn () => $this->assertSame($deptA->name, $user->fresh()->department?->name));
        $this->inOrg($orgB->id, fn () => $this->assertSame($deptB->name, $user->fresh()->department?->name));

        // Số nhân viên của phòng ban chỉ đếm trong tổ chức của phòng ban
        $this->inOrg(Organization::DEFAULT_ID, fn () => $this->assertSame(1, Department::withCount('users')->find($deptA->id)->users_count));
    }

    public function test_admin_sets_department_and_filters_by_it(): void
    {
        $admin = $this->admin();
        $dept = Department::where('code', 'KD')->firstOrFail();
        $member = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com', 'name' => 'Nhân Viên Kinh Doanh']);
        $other = $this->userWithRole('Sales Employee', ['email' => 'nv2@example.com', 'name' => 'Người Khác Phòng']);

        $this->actingAs($admin)->put(route('users.update', $member), [
            'name' => $member->name, 'email' => $member->email, 'status' => 'active', 'role' => 'Sales Employee', 'department_id' => $dept->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($dept->id, $member->fresh()->departmentIdIn());

        $this->actingAs($admin)->get(route('users.index', ['department' => $dept->id]))->assertOk()
            ->assertSee('Nhân Viên Kinh Doanh')->assertDontSee('Người Khác Phòng');

        // Tạo nhân viên mới kèm phòng ban
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Nhân viên mới', 'email' => 'moi@example.com', 'status' => 'active', 'role' => 'Sales Employee',
            'department_id' => $dept->id, 'password' => 'Abcdef1234', 'password_confirmation' => 'Abcdef1234',
        ])->assertRedirect();
        $this->assertSame($dept->id, User::where('email', 'moi@example.com')->first()->departmentIdIn());
    }

    public function test_department_of_another_organization_cannot_be_assigned(): void
    {
        $orgB = $this->orgB();
        $deptB = $this->inOrg($orgB->id, fn () => Department::where('code', 'MKT')->firstOrFail());
        $member = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);

        $this->actingAs($this->admin())->put(route('users.update', $member), [
            'name' => $member->name, 'email' => $member->email, 'status' => 'active', 'role' => 'Sales Employee', 'department_id' => $deptB->id,
        ])->assertSessionHasErrors('department_id');
        $this->assertNull($member->fresh()->departmentIdIn());
    }
}
