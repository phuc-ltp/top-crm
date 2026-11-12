<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\OrganizationProvisioner;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Cấp bậc: chỉ quản lý tài khoản / cấp vai trò / chỉnh quyền cho cấp THẤP HƠN mình; chủ sở hữu quản lý mọi cấp. */
class RoleHierarchyTest extends TestCase
{
    private function owner(): User
    {
        return $this->userWithRole(PermissionCatalog::OWNER, ['email' => 'owner@example.com']);
    }

    private function adminUser(string $email = 'qt@example.com'): User
    {
        return $this->userWithRole('Organization Administrator', ['email' => $email]);
    }

    private function payload(User $u, string $role, string $status = 'active'): array
    {
        return ['name' => $u->name, 'email' => $u->email, 'status' => $status, 'role' => $role];
    }

    public function test_levels_are_ordered(): void
    {
        $this->assertSame(1, PermissionCatalog::level(PermissionCatalog::OWNER));
        $this->assertSame(2, PermissionCatalog::level('Organization Administrator'));
        $this->assertSame(3, PermissionCatalog::level('Sales Manager'));
        $this->assertSame(4, PermissionCatalog::level('Sales Employee'));
        $this->assertSame(5, PermissionCatalog::level('Read-only Auditor'));
        $this->assertSame(PermissionCatalog::DEFAULT_LEVEL, PermissionCatalog::level('Vai trò tự tạo'));
        foreach (PermissionCatalog::ORG_ROLES as $r) {
            $this->assertArrayHasKey($r, PermissionCatalog::LEVELS, "Thiếu cấp bậc cho $r");
        }
    }

    public function test_admin_cannot_manage_other_admins_or_owner(): void
    {
        $admin = $this->adminUser();
        $other = $this->adminUser('qt2@example.com');
        $owner = $this->owner();

        foreach ([$other, $owner] as $target) {
            $this->actingAs($admin)->get(route('users.edit', $target))->assertForbidden();
            $this->actingAs($admin)->put(route('users.update', $target), $this->payload($target, 'Sales Employee'))->assertForbidden();
            $this->actingAs($admin)->post(route('users.toggle-status', $target))->assertForbidden();
            $this->actingAs($admin)->put(route('users.password', $target), ['password' => 'Matkhau2026x', 'password_confirmation' => 'Matkhau2026x'])->assertForbidden();
            $this->actingAs($admin)->delete(route('users.destroy', $target))->assertForbidden();
        }
        $this->assertTrue($other->fresh()->hasRole('Organization Administrator'));
        $this->assertSame('active', $other->fresh()->status);
    }

    public function test_admin_manages_lower_levels_but_cannot_grant_admin_or_owner(): void
    {
        $admin = $this->adminUser();
        $staff = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);

