<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_login_page_renders_brand_and_footer(): void
    {
        $this->admin();
        $this->get('/login')->assertOk()
            ->assertSee('Chào mừng trở lại')
            ->assertSee('Email hoặc tên đăng nhập')
            ->assertSee('Kiến tạo tăng trưởng')
            ->assertSee('TECHNOLOGY OF PROWESS')
            ->assertSee('Bản quyền thuộc Trịnh Phú Phúc')
            ->assertSee(route('pages.terms'), false)
            ->assertSee(route('social.redirect', 'google'), false);
    }

    public function test_login_with_username_case_insensitive(): void
    {
        $this->admin();
        $user = $this->userWithRole('Sales Staff', ['email' => 'nv@example.com', 'username' => 'NguyenVan.A']);
        $this->assertSame('nguyenvan.a', $user->fresh()->username, 'Tên đăng nhập lưu chữ thường');

        $this->post('/login', ['login' => 'NGUYENVAN.A', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_username_is_rejected(): void
    {
        $this->admin();
        $this->post('/login', ['login' => 'khongtontai', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_social_login_not_configured_shows_message(): void
    {
        $this->admin();
        $this->get(route('social.redirect', 'google'))->assertRedirect(route('login'))->assertSessionHas('warning');
        $this->get(route('social.callback', 'facebook'))->assertRedirect(route('login'));
        $this->get('/auth/twitter/redirect')->assertNotFound();
    }

    public function test_social_login_configured_redirects_to_provider(): void
    {
        $this->admin();
        Setting::put(['google_client_id' => 'test-client-id', 'google_client_secret' => Crypt::encryptString('test-secret')]);

        $response = $this->get(route('social.redirect', 'google'));
        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/', $response->headers->get('Location'));
        $this->assertStringContainsString('client_id=test-client-id', $response->headers->get('Location'));
    }

    public function test_public_pages_render_for_guests_and_users(): void
    {
        foreach (['pages.terms' => 'Điều khoản sử dụng', 'pages.privacy' => 'Chính sách bảo mật', 'pages.support' => 'Hỗ trợ'] as $route => $title) {
            $this->get(route($route))->assertOk()->assertSee($title);
        }
        Setting::put(['page_terms' => "Nội dung điều khoản riêng\n<script>alert(1)</script>"]);
        $this->get(route('pages.terms'))->assertSee('Nội dung điều khoản riêng')->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs($this->admin())->get(route('pages.support'))->assertOk();
    }

    public function test_branding_settings_update_and_secret_encrypted(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->get(route('settings.branding'))->assertOk()->assertSee(route('social.callback', 'google'));

        $this->put(route('settings.branding.update'), [
            'brand_name' => 'ABC', 'brand_tagline' => 'CÔNG NGHỆ', 'login_headline' => 'Xin chào', 'login_headline_accent' => 'Đối tác',
            'login_stats' => "1|Một\n2|Hai", 'copyright_text' => 'ABC Corp', 'copyright_owner' => 'Chủ ABC',
            'google_client_id' => 'gid', 'google_client_secret' => 'super-secret',
            'login_background' => UploadedFile::fake()->image('bg.jpg', 800, 1000),
        ])->assertSessionHas('success');

        $this->assertSame('ABC', Setting::get('brand_name'));
        $this->assertNotSame('super-secret', Setting::get('google_client_secret'), 'Khóa bí mật phải được mã hóa');
        $this->assertSame('super-secret', Setting::secret('google_client_secret'));
        $this->assertTrue(Setting::socialEnabled('google'));
        $this->assertCount(2, Setting::loginStats());
        Storage::disk('public')->assertExists(Setting::get('login_background'));

        // Để trống khóa bí mật thì giữ khóa cũ
        $this->put(route('settings.branding.update'), ['brand_name' => 'ABC', 'login_headline' => 'Xin chào', 'copyright_text' => 'ABC Corp', 'google_client_id' => 'gid']);
        $this->assertSame('super-secret', Setting::secret('google_client_secret'));

        auth()->logout();
        $this->get('/login')->assertSee('Xin chào')->assertSee('Bản quyền thuộc Chủ ABC');
    }

    public function test_branding_settings_forbidden_for_staff(): void
    {
        $this->actingAs($this->userWithRole('Sales Manager'));
        $this->get(route('settings.branding'))->assertForbidden();
        $this->put(route('settings.branding.update'), ['brand_name' => 'X'])->assertForbidden();
    }

    public function test_custom_error_pages_are_branded(): void
    {
        $this->admin();
        $this->get('/trang-khong-ton-tai')->assertNotFound()->assertSee('Không tìm thấy trang')->assertDontSee('NOT FOUND');
        $this->actingAs($this->userWithRole('Sales Staff'))->get(route('users.index'))->assertForbidden()->assertSee('Bạn không có quyền truy cập');
    }

    public function test_dashboard_and_design_system_render_with_new_layout(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('dashboard', ['period' => 'this_year']))->assertOk()
            ->assertSee('Tổng quan')->assertSee('Tỷ lệ cơ hội')->assertSee('Doanh số nhân viên')->assertSee('data-theme-toggle', false);
        $this->get(route('design-system'))->assertOk()->assertSee('Bảng màu chủ đạo')->assertSee('#D9823B');
        $this->actingAs($this->userWithRole('Sales Staff'))->get(route('design-system'))->assertForbidden();
    }

    public function test_admin_can_set_username(): void
    {
        $this->actingAs($this->admin());
        $user = $this->userWithRole('Viewer');
        $this->put(route('users.update', $user), ['name' => $user->name, 'email' => $user->email, 'username' => 'xem.vien', 'status' => 'active', 'role' => 'Viewer'])
            ->assertRedirect();
        $this->assertSame('xem.vien', $user->fresh()->username);
        $other = $this->userWithRole('Viewer');
        $this->put(route('users.update', $other), ['name' => 'B', 'email' => $other->email, 'username' => 'XEM.VIEN', 'status' => 'active', 'role' => 'Viewer'])
            ->assertSessionHasErrors('username');
    }
}
