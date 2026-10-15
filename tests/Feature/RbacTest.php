<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\RoleProvisioner;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Phân quyền 2 tầng: quản trị nền tảng + 14 vai trò riêng của từng tổ chức. */
class RbacTest extends TestCase
{
    private function orgB(): Organization
    {
        $org = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        app(RoleProvisioner::class)->provision($org->id);

        return $org;
    }

    private function rolesOf(int $orgId)
    {
        return Role::where('organization_id', $orgId)->pluck('name')->sort()->values()->all();
    }

    public function test_each_organization_gets_its_own_14_default_roles(): void
    {
        $orgB = $this->orgB();
        $expected = collect(PermissionCatalog::ORG_ROLES)->sort()->values()->all();

        $this->assertSame($expected, $this->rolesOf(Organization::DEFAULT_ID));
        $this->assertSame($expected, $this->rolesOf($orgB->id));
        $this->assertSame(14, count(PermissionCatalog::ORG_ROLES));
        // Mọi vai trò đều có quyền mặc định, chủ sở hữu có toàn bộ quyền
        foreach ([Organization::DEFAULT_ID, $orgB->id] as $orgId) {
            foreach (Role::where('organization_id', $orgId)->withCount('permissions')->get() as $role) {
                $this->assertGreaterThan(0, $role->permissions_count, "{$role->name} chưa có quyền");
            }
            $this->assertSame(count(PermissionCatalog::all()), Role::where('organization_id', $orgId)->where('name', PermissionCatalog::OWNER)->first()->permissions()->count());
        }
    }

