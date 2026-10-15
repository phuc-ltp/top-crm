<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireTwoFactor;
use App\Models\LoginEvent;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewDeviceLoginNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\Auth\LoginFlow;
use App\Support\Security\Totp;
use App\Support\Security\TwoFactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    private function member(array $attributes = []): User
    {
        return $this->userWithRole('Sales Employee', $attributes + ['email' => 'nv@example.com']);
    }

    /** Bật 2FA trực tiếp; trả về khóa bí mật và các mã khôi phục. */
    private function enableTwoFactor(User $user): array
    {
        $secret = Totp::generateSecret();
        $codes = TwoFactor::enable($user, $secret, Totp::currentStep() - 5);

        return [$secret, $codes];
    }

    private function currentCode(string $secret): string
    {
        return Totp::codeAt($secret, Totp::currentStep());
    }

    // ---------------- Quy định mật khẩu ----------------

    public function test_password_policy_is_the_same_everywhere(): void
    {
        $user = $this->member();
        $weak = ['current_password' => 'password', 'password' => 'abcdefgh12', 'password_confirmation' => 'abcdefgh12']; // thiếu chữ hoa
        $this->actingAs($user)->put(route('profile.password'), $weak)->assertSessionHasErrors('password');
        $short = ['current_password' => 'password', 'password' => 'Abcdef12', 'password_confirmation' => 'Abcdef12'];   // < 10 ký tự
        $this->actingAs($user)->put(route('profile.password'), $short)->assertSessionHasErrors('password');
        $long = str_repeat('Aa1', 50);   // > 128 ký tự
        $this->actingAs($user)->put(route('profile.password'), ['current_password' => 'password', 'password' => $long, 'password_confirmation' => $long])->assertSessionHasErrors('password');

        $admin = $this->admin();
        $this->actingAs($admin)->put(route('users.password', $user), ['password' => 'abcdefgh12', 'password_confirmation' => 'abcdefgh12'])->assertSessionHasErrors('password');
        $this->actingAs($admin)->put(route('users.password', $user), ['password' => 'Abcdefgh12', 'password_confirmation' => 'Abcdefgh12'])->assertSessionHasNoErrors();
    }

    // ---------------- Đăng nhập 2 lớp ----------------

    public function test_login_without_two_factor_is_unchanged(): void
    {
        $this->member();
        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_two_factor_user_is_not_logged_in_until_code_is_entered(): void
    {
        $user = $this->member();
        [$secret] = $this->enableTwoFactor($user);

        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));   // chưa vào được trang trong

        $this->get(route('two-factor.challenge'))->assertOk()->assertSee(__('Xác thực 2 lớp'));
        $this->post(route('two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('two-factor.verify'), ['code' => $this->currentCode($secret)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('totp', LoginEvent::where('user_id', $user->id)->where('event', 'login')->latest('id')->value('method'));
    }

    public function test_same_code_cannot_be_used_twice(): void
    {
        $user = $this->member();
        [$secret] = $this->enableTwoFactor($user);
        $code = $this->currentCode($secret);

        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password']);
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password']);
        $this->post(route('two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_requires_password_step_first_and_expires(): void
    {
        $user = $this->member();
        [$secret] = $this->enableTwoFactor($user);

        // Không qua bước mật khẩu → không có gì để xác thực
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->post(route('two-factor.verify'), ['code' => $this->currentCode($secret)])->assertRedirect(route('login'));
        $this->assertGuest();

        // Quá thời hạn chờ
        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password']);
        $this->travel(LoginFlow::PENDING_TTL + 5)->seconds();
        $this->post(route('two-factor.verify'), ['code' => Totp::codeAt($secret, Totp::currentStep())])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_two_factor_attempts_are_rate_limited_per_account(): void
    {
        $user = $this->member();
        [$secret] = $this->enableTwoFactor($user);

        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password']);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.verify'), ['code' => '111111']);
        }
        $this->post(route('two-factor.verify'), ['code' => $this->currentCode($secret)])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(5, LoginEvent::where('user_id', $user->id)->where('event', 'two_factor_failed')->count());
        $this->assertTrue(LoginEvent::where('user_id', $user->id)->where('event', 'locked_out')->exists());
    }

    public function test_recovery_code_works_once(): void
    {
        $user = $this->member();
        [, $codes] = $this->enableTwoFactor($user);

        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password']);
        $this->post(route('two-factor.verify'), ['recovery_code' => strtolower($codes[0])])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(TwoFactor::RECOVERY_CODE_COUNT - 1, TwoFactor::remainingRecoveryCodes($user->fresh()));
        $this->post(route('logout'));

        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password']);
        $this->post(route('two-factor.verify'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_secrets_are_encrypted_and_never_exposed(): void
    {
        $user = $this->member();
        [$secret, $codes] = $this->enableTwoFactor($user);

        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertStringNotContainsString($secret, $row->two_factor_secret);
        $this->assertStringNotContainsString($codes[0], (string) $row->two_factor_recovery_codes);
        $this->assertStringNotContainsString($codes[0], decrypt($row->two_factor_recovery_codes, false));   // chỉ lưu bản băm

        $json = $user->fresh()->toJson();
        $this->assertStringNotContainsString('two_factor_secret', $json);
        $this->assertStringNotContainsString('two_factor_recovery_codes', $json);
    }

    // ---------------- Tự thiết lập 2FA ----------------

    public function test_user_can_enable_two_factor_with_password_and_code(): void
    {
        $user = $this->member();
        $this->actingAs($user)->post(route('two-factor.enable'), ['password' => 'sai'])->assertSessionHasErrors('password');
        $this->actingAs($user)->post(route('two-factor.enable'), ['password' => 'password'])->assertRedirect(route('profile.security'));
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());   // chưa có hiệu lực tới khi xác nhận mã
        $this->actingAs($user)->get(route('profile.security'))->assertOk()->assertSee('<svg', false);

        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => '123456'])->assertSessionHasErrors('code');
        $response = $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $this->currentCode($user->two_factor_secret)]);
        $response->assertRedirect(route('profile.security'))->assertSessionHas('two_factor_codes');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        // Mã khôi phục hiển thị đúng một lần
        $page = $this->actingAs($user)->get(route('profile.security'));
        $page->assertSee(__('Mã khôi phục — chỉ hiển thị một lần'));
        $this->actingAs($user)->get(route('profile.security'))->assertDontSee(__('Mã khôi phục — chỉ hiển thị một lần'));
    }

    public function test_disabling_two_factor_needs_password_and_code(): void
    {
        $user = $this->member();
        [$secret] = $this->enableTwoFactor($user);

        $this->actingAs($user)->delete(route('two-factor.disable'), ['password' => 'password', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->actingAs($user)->delete(route('two-factor.disable'), ['password' => 'sai', 'code' => $this->currentCode($secret)])->assertSessionHasErrors('password');
        $this->actingAs($user)->delete(route('two-factor.disable'), ['password' => 'password', 'code' => $this->currentCode($secret)])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_regenerating_codes_invalidates_old_ones(): void
    {
        $user = $this->member();
        [, $old] = $this->enableTwoFactor($user);
        $this->actingAs($user)->post(route('two-factor.recovery-codes'), ['regen_password' => 'password'])->assertSessionHas('two_factor_codes');
        $this->assertFalse(TwoFactor::useRecoveryCode($user->fresh(), $old[0]));
        $this->assertSame(TwoFactor::RECOVERY_CODE_COUNT, TwoFactor::remainingRecoveryCodes($user->fresh()));
    }

    public function test_admin_can_reset_two_factor_of_member_but_not_of_owner(): void
    {
        $member = $this->member();
        $this->enableTwoFactor($member);
        $orgAdmin = $this->userWithRole('Organization Administrator', ['email' => 'qt@example.com']);
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER, ['email' => 'owner@example.com']);
        $this->enableTwoFactor($owner);

        $this->actingAs($orgAdmin)->delete(route('users.two-factor.reset', $owner))->assertForbidden();
        $this->assertTrue($owner->fresh()->hasTwoFactorEnabled());

        $this->actingAs($orgAdmin)->delete(route('users.two-factor.reset', $member))->assertSessionHas('success');
        $this->assertFalse($member->fresh()->hasTwoFactorEnabled());

        $sales = $this->userWithRole('Sales Employee', ['email' => 'sale2@example.com']);
        $this->enableTwoFactor($member);
        $this->actingAs($sales)->delete(route('users.two-factor.reset', $member))->assertForbidden();
    }

    // ---------------- Lịch sử đăng nhập & cảnh báo ----------------

    public function test_login_history_records_success_and_failure_without_typed_text(): void
    {
        $user = $this->member();
        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'sai-mat-khau']);
        $this->post('/login', ['login' => 'MatKhauGoNhamO2026', 'password' => 'x']);   // gõ nhầm mật khẩu vào ô tài khoản
        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password']);

        $this->assertSame(1, LoginEvent::where('user_id', $user->id)->where('event', 'failed')->count());
        $this->assertSame(1, LoginEvent::where('user_id', $user->id)->where('event', 'login')->count());
        $this->assertSame(1, LoginEvent::whereNull('user_id')->count());
        foreach (DB::table('login_events')->get() as $row) {
            $this->assertStringNotContainsString('MatKhauGoNhamO2026', json_encode($row));
        }

        $this->actingAs($user)->get(route('profile.security'))->assertOk()
            ->assertSee(__('Sai mật khẩu'))->assertSee(__('Đăng nhập thành công'));
    }

    public function test_users_only_see_their_own_login_history(): void
    {
        $a = $this->member();
        $b = $this->userWithRole('Sales Employee', ['email' => 'b@example.com']);
        LoginEvent::create(['user_id' => $b->id, 'event' => 'login', 'method' => 'password', 'ip_address' => '10.9.8.7']);

        $this->actingAs($a)->get(route('profile.security'))->assertOk()->assertDontSee('10.9.8.7');
        $this->actingAs($b)->get(route('profile.security'))->assertOk()->assertSee('10.9.8.7');
    }

    public function test_new_device_triggers_alert_but_known_device_does_not(): void
    {
        Notification::fake();
        $user = $this->member();

        // Lần đăng nhập đầu tiên của tài khoản: không cảnh báo
        $first = $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password']);
        Notification::assertNotSentTo($user, NewDeviceLoginNotification::class);
        $cookie = collect($first->headers->getCookies())->first(fn ($c) => $c->getName() === LoginFlow::DEVICE_COOKIE);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $token = \Illuminate\Cookie\CookieValuePrefix::remove(decrypt($cookie->getValue(), false));   // cookie được mã hóa
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $token);
        $this->post(route('logout'));

        // Cùng trình duyệt (gửi lại cookie thiết bị): không cảnh báo
        $this->withCookie(LoginFlow::DEVICE_COOKIE, $token)->post('/login', ['login' => 'nv@example.com', 'password' => 'password']);
        Notification::assertNotSentTo($user, NewDeviceLoginNotification::class);
        $this->post(route('logout'));

        // Trình duyệt khác (không có cookie): cảnh báo qua email + chuông thông báo
        $this->defaultCookies = [];
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) Safari')->post('/login', ['login' => 'nv@example.com', 'password' => 'password']);
        Notification::assertSentTo($user, NewDeviceLoginNotification::class);
        $this->assertTrue(LoginEvent::where('user_id', $user->id)->where('is_new_device', true)->exists());
        $this->assertNull(DB::table('login_events')->where('device_hash', $token)->first());   // không lưu cookie gốc
    }

    public function test_old_login_events_are_pruned(): void
    {
        $user = $this->member();
        $old = LoginEvent::create(['user_id' => $user->id, 'event' => 'login']);
        $old->forceFill(['created_at' => now()->subDays(LoginEvent::RETENTION_DAYS + 1)])->save();
        $recent = LoginEvent::create(['user_id' => $user->id, 'event' => 'login']);

        $this->artisan('model:prune', ['--model' => [LoginEvent::class]])->assertSuccessful();
        $this->assertNull(LoginEvent::find($old->id));
        $this->assertNotNull(LoginEvent::find($recent->id));
    }

    // ---------------- Xác minh email ----------------

    public function test_email_verification_flow(): void
    {
        Notification::fake();
        $user = $this->member(['email_verified_at' => null]);
        $this->assertFalse($user->hasVerifiedEmail());

        $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('success');
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        // Liên kết không có chữ ký / sai chữ ký → bị từ chối
        $this->actingAs($user)->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();

        // Liên kết của người khác → bị từ chối
        $other = $this->userWithRole('Sales Employee', ['email' => 'other@example.com', 'email_verified_at' => null]);
        $otherUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $other->id, 'hash' => sha1($other->email)]);
        $this->actingAs($user)->get($otherUrl)->assertForbidden();
        $this->assertFalse($other->fresh()->hasVerifiedEmail());

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($url)->assertRedirect(route('profile.security'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_changing_email_requires_verification_again(): void
    {
        $user = $this->member(['email_verified_at' => now()]);
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name, 'email' => 'moi@example.com', 'status' => 'active', 'role' => 'Sales Employee',
        ])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    // ---------------- Bắt buộc 2FA cho quản trị ----------------

    public function test_required_two_factor_redirects_admins_without_locking_them_out(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER, ['email' => 'owner@example.com']);
        $member = $this->member();
        Setting::put([RequireTwoFactor::SETTING => '1'], 'security');

        $this->actingAs($owner)->get(route('customers.index'))->assertRedirect(route('profile.security'));
        $this->actingAs($owner)->get(route('profile.security'))->assertOk();
        $this->actingAs($member)->get(route('customers.index'))->assertOk();   // nhân viên thường không bị ép

        $this->enableTwoFactor($owner);
        $this->actingAs($owner->fresh())->get(route('customers.index'))->assertOk();
    }

    public function test_only_platform_admin_can_change_required_two_factor_setting(): void
    {
        $orgAdmin = $this->userWithRole('Organization Administrator', ['email' => 'qt@example.com']);
        $payload = ['company_name' => 'TOP CRM', 'brand_color' => '#D9823B', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND', 'date_format' => 'd/m/Y', 'default_tax_rate' => 10, RequireTwoFactor::SETTING => '1'];

        $this->actingAs($orgAdmin)->put(route('settings.update'), $payload);
        $this->assertSame('0', Setting::get(RequireTwoFactor::SETTING, '0'));

        $this->actingAs($this->admin())->put(route('settings.update'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('1', Setting::get(RequireTwoFactor::SETTING, '0'));
    }
}
