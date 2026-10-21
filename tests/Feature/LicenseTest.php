<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Organization;
use App\Models\SoftwareLicense;
use App\Models\User;
use App\Services\AssetService;
use App\Services\LicenseService;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Bước 5.6: giấy phép phần mềm (khóa mã hóa, số chỗ, gán người / thiết bị), nhắc hết hạn và nhắc tài sản mượn quá hạn. */
class LicenseTest extends TestCase
{
    private const KEY = 'ABCDE-12345-FGHIJ-67890';

    private function newLicense(User $by, array $data = []): SoftwareLicense
    {
        $this->actingAs($by)->post(route('licenses.store'), $data + ['name' => 'Microsoft 365 Business', 'license_type' => 'subscription', 'seats' => 2,
            'billing_cycle' => 'yearly', 'cost' => 6000000, 'expires_at' => now()->addYear()->toDateString(), 'license_key' => self::KEY])->assertSessionHasNoErrors();

        return SoftwareLicense::latest('id')->firstOrFail();
    }

    public function test_key_is_encrypted_hidden_and_revealed_only_to_managers(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $license = $this->newLicense($owner);

        $this->assertNotSame(self::KEY, DB::table('software_licenses')->where('id', $license->id)->value('license_key'));
        $this->assertSame(self::KEY, $license->license_key);
        $this->assertArrayNotHasKey('license_key', $license->toArray());
        $this->assertStringNotContainsString(self::KEY, ActivityLog::all()->toJson());

        $this->get(route('licenses.show', $license))->assertOk()->assertDontSee(self::KEY);
        $this->post(route('licenses.reveal', $license))->assertRedirect()->assertSessionHas('license_key', self::KEY);
        $this->assertTrue(ActivityLog::where('action', 'viewed_secret')->where('subject_id', $license->id)->exists());

        // Sửa không nhập khóa → giữ khóa cũ; tick xóa → xóa
        $this->put(route('licenses.update', $license), ['name' => 'Microsoft 365', 'license_type' => 'subscription', 'seats' => 3, 'billing_cycle' => 'yearly'])->assertSessionHasNoErrors();
        $this->assertSame(self::KEY, $license->fresh()->license_key);

        $accountant = $this->userWithRole('Accountant');
        $this->actingAs($accountant)->get(route('licenses.index'))->assertOk()->assertSee('Microsoft 365');
        $this->get(route('licenses.show', $license))->assertOk()->assertDontSee(__('Hiện khóa'));
        $this->post(route('licenses.reveal', $license))->assertForbidden();
        $this->actingAs($this->userWithRole('Employee'))->get(route('licenses.index'))->assertForbidden();

        $this->actingAs($owner)->put(route('licenses.update', $license), ['name' => 'Microsoft 365', 'license_type' => 'subscription', 'seats' => 3, 'billing_cycle' => 'yearly', 'clear_key' => 1]);
        $this->assertNull($license->fresh()->license_key);
    }

    public function test_seats_assignment_and_release(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $staff = $this->userWithRole('Employee');
        $other = $this->userWithRole('Employee');
        $license = $this->newLicense($owner);
        $asset = app(AssetService::class)->create(['name' => 'Laptop', 'purchase_cost' => 1], $owner);

        $this->post(route('licenses.assign', $license), [])->assertSessionHasErrors('user_id');                                    // phải chọn một
        $this->post(route('licenses.assign', $license), ['user_id' => $staff->id, 'asset_id' => $asset->id])->assertSessionHasErrors('user_id');
        $this->post(route('licenses.assign', $license), ['user_id' => $staff->id])->assertSessionHas('success');
        $this->post(route('licenses.assign', $license), ['user_id' => $staff->id])->assertSessionHasErrors('user_id');            // trùng người
        $this->post(route('licenses.assign', $license), ['asset_id' => $asset->id])->assertSessionHas('success');
        $this->post(route('licenses.assign', $license), ['user_id' => $other->id])->assertSessionHasErrors('user_id');            // hết chỗ
        $this->assertSame(2, $license->activeAssignments()->count());

        $this->put(route('licenses.update', $license), ['name' => 'M365', 'license_type' => 'subscription', 'seats' => 1, 'billing_cycle' => 'yearly'])->assertSessionHasErrors('seats');
        $this->delete(route('licenses.destroy', $license))->assertStatus(422);
        $this->get(route('assets.show', $asset))->assertSee('Microsoft 365 Business');

        $this->actingAs($staff)->get(route('assets.mine'))->assertOk()->assertSee('Microsoft 365 Business')->assertDontSee(self::KEY);

        $assignment = $license->activeAssignments()->where('user_id', $staff->id)->first();
        $this->actingAs($owner)->post(route('licenses.release', [$license, $assignment]))->assertSessionHas('success');
        $this->post(route('licenses.release', [$license, $assignment]))->assertStatus(422);
        $this->post(route('licenses.assign', $license), ['user_id' => $other->id])->assertSessionHas('success');

        $expired = $this->newLicense($owner, ['name' => 'Adobe hết hạn', 'expires_at' => now()->subDay()->toDateString()]);
        $this->post(route('licenses.assign', $expired), ['user_id' => $staff->id])->assertSessionHasErrors('user_id');
    }