    public function test_provisioning_is_idempotent_and_keeps_customizations(): void
    {
        $role = Role::where('organization_id', Organization::DEFAULT_ID)->where('name', 'Employee')->first();
        $role->syncPermissions(['dashboard.view']);   // tổ chức tự chỉnh
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(14, Role::where('organization_id', Organization::DEFAULT_ID)->count());
        $this->assertSame(['dashboard.view'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_changing_permissions_in_one_organization_does_not_affect_another(): void
    {
        $orgB = $this->orgB();
        $roleA = Role::where('organization_id', Organization::DEFAULT_ID)->where('name', 'Sales Employee')->first();
        $roleB = Role::where('organization_id', $orgB->id)->where('name', 'Sales Employee')->first();
        $this->assertNotSame($roleA->id, $roleB->id);

        $roleA->givePermissionTo('customers.delete');
        $this->assertTrue($roleA->fresh()->hasPermissionTo('customers.delete'));
        $this->assertFalse($roleB->fresh()->hasPermissionTo('customers.delete'));
    }

    public function test_same_user_has_different_roles_in_different_organizations(): void
    {
        $orgB = $this->orgB();
        $user = $this->userWithRole('Sales Employee');   // tổ chức A: nhân viên bán hàng
        app(CurrentOrganization::class)->run($orgB->id, function () use ($user, $orgB) {
            $user->organizations()->attach($orgB->id, ['status' => 'active', 'joined_at' => now()]);
            $user->assignRole('Organization Administrator');   // tổ chức B: quản trị
        });
        $registrar = app(PermissionRegistrar::class);

        $this->actingAs($user)->get(route('settings.edit'))->assertForbidden();   // đang ở A

        $user->forceFill(['current_organization_id' => $orgB->id])->save();
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $registrar->forgetCachedPermissions();
        $this->actingAs($user->fresh())->get(route('settings.edit'))->assertOk();   // chuyển sang B
    }

    public function test_platform_admin_has_full_access_in_every_organization(): void
    {
        $orgB = $this->orgB();
        $platform = User::factory()->create(['status' => 'active']);
        $platform->forceFill(['is_platform_admin' => true])->save();   // không có vai trò nào

        $this->actingAs($platform)->get(route('roles.index'))->assertOk();
        $this->assertTrue($platform->can('customers.delete'));
        app(CurrentOrganization::class)->run($orgB->id, fn () => $this->assertTrue($platform->can('settings.manage')));
    }

    public function test_roles_page_only_shows_and_edits_current_organization_roles(): void
    {
        $orgB = $this->orgB();
        Role::where('organization_id', $orgB->id)->where('name', 'Employee')->first()->update(['name' => 'Vai trò riêng của B']);
        $roleB = Role::where('organization_id', $orgB->id)->where('name', 'Vai trò riêng của B')->first();
        $this->actingAs($this->admin());

        $this->get(route('roles.index'))->assertOk()->assertDontSee('Vai trò riêng của B');
        $this->get(route('roles.edit', $roleB))->assertNotFound();
        $this->put(route('roles.update', $roleB), ['permissions' => ['customers.view']])->assertNotFound();
        $this->delete(route('roles.destroy', $roleB))->assertNotFound();
    }

    public function test_custom_role_names_are_unique_per_organization_only(): void
    {
        $orgB = $this->orgB();
        app(CurrentOrganization::class)->run($orgB->id, fn () => Role::create(['name' => 'Cộng tác viên', 'guard_name' => 'web']));
        $this->actingAs($this->admin());

        $this->post(route('roles.store'), ['name' => 'Cộng tác viên', 'permissions' => ['dashboard.view']])->assertSessionHasNoErrors();
        $this->assertSame(Organization::DEFAULT_ID, (int) Role::where('organization_id', Organization::DEFAULT_ID)->where('name', 'Cộng tác viên')->value('organization_id'));
        $this->post(route('roles.store'), ['name' => 'Cộng tác viên'])->assertSessionHasErrors('name');
    }

    public function test_owner_role_is_protected(): void
    {
        $this->actingAs($this->admin());
        $owner = Role::where('organization_id', Organization::DEFAULT_ID)->where('name', PermissionCatalog::OWNER)->first();
        $this->put(route('roles.update', $owner), ['permissions' => []])->assertStatus(422);
        $this->delete(route('roles.destroy', $owner))->assertStatus(422);
    }

    public function test_organization_administrator_cannot_grant_or_edit_owner(): void
    {
        $orgAdmin = $this->userWithRole('Organization Administrator');
        $owner = $this->userWithRole(PermissionCatalog::OWNER);
        $this->actingAs($orgAdmin);

        $this->get(route('users.edit', $owner))->assertForbidden();
        $this->delete(route('users.destroy', $owner))->assertForbidden();
        $member = $this->userWithRole('Employee');
        $this->put(route('users.update', $member), ['name' => $member->name, 'email' => $member->email, 'status' => 'active', 'role' => PermissionCatalog::OWNER])
            ->assertSessionHasErrors('role');
        $this->assertFalse($member->fresh()->hasRole(PermissionCatalog::OWNER));
    }

    public function test_owner_can_grant_owner_but_cannot_remove_own_owner_role(): void
    {
        $owner = $this->userWithRole(PermissionCatalog::OWNER);
        $member = $this->userWithRole('Employee');
        $this->actingAs($owner);

        $this->put(route('users.update', $member), ['name' => $member->name, 'email' => $member->email, 'status' => 'active', 'role' => PermissionCatalog::OWNER])
            ->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->hasRole(PermissionCatalog::OWNER));

        $this->put(route('users.update', $owner), ['name' => $owner->name, 'email' => $owner->email, 'status' => 'active', 'role' => 'Employee'])
            ->assertSessionHas('error');
        $this->assertTrue($owner->fresh()->hasRole(PermissionCatalog::OWNER));
    }

    public function test_platform_admin_account_cannot_be_edited_by_organization_users(): void
    {
        $platform = $this->admin();
        $owner = $this->userWithRole(PermissionCatalog::OWNER);
        $this->actingAs($owner)->get(route('users.edit', $platform))->assertForbidden();
        $this->actingAs($owner)->delete(route('users.destroy', $platform))->assertForbidden();
    }

    public function test_role_based_restrictions_still_apply(): void
    {
        $auditor = $this->userWithRole('Read-only Auditor');
        $this->actingAs($auditor);
        $this->get(route('customers.index'))->assertOk();
        $this->get(route('customers.create'))->assertForbidden();
        $this->get(route('activity-logs.index'))->assertOk();   // kiểm toán được xem nhật ký
        $customer = Customer::create(['name' => 'Khách X', 'type' => 'individual', 'status' => 'active']);
        $this->delete(route('customers.destroy', $customer))->assertForbidden();
    }
}
