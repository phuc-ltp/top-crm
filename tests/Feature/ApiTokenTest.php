<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    /** Tạo khóa trực tiếp (không qua giao diện) trong tổ chức chỉ định. */
    private function tokenFor(User $user, array $scopes, int $orgId = Organization::DEFAULT_ID, ?\DateTimeInterface $expires = null): string
    {
        $new = $user->createToken('test', $scopes, $expires ?? now()->addDays(30));
        $new->accessToken->forceFill(['organization_id' => $orgId])->save();

        return $new->plainTextToken;
    }

    /** Gọi API bằng khóa như một ứng dụng bên ngoài (không có phiên đăng nhập). */
    private function api(string $token, string $method, string $uri, array $data = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->json($method, $uri, $data);
    }

    private function ownerWithCustomer(): array
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER, ['email' => 'owner@example.com']);
        $customer = $this->customer(['name' => 'Khách A']);

        return [$owner, $customer];
    }

    // ---------------- Tạo khóa ----------------

    public function test_owner_creates_token_shown_once_and_stored_hashed(): void
    {
        [$owner] = $this->ownerWithCustomer();

        $this->actingAs($owner)->post(route('api-tokens.store'), ['name' => 'Website', 'scopes' => ['customers:read'], 'expires_in' => 90, 'password' => 'sai'])
            ->assertSessionHasErrors('password');
        $response = $this->actingAs($owner)->post(route('api-tokens.store'), ['name' => 'Website', 'scopes' => ['customers:read'], 'expires_in' => 90, 'password' => 'password']);
        $response->assertRedirect(route('api-tokens.index'))->assertSessionHas('api_token_plain');

        $plain = decrypt(session('api_token_plain'));
        $this->assertStringStartsWith('topcrm_', explode('|', $plain, 2)[1]);
        $row = DB::table('personal_access_tokens')->first();
        $this->assertSame(Organization::DEFAULT_ID, (int) $row->organization_id);
        $this->assertSame(hash('sha256', explode('|', $plain, 2)[1]), $row->token);   // chỉ lưu bản băm
        $this->assertEqualsWithDelta(now()->addDays(90)->timestamp, strtotime($row->expires_at), 5);

        // Hiển thị đúng một lần
        $this->actingAs($owner)->get(route('api-tokens.index'))->assertSee($plain);
        $this->actingAs($owner)->get(route('api-tokens.index'))->assertDontSee($plain)->assertSee('Website');

        // Không lộ trong nhật ký hoạt động
        $this->assertFalse(DB::table('activity_logs')->where('description', 'like', '%'.explode('|', $plain, 2)[1].'%')->exists());
    }

    public function test_only_users_with_api_permission_can_create_tokens(): void
    {
        $sales = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $this->actingAs($sales)->post(route('api-tokens.store'), ['name' => 'X', 'scopes' => ['customers:read'], 'expires_in' => 30, 'password' => 'password'])->assertForbidden();
        $this->actingAs($sales)->get(route('api-tokens.index'))->assertOk()->assertDontSee(__('Tạo khóa mới'));
        $this->assertSame(0, ApiToken::count());
    }

    public function test_cannot_grant_scope_beyond_own_permissions(): void
    {
        $marketing = $this->userWithRole('Marketing Employee', ['email' => 'mk@example.com']);
        $marketing->givePermissionTo('api.use');   // được tạo khóa, nhưng không có quyền xem cơ hội

        $this->actingAs($marketing)->post(route('api-tokens.store'), ['name' => 'X', 'scopes' => ['opportunities:read'], 'expires_in' => 30, 'password' => 'password'])
            ->assertSessionHasErrors('scopes.0');
        $this->actingAs($marketing)->post(route('api-tokens.store'), ['name' => 'X', 'scopes' => ['leads:read'], 'expires_in' => 30, 'password' => 'password'])
            ->assertSessionHasNoErrors();
    }

    public function test_expiry_must_be_from_allowed_list_and_active_tokens_are_limited(): void
    {
        [$owner] = $this->ownerWithCustomer();
        $this->actingAs($owner)->post(route('api-tokens.store'), ['name' => 'X', 'scopes' => ['customers:read'], 'expires_in' => 99999, 'password' => 'password'])
            ->assertSessionHasErrors('expires_in');

        for ($i = 0; $i < ApiToken::MAX_ACTIVE_PER_USER; $i++) {
            $this->tokenFor($owner, ['customers:read']);
        }
        $this->actingAs($owner)->post(route('api-tokens.store'), ['name' => 'X', 'scopes' => ['customers:read'], 'expires_in' => 30, 'password' => 'password'])
            ->assertSessionHas('error');
        $this->assertSame(ApiToken::MAX_ACTIVE_PER_USER, ApiToken::count());
    }

    // ---------------- Dùng khóa ----------------

    public function test_token_reads_data_and_records_usage(): void
    {
        [$owner, $customer] = $this->ownerWithCustomer();
        $token = $this->tokenFor($owner, ['customers:read']);

        $this->api($token, 'GET', '/api/v1/customers')->assertOk()->assertJsonPath('data.0.name', 'Khách A');
        $this->api($token, 'GET', '/api/v1/customers/'.$customer->id)->assertOk()->assertJsonPath('data.id', $customer->id);
        $row = ApiToken::first();
        $this->assertNotNull($row->last_used_at);
        $this->assertSame('127.0.0.1', $row->last_used_ip);
    }

    public function test_requests_without_valid_token_are_rejected(): void
    {
        [$owner] = $this->ownerWithCustomer();
        $this->getJson('/api/v1/customers')->assertUnauthorized();
        $this->api('1|topcrm_khongdung', 'GET', '/api/v1/customers')->assertUnauthorized();

        $expired = $this->tokenFor($owner, ['customers:read'], Organization::DEFAULT_ID, now()->subMinute());
        $this->api($expired, 'GET', '/api/v1/customers')->assertUnauthorized();

        $token = $this->tokenFor($owner, ['customers:read']);
        ApiToken::latest('id')->first()->delete();   // thu hồi
        $this->api($token, 'GET', '/api/v1/customers')->assertUnauthorized();
    }

    public function test_scopes_limit_what_a_token_can_do(): void
    {
        [$owner, $customer] = $this->ownerWithCustomer();
        $token = $this->tokenFor($owner, ['customers:read']);

        $this->api($token, 'POST', '/api/v1/customers', ['name' => 'Mới', 'type' => 'individual', 'status' => 'active'])->assertForbidden();
        $this->api($token, 'DELETE', '/api/v1/customers/'.$customer->id)->assertForbidden();
        $this->api($token, 'GET', '/api/v1/leads')->assertForbidden();
        $this->api($token, 'GET', '/api/v1/stats')->assertForbidden();
        $this->assertNotSoftDeleted($customer);

        $writer = $this->tokenFor($owner, ['customers:write']);
        $this->api($writer, 'POST', '/api/v1/customers', ['name' => 'Khách qua API', 'type' => 'individual', 'status' => 'active'])->assertCreated();
        $this->assertTrue(Customer::where('name', 'Khách qua API')->exists());
    }

    public function test_token_never_exceeds_user_permissions(): void
    {
        $sales = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $mine = $this->customer(['name' => 'Của tôi', 'assigned_to' => $sales->id]);
        $other = $this->customer(['name' => 'Của người khác']);
        $token = $this->tokenFor($sales, ['customers:read', 'customers:write']);   // khóa có phạm vi ghi, nhưng nhân viên không có quyền xóa

        $this->api($token, 'GET', '/api/v1/customers/'.$other->id)->assertForbidden();   // không có quyền xem tất cả
        $this->api($token, 'GET', '/api/v1/customers/'.$mine->id)->assertOk();
        $this->api($token, 'DELETE', '/api/v1/customers/'.$mine->id)->assertForbidden();
        $this->assertNotSoftDeleted($mine);

        // Quyền bị thu hẹp sau khi tạo khóa → khóa bị thu hẹp theo
        app(CurrentOrganization::class)->run(Organization::DEFAULT_ID, fn () => \Spatie\Permission\Models\Role::where('organization_id', Organization::DEFAULT_ID)->where('name', 'Sales Employee')->firstOrFail()->revokePermissionTo('customers.view'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->api($token, 'GET', '/api/v1/customers/'.$mine->id)->assertForbidden();
    }

    public function test_token_is_bound_to_its_organization(): void
    {
        [$owner, $customerA] = $this->ownerWithCustomer();
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        app(\App\Support\Tenancy\RoleProvisioner::class)->provision($orgB->id);
        $owner->organizations()->attach($orgB->id, ['status' => 'active', 'joined_at' => now()]);
        $customerB = app(CurrentOrganization::class)->run($orgB->id, function () use ($owner) {
            $owner->assignRole(\App\Support\PermissionCatalog::OWNER);

            return Customer::create(['name' => 'Khách BÍ MẬT B', 'type' => 'individual', 'status' => 'active']);
        });

        // Người dùng đang chọn tổ chức B trên web, nhưng khóa thuộc tổ chức A → chỉ thấy dữ liệu A
        $owner->forceFill(['current_organization_id' => $orgB->id])->saveQuietly();
        $tokenA = $this->tokenFor($owner, ['customers:read', 'customers:write'], Organization::DEFAULT_ID);

        $list = $this->api($tokenA, 'GET', '/api/v1/customers')->assertOk();
        $list->assertJsonFragment(['name' => 'Khách A'])->assertJsonMissing(['name' => 'Khách BÍ MẬT B']);
        $this->api($tokenA, 'GET', '/api/v1/customers/'.$customerB->id)->assertNotFound();
        $this->api($tokenA, 'DELETE', '/api/v1/customers/'.$customerB->id)->assertNotFound();
        $this->assertNotSoftDeleted($customerB);

        $created = $this->api($tokenA, 'POST', '/api/v1/customers', ['name' => 'Tạo qua khóa A', 'type' => 'individual', 'status' => 'active'])->assertCreated();
        $this->assertSame(Organization::DEFAULT_ID, (int) Customer::withoutGlobalScopes()->find($created->json('data.id'))->organization_id);
    }

    public function test_token_stops_working_when_access_is_lost(): void
    {
        [$owner] = $this->ownerWithCustomer();
        $member = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);
        $token = $this->tokenFor($member, ['customers:read']);
        $this->api($token, 'GET', '/api/v1/customers')->assertOk();

        // Tạm ngưng thành viên
        $member->organizations()->updateExistingPivot(Organization::DEFAULT_ID, ['status' => 'suspended']);
        $this->api($token, 'GET', '/api/v1/customers')->assertForbidden();
        $member->organizations()->updateExistingPivot(Organization::DEFAULT_ID, ['status' => 'active']);
        $this->api($token, 'GET', '/api/v1/customers')->assertOk();

        // Khóa tài khoản
        $member->forceFill(['status' => 'locked'])->saveQuietly();
        $this->api($token, 'GET', '/api/v1/customers')->assertForbidden();
        $member->forceFill(['status' => 'active'])->saveQuietly();

        // Tổ chức bị tạm ngưng
        Organization::whereKey(Organization::DEFAULT_ID)->update(['status' => 'suspended']);
        $this->api($token, 'GET', '/api/v1/customers')->assertForbidden();
        Organization::whereKey(Organization::DEFAULT_ID)->update(['status' => 'active']);

        // Xóa tài khoản → mọi khóa bị thu hồi
        $this->actingAs($owner)->delete(route('users.destroy', $member));
        $this->assertSame(0, ApiToken::where('tokenable_id', $member->id)->count());
        $this->api($token, 'GET', '/api/v1/customers')->assertUnauthorized();
    }

    public function test_token_cannot_be_used_on_web_pages(): void
    {
        [$owner] = $this->ownerWithCustomer();
        $token = $this->tokenFor($owner, ['customers:read']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->withToken($token)->get(route('api-tokens.index'))->assertRedirect(route('login'));
    }

    // ---------------- Thu hồi ----------------

    public function test_revocation_rules(): void
    {
        [$owner] = $this->ownerWithCustomer();
        $member = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);
        $member->givePermissionTo('api.use');
        $other = $this->userWithRole('Sales Employee', ['email' => 'nv2@example.com']);
        $this->tokenFor($member, ['customers:read']);
        $memberToken = ApiToken::latest('id')->first();

        // Nhân viên khác không thu hồi được khóa của người khác
        $this->actingAs($other)->delete(route('api-tokens.destroy', $memberToken->id))->assertForbidden();
        // Quản trị (users.manage) thấy và thu hồi được
        $this->actingAs($owner)->get(route('api-tokens.index'))->assertSee('nv@example.com');
        $this->actingAs($owner)->delete(route('api-tokens.destroy', $memberToken->id))->assertSessionHas('success');
        $this->assertNull(ApiToken::find($memberToken->id));

        // Chủ khóa tự thu hồi
        $this->tokenFor($member, ['customers:read']);
        $own = ApiToken::latest('id')->first();
        $this->actingAs($member)->delete(route('api-tokens.destroy', $own->id))->assertSessionHas('success');
        $this->assertNull(ApiToken::find($own->id));
    }

    public function test_tokens_of_other_organizations_are_invisible(): void
    {
        [$owner] = $this->ownerWithCustomer();
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        app(\App\Support\Tenancy\RoleProvisioner::class)->provision($orgB->id);
        $userB = app(CurrentOrganization::class)->run($orgB->id, fn () => User::factory()->create(['email' => 'b@example.com', 'status' => 'active']));
        $this->tokenFor($userB, ['customers:read'], $orgB->id);
        $tokenB = ApiToken::latest('id')->first();

        $this->actingAs($owner)->get(route('api-tokens.index'))->assertDontSee('b@example.com');
        $this->actingAs($owner)->delete(route('api-tokens.destroy', $tokenB->id))->assertNotFound();
        $this->assertNotNull(ApiToken::find($tokenB->id));
    }

    public function test_web_session_api_still_works(): void
    {
        [$owner] = $this->ownerWithCustomer();
        $this->actingAs($owner)->getJson(route('api.customers.index'))->assertOk()->assertJsonPath('data.0.name', 'Khách A');
        $this->actingAs($owner)->getJson(route('api.stats'))->assertOk();
    }
}