    public function test_expiry_reminders_follow_stages_without_duplicates(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $responsible = $this->userWithRole('Employee');
        $license = $this->newLicense($owner, ['expires_at' => now()->addDays(25)->toDateString(), 'owner_id' => $responsible->id]);
        $service = app(LicenseService::class);
        $count = fn () => $responsible->notifications()->where('data->kind', 'license_expiring')->count();

        $service->runReminders();
        $this->assertSame([1, 30], [$count(), $license->fresh()->reminded_stage]);
        $service->runReminders();
        $this->assertSame(1, $count());                                                   // không nhắc trùng

        $license->forceFill(['expires_at' => now()->addDays(5)])->saveQuietly();
        $service->runReminders();
        $this->assertSame([2, 7], [$count(), $license->fresh()->reminded_stage]);
        $license->forceFill(['expires_at' => now()->subDay()])->saveQuietly();
        $service->runReminders();
        $this->assertSame([3, 0], [$count(), $license->fresh()->reminded_stage]);

        // Gia hạn qua form → đặt lại mốc nhắc
        $this->actingAs($owner)->put(route('licenses.update', $license), ['name' => 'M365', 'license_type' => 'subscription', 'seats' => 2, 'billing_cycle' => 'yearly',
            'expires_at' => now()->addYear()->toDateString()]);
        $this->assertNull($license->fresh()->reminded_stage);
        $this->assertSame(0, $owner->notifications()->where('data->kind', 'license_expiring')->count());   // đã có người phụ trách riêng
        $this->artisan('crm:asset-reminders')->assertSuccessful();
    }

    public function test_overdue_loan_reminder_once_per_day(): void
    {
        $manager = $this->userWithRole('Inventory Manager');
        $borrower = $this->userWithRole('Employee');
        $assets = app(AssetService::class);
        $asset = $assets->create(['name' => 'Máy chiếu', 'purchase_cost' => 1], $manager);
        $assets->act($asset, 'borrow', ['target_user_id' => $borrower->id, 'due_back_at' => now()->subDays(2)->toDateString()], $manager);

        $r = app(LicenseService::class)->runReminders();
        $this->assertSame(1, $r['loans']);
        $this->assertSame(1, $borrower->notifications()->where('data->kind', 'asset_overdue')->count());
        $this->assertSame(1, $manager->notifications()->where('data->kind', 'asset_overdue')->count());
        $this->assertSame(0, app(LicenseService::class)->runReminders()['loans']);                         // cùng ngày không nhắc lại
    }

    public function test_licenses_are_isolated_between_organizations(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $licenseB = app(CurrentOrganization::class)->run($orgB->id, fn () => SoftwareLicense::create(['name' => 'Phần mềm bí mật B', 'license_type' => 'perpetual',
            'seats' => 1, 'billing_cycle' => 'one_time', 'license_key' => 'SECRET-B']));

        $this->actingAs($owner)->get(route('licenses.index'))->assertOk()->assertDontSee('Phần mềm bí mật B');
        $this->get(route('licenses.show', $licenseB))->assertNotFound();
        $this->post(route('licenses.reveal', $licenseB))->assertNotFound();
        $this->post(route('licenses.assign', $licenseB), ['user_id' => $owner->id])->assertNotFound();
    }
}
