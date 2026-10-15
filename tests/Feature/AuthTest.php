<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppInitialization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ResetPasswordNotification as ResetPassword;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_setup_page_creates_first_super_admin_then_locks_itself(): void
    {
        $this->get('/login')->assertRedirect(route('setup'));
        $this->get(route('setup'))->assertOk();

        $this->post(route('setup.store'), [
            'name' => 'Chủ hệ thống', 'email' => 'owner@example.com',
            'password' => 'MatKhau2026Manh', 'password_confirmation' => 'MatKhau2026Manh',
        ])->assertRedirect(route('dashboard'));

        $owner = User::where('email', 'owner@example.com')->first();
        $this->assertTrue($owner->isPlatformAdmin());
        $this->assertTrue($owner->hasRole('Organization Owner'));
        $this->post(route('logout'));
        $this->get(route('setup'))->assertNotFound();
        $this->get('/login')->assertOk();
    }

    private function setupPayload(string $email = 'owner@example.com'): array
    {
        return ['name' => 'Chủ hệ thống', 'email' => $email, 'password' => 'MatKhau2026Manh', 'password_confirmation' => 'MatKhau2026Manh'];
    }

    public function test_setup_writes_initialized_flag_and_second_attempt_creates_nothing(): void
    {
        $this->post(route('setup.store'), $this->setupPayload())->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('settings', ['key' => AppInitialization::KEY]);
        $this->post(route('logout'));

        $this->post(route('setup.store'), $this->setupPayload('attacker@example.com'))->assertNotFound();
        $this->assertSame(1, User::where('is_platform_admin', true)->count());
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_setup_stays_closed_after_super_admin_is_deleted(): void
    {
        $this->post(route('setup.store'), $this->setupPayload())->assertRedirect(route('dashboard'));
        $this->post(route('logout'));
        User::where('email', 'owner@example.com')->first()->forceDelete();

        $this->assertSame(0, User::where('is_platform_admin', true)->count());
        $this->get(route('setup'))->assertNotFound();
        $this->post(route('setup.store'), $this->setupPayload('attacker@example.com'))->assertNotFound();
        $this->get('/login')->assertOk();   // không bị chuyển sang /setup
    }

    public function test_setup_is_rejected_while_another_request_holds_the_lock(): void
    {
        $lock = Cache::lock('crm:setup', 30);
        $this->assertTrue($lock->get());

        $this->from(route('setup'))->post(route('setup.store'), $this->setupPayload())
            ->assertRedirect(route('setup'))->assertSessionHasErrors('name');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('settings', ['key' => AppInitialization::KEY]);

        $lock->release();
        $this->post(route('setup.store'), $this->setupPayload())->assertRedirect(route('dashboard'));
    }

    public function test_initialization_claim_succeeds_only_once(): void
    {
        $this->assertTrue(AppInitialization::claim());
        $this->assertFalse(AppInitialization::claim());
        $this->assertSame(1, \App\Models\Setting::where('key', AppInitialization::KEY)->count());
        $this->get(route('setup'))->assertNotFound();
    }

    public function test_failed_account_creation_does_not_close_setup(): void
    {
        // Lỗi giữa chừng khi lưu tài khoản phải hoàn tác cả cờ khởi tạo, để vẫn cài đặt lại được.
        User::creating(fn () => throw new \RuntimeException('Giả lập lỗi khi lưu tài khoản'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('setup.store'), $this->setupPayload());
            $this->fail('Lẽ ra phải ném lỗi giả lập');
        } catch (\RuntimeException $e) {
            $this->assertSame('Giả lập lỗi khi lưu tài khoản', $e->getMessage());
        }
        $this->assertDatabaseMissing('settings', ['key' => AppInitialization::KEY]);
        $this->assertDatabaseCount('users', 0);
        $this->assertTrue(\App\Http\Controllers\Auth\SetupController::needsSetup());
        $this->assertTrue(Cache::lock('crm:setup', 1)->get(), 'Khóa phải được nhả sau khi lỗi');
    }

    public function test_create_admin_command_closes_setup(): void
    {
        $this->artisan('crm:create-admin', ['--email' => 'cli@example.com', '--name' => 'Quản trị CLI'])
            ->expectsQuestion('Mật khẩu (tối thiểu 10 ký tự, có chữ hoa, chữ thường, số)', 'MatKhau2026Manh')
            ->expectsQuestion('Nhập lại mật khẩu', 'MatKhau2026Manh')
            ->assertSuccessful();
        $this->assertDatabaseHas('settings', ['key' => AppInitialization::KEY]);
        $this->get(route('setup'))->assertNotFound();
    }

    public function test_setup_rejects_weak_password(): void
    {
        $this->post(route('setup.store'), ['name' => 'A', 'email' => 'a@example.com', 'password' => '123456', 'password_confirmation' => '123456'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_can_login_and_logout(): void
    {
        $this->admin();
        $user = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);

        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'login', 'user_id' => $user->id]);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_and_rate_limited(): void
    {
        $this->admin();
        $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'nv@example.com', 'password' => 'sai-mat-khau'])->assertSessionHasErrors('login');
        }
        // Lần thứ 6 bị khóa kể cả khi đúng mật khẩu
        $this->post('/login', ['login' => 'nv@example.com', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_locked_user_cannot_login_and_is_kicked_out(): void
    {
        $this->admin();
        $user = $this->userWithRole('Sales Employee', ['email' => 'khoa@example.com', 'status' => 'locked']);

        $this->post('/login', ['login' => 'khoa@example.com', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        // Đang đăng nhập rồi bị khóa → bị đăng xuất ở request kế tiếp
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->admin();
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $this->admin();
        $user = $this->userWithRole('Read-only Auditor', ['email' => 'quen@example.com']);

        $this->post(route('password.email'), ['email' => 'quen@example.com'])->assertSessionHas('success');
        $this->post(route('password.email'), ['email' => 'khong-ton-tai@example.com'])->assertSessionHas('success'); // không lộ email

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post(route('password.update'), ['token' => $token, 'email' => 'quen@example.com', 'password' => 'MoiMoi2026', 'password_confirmation' => 'MoiMoi2026'])
            ->assertRedirect(route('login'));
        $this->post('/login', ['login' => 'quen@example.com', 'password' => 'MoiMoi2026'])->assertRedirect(route('dashboard'));
    }

    public function test_change_password_and_profile(): void
    {
        $user = $this->userWithRole('Sales Employee');
        $this->actingAs($user);

        $this->put(route('profile.password'), ['current_password' => 'sai', 'password' => 'MoiMoi2026', 'password_confirmation' => 'MoiMoi2026'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('profile.password'), ['current_password' => 'password', 'password' => 'MoiMoi2026', 'password_confirmation' => 'MoiMoi2026'])
            ->assertSessionHas('success');
        $this->assertTrue(\Hash::check('MoiMoi2026', $user->fresh()->password));

        $this->put(route('profile.update'), ['name' => 'Tên mới', 'phone' => '0901234567'])->assertSessionHas('success');
        $this->assertSame('Tên mới', $user->fresh()->name);
    }

    public function test_password_never_written_to_activity_log(): void
    {
        $user = $this->userWithRole('Sales Employee');
        $this->actingAs($user)->put(route('profile.password'), ['current_password' => 'password', 'password' => 'BiMat2026xyz', 'password_confirmation' => 'BiMat2026xyz']);

        $this->assertDatabaseMissing('activity_logs', ['properties' => null, 'description' => 'BiMat2026xyz']);
        foreach (\App\Models\ActivityLog::all() as $log) {
            $this->assertStringNotContainsString('BiMat2026xyz', json_encode($log->properties ?? []));
            $this->assertStringNotContainsString('$2y$', json_encode($log->properties ?? []));
        }
    }
}
