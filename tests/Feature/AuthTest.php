<?php

namespace Tests\Feature;

use App\Models\User;
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

        $this->assertTrue(User::where('email', 'owner@example.com')->first()->hasRole('Super Admin'));
        $this->post(route('logout'));
        $this->get(route('setup'))->assertNotFound();
        $this->get('/login')->assertOk();
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
        $user = $this->userWithRole('Sales Staff', ['email' => 'nv@example.com']);

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
        $this->userWithRole('Sales Staff', ['email' => 'nv@example.com']);

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
        $user = $this->userWithRole('Sales Staff', ['email' => 'khoa@example.com', 'status' => 'locked']);

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
        $user = $this->userWithRole('Viewer', ['email' => 'quen@example.com']);

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
        $user = $this->userWithRole('Sales Staff');
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
        $user = $this->userWithRole('Sales Staff');
        $this->actingAs($user)->put(route('profile.password'), ['current_password' => 'password', 'password' => 'BiMat2026xyz', 'password_confirmation' => 'BiMat2026xyz']);

        $this->assertDatabaseMissing('activity_logs', ['properties' => null, 'description' => 'BiMat2026xyz']);
        foreach (\App\Models\ActivityLog::all() as $log) {
            $this->assertStringNotContainsString('BiMat2026xyz', json_encode($log->properties ?? []));
            $this->assertStringNotContainsString('$2y$', json_encode($log->properties ?? []));
        }
    }
}
