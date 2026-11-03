<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Invitation;
use App\Models\LoginEvent;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use App\Services\Auth\EmailOtp;
use App\Support\Tenancy\OrganizationProvisioner;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InviteEmailOtpTest extends TestCase
{
    private const PW = 'Matkhau2026x';

    private function lastCode(string $email): string
    {
        $code = null;
        Notification::assertSentOnDemand(EmailOtpNotification::class, function ($n, $channels, $notifiable) use ($email, &$code) {
            if (($notifiable->routes['mail'] ?? null) === $email) {
                $code = $n->code;
            }

            return true;
        });
        $this->assertNotNull($code, 'Không thấy mã gửi tới '.$email);

        return $code;
    }

    /** Tạo lời mời trực tiếp, trả về [Invitation, token]. */
    private function invitation(array $attr = []): array
    {
        $inv = new Invitation($attr + ['role' => 'Sales Employee', 'max_uses' => 1, 'expires_at' => now()->addDays(7)]);
        $token = $inv->issueToken();
        $inv->save();

        return [$inv, $token];
    }

    private function joinPayload(array $over = []): array
    {
        return $over + ['name' => 'Nguyễn Văn Mới', 'email' => 'moi@example.com', 'phone' => '0901234567',
            'password' => self::PW, 'password_confirmation' => self::PW, 'terms' => '1'];
    }

    // ---------------- Dịch vụ mã email ----------------

    public function test_email_otp_is_one_time_and_destroyed_after_too_many_attempts(): void
    {
        Notification::fake();
        $this->assertSame('sent', EmailOtp::send('login', 'user:1', 'a@example.com'));
        $this->assertSame('cooldown', EmailOtp::send('login', 'user:1', 'a@example.com'));
        $code = $this->lastCode('a@example.com');
        $this->assertFalse(EmailOtp::verify('login', 'user:2', $code));       // khác chủ thể
        $this->assertTrue(EmailOtp::verify('login', 'user:1', substr($code, 0, 3).' '.substr($code, 3)));   // cho phép dấu cách
        $this->assertFalse(EmailOtp::verify('login', 'user:1', $code));       // dùng một lần

        $this->travel(2)->minutes();
        EmailOtp::send('login', 'user:1', 'a@example.com');
        $fresh = null;
        Notification::assertSentOnDemand(EmailOtpNotification::class, function ($n) use (&$fresh) { $fresh = $n->code; return true; });
        for ($i = 0; $i < EmailOtp::MAX_ATTEMPTS; $i++) {
            $this->assertFalse(EmailOtp::verify('login', 'user:1', $fresh === '000000' ? '111111' : '000000'));
        }
        $this->assertFalse(EmailOtp::verify('login', 'user:1', $fresh));      // đã bị hủy

        $this->travel(2)->minutes();
        EmailOtp::send('login', 'user:1', 'a@example.com');
        Notification::assertSentOnDemand(EmailOtpNotification::class, function ($n) use (&$fresh) { $fresh = $n->code; return true; });
        $this->travel(11)->minutes();
        $this->assertFalse(EmailOtp::verify('login', 'user:1', $fresh));      // hết hạn
        $this->assertSame('ng****@example.com', EmailOtp::mask('nguyen@example.com'));
    }

    // ---------------- Đăng nhập bằng mã email ----------------

    public function test_user_with_email_otp_must_enter_code_sent_by_email(): void
    {
        Notification::fake();
        $user = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);
        $user->forceFill(['email_otp_enabled' => true])->save();

        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('nv***@example.com')->assertDontSee(__('Mất điện thoại? Dùng mã khôi phục'));
        $code = $this->lastCode('nv@example.com');

        $this->post(route('two-factor.verify'), ['recovery_code' => 'ABCDE-12345'])->assertSessionHasErrors();   // email mode: không có mã khôi phục
        $this->post(route('two-factor.verify'), ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('email_otp', LoginEvent::where('user_id', $user->id)->where('event', 'login')->latest('id')->value('method'));
    }

    public function test_global_require_setting_applies_to_everyone_and_needs_working_mail(): void
    {
        Notification::fake();
        $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);
        $payload = ['company_name' => 'TOP CRM', 'brand_color' => '#D9823B', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND', 'date_format' => 'd/m/Y', 'default_tax_rate' => 10, 'require_email_otp' => '1'];
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('settings.update'), $payload)->assertSessionHasErrors('require_email_otp');   // chưa có email gửi đi
        $this->assertSame('0', Setting::get('require_email_otp', '0'));

        config(['mail.default' => 'smtp', 'mail.from.address' => 'crm@example.com']);
        $this->actingAs($admin)->put(route('settings.update'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('1', Setting::get('require_email_otp', '0'));
        auth()->logout();

        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
    }

    public function test_user_enables_email_otp_only_after_proving_the_mailbox_and_can_disable(): void
    {
        Notification::fake();
        $user = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);

        $this->actingAs($user)->post(route('email-otp.start'), ['password' => 'sai'])->assertSessionHasErrors('password');
        $this->actingAs($user)->post(route('email-otp.start'), ['password' => 'password'])->assertRedirect(route('profile.security'));
        $this->assertFalse($user->fresh()->email_otp_enabled);   // chưa bật cho tới khi nhập mã
        $code = $this->lastCode('nv@example.com');

        $this->actingAs($user)->post(route('email-otp.confirm'), ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('email_otp_code');
        $this->actingAs($user)->post(route('email-otp.confirm'), ['code' => $code])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->email_otp_enabled);
        $this->actingAs($user)->get(route('profile.security'))->assertOk();

        Setting::put(['require_email_otp' => '1'], 'security');
        $this->actingAs($user)->delete(route('email-otp.disable'), ['password' => 'password'])->assertStatus(422);
        Setting::put(['require_email_otp' => '0'], 'security');
        $this->actingAs($user)->delete(route('email-otp.disable'), ['password' => 'password'])->assertRedirect(route('profile.security'));
        $this->assertFalse($user->fresh()->email_otp_enabled);
    }

    // ---------------- Quản lý link mời ----------------

    public function test_admin_creates_invitation_link_shown_once_and_can_revoke(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('invitations.index'))->assertOk();
        $this->actingAs($admin)->post(route('invitations.store'), ['role' => 'Sales Employee', 'days' => 7, 'max_uses' => 3, 'note' => 'Đợt tháng 11'])
            ->assertRedirect(route('invitations.index'))->assertSessionHas('invite_created');
        $inv = Invitation::sole();
        $this->assertSame(3, (int) $inv->max_uses);
        $this->assertSame(64, strlen($inv->token_hash));
        $url = session('invite_created')['url'];
        $this->assertStringNotContainsString($inv->token_hash, $url);              // link chứa token gốc, DB chỉ giữ băm
        $this->actingAs($admin)->get(route('invitations.index'))->assertSee('data:image/png;base64', false);

        $this->actingAs($admin)->delete(route('invitations.destroy', $inv))->assertRedirect();
        $this->assertNotNull($inv->fresh()->revoked_at);
    }

    public function test_invitation_role_limits_and_permissions(): void
    {
        $orgAdmin = $this->userWithRole('Organization Administrator', ['email' => 'qt@example.com']);
        $this->actingAs($orgAdmin)->post(route('invitations.store'), ['role' => PermissionCatalog::OWNER, 'days' => 7, 'max_uses' => 1])->assertSessionHasErrors('role');
        $this->actingAs($orgAdmin)->post(route('invitations.store'), ['role' => 'Organization Administrator', 'days' => 7, 'max_uses' => 1])->assertSessionHasErrors('role');
        $this->actingAs($orgAdmin)->post(route('invitations.store'), ['role' => 'Sales Employee', 'days' => 31, 'max_uses' => 1])->assertSessionHasErrors('days');
        $this->actingAs($orgAdmin)->post(route('invitations.store'), ['role' => 'Sales Employee', 'days' => 7, 'max_uses' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, Invitation::count());

        $owner = $this->admin();
        $this->actingAs($owner)->post(route('invitations.store'), ['role' => 'Organization Administrator', 'days' => 1, 'max_uses' => 1])->assertSessionHasNoErrors();

        $sales = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $this->actingAs($sales)->get(route('invitations.index'))->assertForbidden();
        $this->actingAs($sales)->post(route('invitations.store'), ['role' => 'Sales Employee', 'days' => 7, 'max_uses' => 1])->assertForbidden();
    }

    public function test_invitations_of_other_organization_are_unreachable(): void
    {
        $owner = $this->admin();
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        $invB = app(CurrentOrganization::class)->run($orgB->id, function () {
            $inv = new Invitation(['role' => 'Sales Employee', 'max_uses' => 1, 'expires_at' => now()->addDay(), 'note' => 'Lời mời của B']);
            $inv->issueToken();
            $inv->save();

            return $inv;
        });
        $owner->forceFill(['is_platform_admin' => false])->save();
        $this->actingAs($owner)->get(route('invitations.index'))->assertDontSee('Lời mời của B');
        $this->actingAs($owner)->delete(route('invitations.destroy', $invB->id))->assertNotFound();
        $this->assertNull($invB->fresh()->revoked_at);
    }

    // ---------------- Đăng ký qua link mời ----------------

    public function test_join_flow_creates_account_in_inviting_organization_after_email_code(): void
    {
        Notification::fake();
        $creator = $this->admin();
        [$inv, $token] = $this->invitation(['max_uses' => 2]);
        $inv->forceFill(['created_by' => $creator->id])->save();

        $this->get(route('join.show', $token))->assertOk()->assertSee(__('Đăng ký tài khoản'));
        $this->post(route('join.submit', $token), $this->joinPayload())->assertRedirect(route('join.verify', $token));
        $this->assertDatabaseMissing('users', ['email' => 'moi@example.com']);      // chưa tạo khi chưa xác thực
        $this->get(route('join.verify', $token))->assertOk()->assertSee('mo***@example.com');
        $code = $this->lastCode('moi@example.com');

        $this->post(route('join.verify.store', $token), ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post(route('join.verify.store', $token), ['code' => $code])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'moi@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('Sales Employee'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($inv->id, (int) $user->invited_via_id);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(self::PW, $user->password));
        $this->assertTrue($user->organizations()->whereKey($inv->organization_id)->exists());
        $this->assertTrue(EmployeeProfile::where('user_id', $user->id)->exists());
        $this->assertSame(1, (int) $inv->fresh()->uses);
        $this->assertSame('invitation', LoginEvent::where('user_id', $user->id)->latest('id')->value('method'));
        Notification::assertSentTo($creator, \App\Notifications\CrmNotification::class);
    }

    public function test_join_rejects_existing_email_weak_password_and_honeypot(): void
    {
        Notification::fake();
        $this->userWithRole('Sales Employee', ['email' => 'cu@example.com']);
        [, $token] = $this->invitation();

        $this->post(route('join.submit', $token), $this->joinPayload(['email' => 'cu@example.com']))->assertSessionHasErrors('email');
        $this->post(route('join.submit', $token), $this->joinPayload(['password' => 'abc12345', 'password_confirmation' => 'abc12345']))->assertSessionHasErrors('password');
        $this->post(route('join.submit', $token), $this->joinPayload(['website' => 'http://spam']))->assertRedirect(route('join.show', $token));
        Notification::assertNothingSent();
        $this->get(route('join.verify', $token))->assertRedirect(route('join.show', $token));   // không có phiên đăng ký
    }

    public function test_unusable_links_cannot_register(): void
    {
        Notification::fake();
        [, $expired] = $this->invitation(['expires_at' => now()->subMinute()]);
        [, $used] = $this->invitation(['max_uses' => 1]);
        Invitation::latest('id')->first()->forceFill(['uses' => 1])->save();
        [$revokedInv, $revoked] = $this->invitation();
        $revokedInv->forceFill(['revoked_at' => now()])->save();

        foreach ([$expired, $used, $revoked] as $token) {
            $this->get(route('join.show', $token))->assertOk()->assertSee(__('Link mời không còn hiệu lực'));
            $this->post(route('join.submit', $token), $this->joinPayload())->assertStatus(410);
        }
        $this->get('/join/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/join/ngan')->assertNotFound();
        Notification::assertNothingSent();
    }

    public function test_last_seat_cannot_be_used_twice(): void
    {
        Notification::fake();
        [$inv, $token] = $this->invitation(['max_uses' => 1]);
        $this->post(route('join.submit', $token), $this->joinPayload());
        $code = $this->lastCode('moi@example.com');
        $inv->forceFill(['uses' => 1])->save();                   // người khác vừa dùng hết lượt

        $this->post(route('join.verify.store', $token), ['code' => $code])->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'moi@example.com']);
        $this->assertGuest();
    }

    public function test_logged_in_user_is_sent_to_dashboard_from_join_link(): void
    {
        [, $token] = $this->invitation();
        $this->actingAs($this->admin())->get(route('join.show', $token))->assertRedirect(route('dashboard'));
    }
}
