<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\JoinRequest;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Notifications\EmailOtpNotification;
use App\Notifications\JoinRequestDecisionNotification;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\OrganizationProvisioner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SignupRequestTest extends TestCase
{
    private const PW = 'Matkhau2026x';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->admin();
        config(['mail.default' => 'smtp', 'mail.from.address' => 'crm@example.com']);   // email gửi đi "đang hoạt động"
        Setting::put(['signup_organization_id' => (string) app(CurrentOrganization::class)->id()], 'security');
    }

    private function payload(array $over = []): array
    {
        return $over + ['name' => 'Bạn Của Phúc', 'email' => 'Ban.Moi@Example.com', 'phone' => '0901234567',
            'password' => self::PW, 'password_confirmation' => self::PW, 'message' => 'Phòng kinh doanh', 'terms' => '1'];
    }

    private function code(string $email): string
    {
        $code = null;
        Notification::assertSentOnDemand(EmailOtpNotification::class, function ($n, $c, $notifiable) use ($email, &$code) {
            if (($notifiable->routes['mail'] ?? null) === $email) {
                $code = $n->code;
            }

            return true;
        });

        return $code;
    }

    /** Điền form + nhập mã → yêu cầu chờ duyệt. */
    private function submitRequest(array $over = []): JoinRequest
    {
        $this->post(route('register.submit'), $this->payload($over))->assertRedirect(route('register.verify'));
        $email = mb_strtolower($over['email'] ?? 'Ban.Moi@Example.com');
        $this->post(route('register.verify.store'), ['code' => $this->code($email)])->assertRedirect(route('register.done'));

        return JoinRequest::withoutGlobalScopes()->where('email', $email)->latest('id')->firstOrFail();
    }

    public function test_login_page_shows_create_account_only_when_open(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('register'));
        Setting::put(['signup_organization_id' => '0'], 'security');
        $this->get(route('login'))->assertOk()->assertDontSee(route('register'))->assertSee(__('Chưa có tài khoản? Xin quản lý của bạn tạo tài khoản hoặc gửi link mời.'));
        $this->get(route('register'))->assertOk()->assertSee(__('Hiện chưa mở đăng ký trực tuyến. Hãy xin quản lý của bạn tạo tài khoản hoặc gửi link mời qua email / Zalo.'));
        $this->post(route('register.submit'), $this->payload())->assertNotFound();

        Setting::put(['signup_organization_id' => (string) app(CurrentOrganization::class)->id()], 'security');
        config(['mail.default' => 'log']);   // email chưa hoạt động → mã không đến được → không mở
        $this->get(route('login'))->assertDontSee(route('register'));
    }

    public function test_full_flow_request_then_approve_then_login(): void
    {
        $this->get(route('register'))->assertOk()->assertSee(__('Tạo tài khoản'));
        $this->post(route('register.submit'), $this->payload())->assertRedirect(route('register.verify'));
        $this->assertSame(0, JoinRequest::count());   // chưa có gì khi chưa xác thực email
        $this->get(route('register.verify'))->assertOk()->assertSee('ba*****@example.com');
        $this->post(route('register.verify.store'), ['code' => '000000' === $this->code('ban.moi@example.com') ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->post(route('register.verify.store'), ['code' => $this->code('ban.moi@example.com')])->assertRedirect(route('register.done'));
        $this->get(route('register.done'))->assertOk()->assertSee(__('Đã gửi yêu cầu tham gia'));

        $jr = JoinRequest::sole();
        $this->assertSame('pending', $jr->status);
        $this->assertSame('ban.moi@example.com', $jr->email);
        $this->assertTrue(Hash::check(self::PW, $jr->password));
        $this->assertDatabaseMissing('users', ['email' => 'ban.moi@example.com']);
        Notification::assertSentTo($this->admin, CrmNotification::class);   // quản trị được báo

        // Chưa duyệt: đăng nhập báo rõ "đang chờ duyệt"
        $this->post('/login', ['login' => 'ban.moi@example.com', 'password' => self::PW])
            ->assertSessionHasErrors(['login' => __('Yêu cầu tạo tài khoản của email này đang chờ quản trị viên duyệt. Bạn sẽ nhận email khi được duyệt.')]);
        $this->assertGuest();

        // Quản trị duyệt với vai trò
        $this->actingAs($this->admin)->get(route('join-requests.index'))->assertOk()->assertSee('ban.moi@example.com')->assertSee('Phòng kinh doanh');
        $this->actingAs($this->admin)->post(route('join-requests.approve', $jr), ['role' => 'Sales Employee'])->assertSessionHasNoErrors();
        $user = User::where('email', 'ban.moi@example.com')->sole();
        $this->assertTrue($user->hasRole('Sales Employee'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->organizations()->whereKey(app(CurrentOrganization::class)->id())->exists());
        $this->assertTrue(EmployeeProfile::where('user_id', $user->id)->exists());
        $jr->refresh();
        $this->assertSame('approved', $jr->status);
        $this->assertSame('-', $jr->password);   // không giữ lại bản băm mật khẩu
        Notification::assertSentOnDemand(JoinRequestDecisionNotification::class, fn ($n) => $n->approved && $n->role === 'Sales Employee');

        // Người mới đăng nhập bằng mật khẩu đã đặt
        auth()->logout();
        $this->post('/login', ['login' => 'ban.moi@example.com', 'password' => self::PW])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_reject_sends_reason_and_allows_new_request(): void
    {
        $jr = $this->submitRequest();
        $this->actingAs($this->admin)->post(route('join-requests.reject', $jr), ['reject_reason' => 'Không phải nhân viên công ty'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $jr->fresh()->status);
        $this->assertDatabaseMissing('users', ['email' => 'ban.moi@example.com']);
        Notification::assertSentOnDemand(JoinRequestDecisionNotification::class, fn ($n) => ! $n->approved && $n->reason === 'Không phải nhân viên công ty');
        $this->actingAs($this->admin)->post(route('join-requests.approve', $jr), ['role' => 'Sales Employee'])->assertSessionHasErrors('role');   // đã xử lý

        auth()->logout();
        $this->travel(2)->minutes();
        $this->submitRequest();   // gửi lại được sau khi bị từ chối
        $this->assertSame(1, JoinRequest::where('status', 'pending')->count());
    }

    public function test_duplicate_pending_and_existing_account_and_validation(): void
    {
        $this->userWithRole('Sales Employee', ['email' => 'cu@example.com']);
        $this->post(route('register.submit'), $this->payload(['email' => 'cu@example.com']))->assertSessionHasErrors('email');
        $this->post(route('register.submit'), $this->payload(['password' => 'abc12345', 'password_confirmation' => 'abc12345']))->assertSessionHasErrors('password');
        $this->post(route('register.submit'), $this->payload(['terms' => null]))->assertSessionHasErrors('terms');
        $this->post(route('register.submit'), $this->payload(['website' => 'spam']))->assertRedirect(route('register'));
        Notification::assertNothingSent();

        $this->submitRequest();
        $this->post(route('register.submit'), $this->payload())->assertRedirect(route('register.done'));   // đã có yêu cầu chờ duyệt → không gửi mã lại
        $this->get(route('register.done'))->assertSee(__('Yêu cầu đang chờ duyệt'));
        $this->assertSame(1, JoinRequest::count());
    }

    public function test_approval_permissions_and_role_limits(): void
    {
        $jr = $this->submitRequest();
        $sales = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $this->actingAs($sales)->get(route('join-requests.index'))->assertForbidden();
        $this->actingAs($sales)->post(route('join-requests.approve', $jr), ['role' => 'Sales Employee'])->assertForbidden();

        $orgAdmin = $this->userWithRole('Organization Administrator', ['email' => 'qt@example.com']);
        $this->actingAs($orgAdmin)->post(route('join-requests.approve', $jr), ['role' => \App\Support\PermissionCatalog::OWNER])->assertSessionHasErrors('role');
        $this->actingAs($orgAdmin)->post(route('join-requests.approve', $jr), ['role' => 'Organization Administrator'])->assertSessionHasErrors('role');
        $this->assertSame('pending', $jr->fresh()->status);
    }

    public function test_requests_of_other_organization_are_unreachable(): void
    {
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        Setting::put(['signup_organization_id' => (string) $orgB->id], 'security');
        $jr = $this->submitRequest();
        $this->assertSame($orgB->id, (int) JoinRequest::withoutGlobalScopes()->find($jr->id)->organization_id);

        $this->admin->forceFill(['is_platform_admin' => false])->save();
        $this->actingAs($this->admin)->get(route('join-requests.index'))->assertDontSee('ban.moi@example.com');
        $this->actingAs($this->admin)->post(route('join-requests.approve', $jr->id), ['role' => 'Sales Employee'])->assertNotFound();
    }

    public function test_only_platform_admin_sets_signup_organization_and_mail_required(): void
    {
        $payload = ['company_name' => 'TOP CRM', 'brand_color' => '#D9823B', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND', 'date_format' => 'd/m/Y', 'default_tax_rate' => 10];
        Setting::put(['signup_organization_id' => '0'], 'security');
        $orgId = (string) app(CurrentOrganization::class)->id();

        $orgAdmin = $this->userWithRole('Organization Administrator', ['email' => 'qt@example.com']);
        $this->actingAs($orgAdmin)->put(route('settings.update'), $payload + ['signup_organization_id' => $orgId]);
        $this->assertSame('0', Setting::get('signup_organization_id'));

        config(['mail.default' => 'log']);
        $this->actingAs($this->admin)->put(route('settings.update'), $payload + ['signup_organization_id' => $orgId])->assertSessionHasErrors('signup_organization_id');
        config(['mail.default' => 'smtp']);
        $this->actingAs($this->admin)->put(route('settings.update'), $payload + ['signup_organization_id' => '999'])->assertSessionHasErrors('signup_organization_id');
        $this->actingAs($this->admin)->put(route('settings.update'), $payload + ['signup_organization_id' => $orgId])->assertSessionHasNoErrors();
        $this->assertSame($orgId, Setting::get('signup_organization_id'));
    }
}
