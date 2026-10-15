<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_viewer_cannot_create_update_or_delete(): void
    {
        $viewer = $this->userWithRole('Read-only Auditor');
        $customer = $this->customer();
        $this->actingAs($viewer);

        $this->get(route('customers.index'))->assertOk();
        $this->get(route('customers.show', $customer))->assertOk();
        $this->get(route('customers.create'))->assertForbidden();
        $this->post(route('customers.store'), ['name' => 'X', 'type' => 'individual', 'status' => 'active'])->assertForbidden();
        $this->put(route('customers.update', $customer), ['name' => 'X', 'type' => 'individual', 'status' => 'active'])->assertForbidden();
        $this->delete(route('customers.destroy', $customer))->assertForbidden();
        $this->get(route('customers.export'))->assertForbidden();
    }

    public function test_sales_staff_only_sees_own_customers(): void
    {
        $staff = $this->userWithRole('Sales Employee');
        $other = $this->userWithRole('Sales Employee');
        $mine = $this->customer(['name' => 'Khách của tôi', 'assigned_to' => $staff->id]);
        $theirs = $this->customer(['name' => 'Khách người khác', 'assigned_to' => $other->id]);

        $this->actingAs($staff);
        $this->get(route('customers.index'))->assertSee('Khách của tôi')->assertDontSee('Khách người khác');
        $this->get(route('customers.show', $mine))->assertOk();
        $this->get(route('customers.show', $theirs))->assertForbidden();
        $this->get(route('customers.edit', $theirs))->assertForbidden();
        $this->getJson(route('api.customers.show', $theirs))->assertForbidden();
        $this->getJson(route('search', ['q' => 'Khách người']))->assertJsonMissing(['title' => 'Khách người khác']);
    }

    public function test_sales_staff_cannot_reassign_customer(): void
    {
        $staff = $this->userWithRole('Sales Employee');
        $other = $this->userWithRole('Sales Employee');
        $this->actingAs($staff)->post(route('customers.store'), [
            'name' => 'Khách mới', 'type' => 'individual', 'status' => 'active', 'assigned_to' => $other->id,
        ])->assertRedirect();

        $this->assertSame($staff->id, Customer::where('name', 'Khách mới')->first()->assigned_to);
    }

    public function test_manager_sees_all_and_can_bulk_assign(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $staff = $this->userWithRole('Sales Employee');
        $c1 = $this->customer(['name' => 'A1']);
        $c2 = $this->customer(['name' => 'A2']);

        $this->actingAs($manager)->get(route('customers.index'))->assertSee('A1')->assertSee('A2');
        $this->post(route('customers.bulk-assign'), ['ids' => [$c1->id, $c2->id], 'assigned_to' => $staff->id])->assertSessionHas('success');
        $this->assertSame(2, Customer::where('assigned_to', $staff->id)->count());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $staff->id]);
    }

    public function test_admin_pages_are_protected(): void
    {
        $staff = $this->userWithRole('Sales Employee');
        $this->actingAs($staff);
        foreach (['users.index', 'roles.index', 'departments.index', 'activity-logs.index', 'settings.edit'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_administrator_cannot_modify_super_admin(): void
    {
        $super = $this->admin();
        $adminUser = $this->userWithRole('Organization Administrator');
        $this->actingAs($adminUser);

        $this->get(route('users.edit', $super))->assertForbidden();
        $this->delete(route('users.destroy', $super))->assertForbidden();
        $this->post(route('users.toggle-status', $super))->assertForbidden();
        // Quản trị tổ chức không gán được vai trò chủ sở hữu cho người khác
        $this->post(route('users.store'), [
            'name' => 'Hacker', 'email' => 'h@example.com', 'status' => 'active', 'role' => 'Organization Owner',
            'password' => 'Abcdef1234', 'password_confirmation' => 'Abcdef1234',
        ])->assertSessionHasErrors('role');
    }

    public function test_marketing_cannot_access_invoices(): void
    {
        $mk = $this->userWithRole('Marketing Employee');
        $this->actingAs($mk)->get(route('invoices.index'))->assertForbidden();
        $this->get(route('campaigns.index'))->assertOk();
    }

    public function test_csrf_protection_is_active_outside_tests(): void
    {
        // Laravel bỏ qua CSRF khi chạy unit test; xác nhận middleware vẫn nằm trong nhóm web
        $groups = app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups();
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $groups['web']);
    }

    public function test_role_permission_editing(): void
    {
        $this->actingAs($this->admin());
        $role = \Spatie\Permission\Models\Role::findByName('Read-only Auditor');
        $this->put(route('roles.update', $role), ['permissions' => ['customers.view', 'customers.view_all', 'customers.create']])->assertRedirect();

        $viewer = $this->userWithRole('Read-only Auditor');
        $this->assertTrue($viewer->fresh()->can('customers.create'));
        $this->assertFalse($viewer->fresh()->can('leads.view'));
    }
}
