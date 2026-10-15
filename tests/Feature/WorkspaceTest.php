<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\OrganizationProvisioner;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Bước 1.6: chuyển tổ chức, quản lý tổ chức (nền tảng), thành viên dùng chung nhiều tổ chức. */
class WorkspaceTest extends TestCase
{
    private function orgB(string $name = 'Công ty B'): Organization
    {
        $org = Organization::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name)]);
        app(OrganizationProvisioner::class)->provision($org);

        return $org;
    }

    /** Người dùng là thành viên cả tổ chức mặc định (A) và $orgB với vai trò cho trước. */
    private function sharedUser(Organization $orgB, string $roleA = 'Sales Employee', string $roleB = 'Sales Employee'): User
    {
        $user = $this->userWithRole($roleA, ['email' => 'chung@example.com']);
        app(CurrentOrganization::class)->run($orgB->id, function () use ($user, $orgB, $roleB) {
            $user->organizations()->attach($orgB->id, ['status' => 'active', 'joined_at' => now()]);
            $user->unsetRelation('roles');
            $user->assignRole($roleB);
        });

        return $user->fresh();
    }

    public function test_new_organization_gets_default_reference_data_and_roles(): void
    {
        $org = $this->orgB();
        app(CurrentOrganization::class)->run($org->id, function () {
            $this->assertSame(5, Department::count());
            $this->assertSame(count(OrganizationProvisioner::LEAD_SOURCES), LeadSource::count());
            $this->assertSame(6, PipelineStage::count());
        });
        $this->assertSame(14, Role::where('organization_id', $org->id)->count());
        // Chạy lại không tạo trùng
        app(OrganizationProvisioner::class)->provision($org);
        app(CurrentOrganization::class)->run($org->id, fn () => $this->assertSame(6, PipelineStage::count()));
    }

    public function test_member_can_switch_between_their_organizations(): void
    {
        $orgB = $this->orgB();
        $user = $this->sharedUser($orgB);
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk()->assertSee('Công ty B');   // có trong menu chuyển tổ chức
        $this->post(route('workspace.switch'), ['organization_id' => $orgB->id])->assertRedirect(route('dashboard'));
        $this->assertSame($orgB->id, (int) $user->fresh()->current_organization_id);
    }

    public function test_cannot_switch_into_organization_without_membership(): void
    {
        $orgB = $this->orgB();
        $user = $this->userWithRole('Organization Administrator');
        $this->actingAs($user)->post(route('workspace.switch'), ['organization_id' => $orgB->id])->assertForbidden();
        $this->assertSame(Organization::DEFAULT_ID, (int) $user->fresh()->current_organization_id);
    }

    public function test_cannot_switch_into_suspended_organization(): void
    {
        $orgB = $this->orgB();
        $user = $this->sharedUser($orgB);
        $orgB->update(['status' => 'suspended']);
        $this->actingAs($user)->post(route('workspace.switch'), ['organization_id' => $orgB->id])->assertForbidden();
    }

    public function test_platform_admin_can_enter_any_active_organization(): void
    {
        $orgB = $this->orgB();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('workspace.switch'), ['organization_id' => $orgB->id])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
        $this->assertSame($orgB->id, (int) $admin->fresh()->current_organization_id);
    }

    public function test_only_platform_admin_can_manage_organizations(): void
    {
        $owner = $this->userWithRole(PermissionCatalog::OWNER);
        $this->actingAs($owner)->get(route('platform.organizations.index'))->assertForbidden();
        $this->actingAs($owner)->post(route('platform.organizations.store'), ['name' => 'X', 'owner_email' => 'x@example.com'])->assertForbidden();

        $this->actingAs($this->admin())->get(route('platform.organizations.index'))->assertOk()->assertSee('TOP CRM');
    }

    public function test_platform_admin_creates_organization_with_new_owner(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('platform.organizations.store'), [
            'name' => 'Công ty Mới', 'owner_email' => 'chu@moi.test', 'owner_name' => 'Chủ Công ty Mới',
            'owner_password' => 'MatKhau2026Manh', 'owner_password_confirmation' => 'MatKhau2026Manh',
        ])->assertRedirect(route('platform.organizations.index'))->assertSessionHasNoErrors();

        $org = Organization::where('name', 'Công ty Mới')->firstOrFail();
        $owner = User::where('email', 'chu@moi.test')->firstOrFail();
        $this->assertSame('cong-ty-moi', $org->slug);
        $this->assertSame($owner->id, $org->owner_id);
        $this->assertTrue($owner->belongsToOrganization($org->id));
        $this->assertFalse($owner->belongsToOrganization(Organization::DEFAULT_ID));
        $this->assertTrue(app(CurrentOrganization::class)->run($org->id, fn () => $owner->fresh()->hasRole(PermissionCatalog::OWNER)));
        $this->assertSame(14, Role::where('organization_id', $org->id)->count());

        // Chủ sở hữu đăng nhập được và làm việc trong tổ chức mới
        auth()->logout();
        $this->post('/login', ['login' => 'chu@moi.test', 'password' => 'MatKhau2026Manh'])->assertRedirect(route('dashboard'));
        $this->get(route('roles.index'))->assertOk();
    }

    public function test_existing_account_can_become_owner_of_new_organization(): void
    {
        $existing = $this->userWithRole('Sales Employee', ['email' => 'dacotk@example.com']);
        $this->actingAs($this->admin());
        $this->post(route('platform.organizations.store'), ['name' => 'Công ty C', 'owner_email' => 'DACOTK@example.com'])->assertSessionHasNoErrors();

        $org = Organization::where('name', 'Công ty C')->firstOrFail();
        $this->assertSame($existing->id, $org->owner_id);
        $this->assertTrue($existing->belongsToOrganization($org->id));
        $this->assertTrue($existing->belongsToOrganization(Organization::DEFAULT_ID), 'Vẫn giữ tổ chức cũ');
        $this->assertTrue(Hash::check('password', $existing->fresh()->password), 'Không đổi mật khẩu tài khoản có sẵn');
    }

    public function test_default_organization_cannot_be_suspended(): void
    {
        $this->actingAs($this->admin());
        $this->put(route('platform.organizations.update', Organization::DEFAULT_ID), ['name' => 'TOP CRM', 'slug' => 'top', 'status' => 'suspended'])
            ->assertSessionHas('error');
        $this->assertSame('active', Organization::find(Organization::DEFAULT_ID)->status);
    }

    public function test_adding_existing_account_by_email_makes_them_a_member(): void
    {
        $orgB = $this->orgB();
        $other = app(CurrentOrganization::class)->run($orgB->id, fn () => User::factory()->create(['email' => 'nguoib@example.com', 'status' => 'active']));
        $this->actingAs($this->userWithRole('Organization Administrator'));

        $this->post(route('users.store'), ['name' => 'Bỏ qua', 'email' => 'nguoib@example.com', 'role' => 'Employee', 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($other->belongsToOrganization(Organization::DEFAULT_ID));
        $this->assertTrue($other->fresh()->hasRole('Employee'));
        $this->assertNotSame('Bỏ qua', $other->fresh()->name, 'Không sửa thông tin tài khoản có sẵn');
        // Thêm lần nữa → báo đã là thành viên
        $this->post(route('users.store'), ['email' => 'nguoib@example.com', 'role' => 'Employee'])->assertSessionHasErrors('email');
    }

    public function test_org_admin_cannot_take_over_shared_account(): void
    {
        $orgB = $this->orgB();
        $shared = $this->sharedUser($orgB, 'Sales Employee', PermissionCatalog::OWNER);   // chủ sở hữu của B
        $admin = $this->userWithRole('Organization Administrator');   // quản trị của A
        $this->actingAs($admin);

        $this->put(route('users.password', $shared), ['password' => 'Chiemquyen2026', 'password_confirmation' => 'Chiemquyen2026'])->assertForbidden();
        $this->post(route('users.send-reset', $shared))->assertForbidden();
        $this->put(route('users.update', $shared), ['name' => 'Bị đổi', 'email' => 'attacker@example.com', 'status' => 'active', 'role' => 'Sales Manager'])
            ->assertSessionHasNoErrors();

        $fresh = $shared->fresh();
        $this->assertSame('chung@example.com', $fresh->email, 'Email tài khoản dùng chung không được đổi');
        $this->assertNotSame('Bị đổi', $fresh->name);
        $this->assertTrue(Hash::check('password', $fresh->password));
        $this->assertTrue($fresh->hasRole('Sales Manager'), 'Vai trò trong tổ chức A thì được đổi');
        $this->assertTrue(app(CurrentOrganization::class)->run($orgB->id, fn () => $shared->fresh()->hasRole(PermissionCatalog::OWNER)), 'Vai trò ở B giữ nguyên');
    }

    public function test_org_admin_still_manages_accounts_that_only_belong_to_their_organization(): void
    {
        $member = $this->userWithRole('Employee');
        $this->actingAs($this->userWithRole('Organization Administrator'));
        $this->put(route('users.password', $member), ['password' => 'MatKhauMoi2026', 'password_confirmation' => 'MatKhauMoi2026'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('MatKhauMoi2026', $member->fresh()->password));
    }

    public function test_locking_suspends_membership_only_in_current_organization(): void
    {
        $orgB = $this->orgB();
        $shared = $this->sharedUser($orgB);
        $this->actingAs($this->userWithRole('Organization Administrator'))->post(route('users.toggle-status', $shared))->assertRedirect();

        $this->assertSame('suspended', $shared->membershipStatus(Organization::DEFAULT_ID));
        $this->assertSame('active', $shared->membershipStatus($orgB->id));
        $this->assertSame('active', $shared->fresh()->status, 'Tài khoản toàn hệ thống vẫn hoạt động');
        // Vẫn xuất hiện trong danh sách để mở lại
        $this->get(route('users.index'))->assertOk()->assertSee('chung@example.com');
        $this->post(route('users.toggle-status', $shared))->assertRedirect();
        $this->assertSame('active', $shared->membershipStatus(Organization::DEFAULT_ID));
    }

    public function test_suspended_member_is_moved_to_their_other_organization(): void
    {
        $orgB = $this->orgB();
        $shared = $this->sharedUser($orgB);
        $shared->organizations()->updateExistingPivot(Organization::DEFAULT_ID, ['status' => 'suspended']);
        $this->actingAs($shared->fresh())->get(route('dashboard'))->assertOk();
        $this->assertSame($orgB->id, (int) $shared->fresh()->current_organization_id);
    }

    public function test_deleting_shared_member_only_removes_them_from_this_organization(): void
    {
        $orgB = $this->orgB();
        $shared = $this->sharedUser($orgB);
        $this->actingAs($this->userWithRole('Organization Administrator'))->delete(route('users.destroy', $shared))->assertRedirect(route('users.index'));

        $fresh = User::withTrashed()->find($shared->id);
        $this->assertNull($fresh->deleted_at, 'Tài khoản không bị xóa');
        $this->assertFalse($fresh->belongsToOrganization(Organization::DEFAULT_ID));
        $this->assertTrue($fresh->belongsToOrganization($orgB->id));
        $this->assertTrue(app(CurrentOrganization::class)->run($orgB->id, fn () => $fresh->hasRole('Sales Employee')));
    }

    public function test_deleting_single_organization_member_soft_deletes_account(): void
    {
        $member = $this->userWithRole('Employee');
        $this->actingAs($this->userWithRole('Organization Administrator'))->delete(route('users.destroy', $member))->assertRedirect();
        $this->assertNotNull(User::withTrashed()->find($member->id)->deleted_at);
    }

    public function test_demo_data_flag_is_cached_per_organization(): void
    {
        $orgB = $this->orgB();
        \App\Models\Customer::create(['name' => 'Khách demo A', 'type' => 'individual', 'status' => 'active', 'is_demo' => true]);
        $userB = app(CurrentOrganization::class)->run($orgB->id, function () {
            $u = User::factory()->create(['status' => 'active']);
            $u->assignRole('Organization Administrator');

            return $u;
        });
        $this->actingAs($this->admin())->get(route('dashboard'))->assertSee('DỮ LIỆU DEMO');
        $this->actingAs($userB)->get(route('dashboard'))->assertDontSee('DỮ LIỆU DEMO');
    }
}