        $this->actingAs($admin)->put(route('users.update', $staff), $this->payload($staff, 'Sales Manager'))->assertSessionHasNoErrors();
        $this->assertTrue($staff->fresh()->hasRole('Sales Manager'));
        $this->actingAs($admin)->put(route('users.update', $staff), $this->payload($staff, 'Organization Administrator'))->assertSessionHasErrors('role');
        $this->actingAs($admin)->put(route('users.update', $staff), $this->payload($staff, PermissionCatalog::OWNER))->assertSessionHasErrors('role');
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Mới', 'email' => 'moi@example.com', 'status' => 'active',
            'role' => 'Organization Administrator', 'password' => 'Matkhau2026x', 'password_confirmation' => 'Matkhau2026x'])->assertSessionHasErrors('role');
        $this->assertFalse($staff->fresh()->hasRole('Organization Administrator'));

        // Tự sửa hồ sơ của mình mà vẫn giữ vai trò hiện tại
        $this->actingAs($admin)->put(route('users.update', $admin), $this->payload($admin, 'Organization Administrator'))->assertSessionHasNoErrors();
        $this->assertTrue($admin->fresh()->hasRole('Organization Administrator'));
    }

    public function test_owner_manages_admins_and_can_grant_admin(): void
    {
        $owner = $this->owner();
        $admin = $this->adminUser();
        $staff = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);

        $this->actingAs($owner)->put(route('users.update', $admin), $this->payload($admin, 'Sales Manager'))->assertSessionHasNoErrors();
        $this->assertTrue($admin->fresh()->hasRole('Sales Manager'));
        $this->actingAs($owner)->put(route('users.update', $staff), $this->payload($staff, 'Organization Administrator'))->assertSessionHasNoErrors();
        $this->assertTrue($staff->fresh()->hasRole('Organization Administrator'));
    }

    public function test_role_permission_editing_follows_levels(): void
    {
        $orgId = app(CurrentOrganization::class)->id();
        $adminRole = Role::where('organization_id', $orgId)->where('name', 'Organization Administrator')->first();
        $salesRole = Role::where('organization_id', $orgId)->where('name', 'Sales Employee')->first();
        $admin = $this->adminUser();

        $this->actingAs($admin)->get(route('roles.edit', $adminRole))->assertForbidden();
        $this->actingAs($admin)->put(route('roles.update', $adminRole), ['permissions' => ['dashboard.view']])->assertForbidden();
        $this->assertGreaterThan(1, $adminRole->permissions()->count());
        $this->actingAs($admin)->get(route('roles.edit', $salesRole))->assertOk();
        $this->actingAs($admin)->get(route('roles.index'))->assertOk()->assertSee(__('Cấp :n', ['n' => 2]))->assertDontSee(route('roles.edit', $adminRole));

        $owner = $this->owner();
        $this->actingAs($owner)->get(route('roles.edit', $adminRole))->assertOk();
    }

    public function test_invitation_and_join_request_roles_follow_levels(): void
    {
        $admin = $this->adminUser();
        $roles = \App\Services\Auth\AccountProvisioner::assignableRoles($admin, app(CurrentOrganization::class)->id());
        $this->assertArrayNotHasKey('Organization Administrator', $roles);
        $this->assertArrayNotHasKey(PermissionCatalog::OWNER, $roles);
        $this->assertArrayHasKey('Sales Manager', $roles);
        $this->assertArrayHasKey('Read-only Auditor', $roles);

        $manager = $this->userWithRole('Sales Manager', ['email' => 'tp@example.com']);
        $mgrRoles = \App\Services\Auth\AccountProvisioner::assignableRoles($manager, app(CurrentOrganization::class)->id());
        $this->assertArrayNotHasKey('Marketing Manager', $mgrRoles);   // cùng cấp
        $this->assertArrayHasKey('Sales Employee', $mgrRoles);
    }

    public function test_every_organization_gets_the_same_roles_and_permissions(): void
    {
        $orgB = Organization::create(['name' => 'Công ty thật', 'slug' => 'that']);
        app(OrganizationProvisioner::class)->provision($orgB);
        $orgA = app(CurrentOrganization::class)->id();
        foreach (PermissionCatalog::ORG_ROLES as $name) {
            $a = Role::where('organization_id', $orgA)->where('name', $name)->first()->permissions->pluck('name')->sort()->values()->all();
            $b = Role::where('organization_id', $orgB->id)->where('name', $name)->first()->permissions->pluck('name')->sort()->values()->all();
            $this->assertSame($a, $b, "Vai trò $name khác nhau giữa 2 tổ chức");
        }
        $this->artisan('crm:org-parity')->assertSuccessful();

        Role::where('organization_id', $orgB->id)->where('name', 'Read-only Auditor')->first()->revokePermissionTo('activity_logs.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->artisan('crm:org-parity')->assertFailed();
        $this->artisan('crm:org-parity', ['--fix' => true])->assertSuccessful();
        $this->artisan('crm:org-parity')->assertSuccessful();
    }
}
