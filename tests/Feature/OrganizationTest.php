<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    private function secondOrganization(): Organization
    {
        return Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
    }

    public function test_default_organization_exists_and_new_records_join_it(): void
    {
        $this->assertTrue(Organization::whereKey(Organization::DEFAULT_ID)->exists());
        $this->actingAs($this->admin());

        $customer = Customer::create(['name' => 'Khách A', 'type' => 'individual', 'status' => 'potential']);
        $this->assertSame(Organization::DEFAULT_ID, (int) $customer->organization_id);
        $this->assertSame(Organization::DEFAULT_ID, (int) $customer->organization->id);
    }

    public function test_records_follow_the_current_organization_context(): void
    {
        $orgB = $this->secondOrganization();
        $customer = app(CurrentOrganization::class)->run($orgB->id,
            fn () => Customer::create(['name' => 'Khách B', 'type' => 'individual', 'status' => 'potential']));

        $this->assertSame($orgB->id, (int) $customer->organization_id);
        // Ra khỏi ngữ cảnh thì quay về tổ chức trước đó
        $this->assertSame(Organization::DEFAULT_ID, app(CurrentOrganization::class)->id());
    }

    public function test_organization_id_cannot_be_mass_assigned(): void
    {
        $orgB = $this->secondOrganization();
        $this->actingAs($this->admin());

        $customer = Customer::create(['name' => 'Khách X', 'type' => 'individual', 'status' => 'potential', 'organization_id' => $orgB->id]);
        $this->assertSame(Organization::DEFAULT_ID, (int) $customer->organization_id, 'organization_id gửi lên phải bị bỏ qua');

        $customer->fill(['organization_id' => $orgB->id])->save();
        $this->assertSame(Organization::DEFAULT_ID, (int) $customer->fresh()->organization_id);
    }

    public function test_unique_values_are_scoped_per_organization(): void
    {
        $orgB = $this->secondOrganization();
        Tag::create(['name' => 'Nhãn kiểm thử']);
        app(CurrentOrganization::class)->run($orgB->id, fn () => Tag::create(['name' => 'Nhãn kiểm thử']));   // khác tổ chức: hợp lệ
        $this->assertSame(1, Tag::where('name', 'Nhãn kiểm thử')->count());   // bộ lọc tổ chức: chỉ thấy bản của mình
        $this->assertSame(2, Tag::withoutOrganizationScope()->where('name', 'Nhãn kiểm thử')->count());

        $this->expectException(UniqueConstraintViolationException::class);
        Tag::create(['name' => 'Nhãn kiểm thử']);   // trùng trong cùng tổ chức: bị chặn ở tầng CSDL
    }

    public function test_new_users_become_members_of_the_current_organization(): void
    {
        $orgB = $this->secondOrganization();
        $user = app(CurrentOrganization::class)->run($orgB->id, fn () => User::factory()->create());

        $this->assertTrue($user->belongsToOrganization($orgB->id));
        $this->assertFalse($user->belongsToOrganization(Organization::DEFAULT_ID));
        $this->assertSame($orgB->id, (int) $user->current_organization_id);
    }

    public function test_organization_with_data_cannot_be_hard_deleted(): void
    {
        $this->actingAs($this->admin());
        Customer::create(['name' => 'Khách A', 'type' => 'individual', 'status' => 'potential']);

        $this->expectException(QueryException::class);
        Organization::find(Organization::DEFAULT_ID)->forceDelete();
    }

    public function test_platform_level_activity_logs_may_have_no_organization(): void
    {
        $this->assertTrue((new ActivityLog)->organizationIsOptional());
        $this->assertFalse((new Customer)->organizationIsOptional());
    }

    public function test_setup_makes_the_first_admin_owner_of_default_organization(): void
    {
        Organization::whereKey(Organization::DEFAULT_ID)->update(['owner_id' => null]);
        $this->post(route('setup.store'), [
            'name' => 'Chủ hệ thống', 'email' => 'owner@example.com',
            'password' => 'MatKhau2026Manh', 'password_confirmation' => 'MatKhau2026Manh',
        ])->assertRedirect(route('dashboard'));

        $owner = User::where('email', 'owner@example.com')->first();
        $this->assertSame($owner->id, Organization::find(Organization::DEFAULT_ID)->owner_id);
        $this->assertTrue($owner->belongsToOrganization(Organization::DEFAULT_ID));
    }
}
