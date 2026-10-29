<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\MailSettings;
use App\Support\NotificationCatalog;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Hệ thống → Email gửi đi: chỉ quản trị nền tảng, mật khẩu mã hóa, áp dụng cấu hình lúc chạy, gửi thử. */
class MailSettingsTest extends TestCase
{
    private const PASSWORD = 'abcd efgh ijkl mnop';

    private function payload(array $data = []): array
    {
        return $data + ['mail_mailer' => 'smtp', 'mail_host' => 'smtp.gmail.com', 'mail_port' => 587, 'mail_encryption' => 'tls',
            'mail_username' => 'crm@congty.vn', 'mail_password' => self::PASSWORD, 'mail_from_address' => 'crm@congty.vn', 'mail_from_name' => 'TOP CRM'];
    }

    public function test_only_platform_admin_can_configure(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);   // chủ tổ chức nhưng không phải quản trị nền tảng
        $this->actingAs($owner)->get(route('settings.mail'))->assertForbidden();
        $this->put(route('settings.mail.update'), $this->payload())->assertForbidden();
        $this->post(route('settings.mail.test'), ['to' => 'a@b.vn'])->assertForbidden();
        $this->assertNull(Setting::get('mail_host'));
    }

    public function test_password_is_encrypted_never_shown_and_config_is_applied(): void
    {
        $this->actingAs($this->admin())->get(route('settings.mail'))->assertOk()->assertSee(__('Email chưa được gửi đi thật', [], 'vi'), false);
        $this->put(route('settings.mail.update'), $this->payload(['mail_host' => 'smtp gmail.com']))->assertSessionHasErrors('mail_host');
        $this->put(route('settings.mail.update'), $this->payload(['mail_from_address' => 'khong-phai-email']))->assertSessionHasErrors('mail_from_address');
        $this->put(route('settings.mail.update'), $this->payload())->assertSessionHas('success');

        $raw = DB::table('settings')->where('key', 'mail_password')->value('value');
        $this->assertNotSame(self::PASSWORD, $raw);
        $this->assertSame(self::PASSWORD, Crypt::decryptString($raw));
        $this->assertStringNotContainsString(self::PASSWORD, \App\Models\ActivityLog::all()->toJson());
        $this->get(route('settings.mail'))->assertOk()->assertDontSee(self::PASSWORD)->assertSee(__('Đã lưu — để trống để giữ nguyên'));

        $this->assertTrue(MailSettings::enabled());
        $this->assertSame(['smtp', 'smtp.gmail.com', 587, 'smtp', 'crm@congty.vn', self::PASSWORD, 'crm@congty.vn'],
            [config('mail.default'), config('mail.mailers.smtp.host'), config('mail.mailers.smtp.port'), config('mail.mailers.smtp.scheme'),
                config('mail.mailers.smtp.username'), config('mail.mailers.smtp.password'), config('mail.from.address')]);
        $this->assertTrue(NotificationCatalog::mailEnabled());

        // Để trống mật khẩu khi lưu lại = giữ mật khẩu cũ; SSL → scheme smtps; xóa mật khẩu
        $this->put(route('settings.mail.update'), $this->payload(['mail_password' => '', 'mail_encryption' => 'ssl', 'mail_port' => 465]))->assertSessionHas('success');
        $this->assertSame([self::PASSWORD, 'smtps'], [Crypt::decryptString(Setting::get('mail_password')), config('mail.mailers.smtp.scheme')]);
        $this->put(route('settings.mail.update'), $this->payload(['mail_password' => '', 'clear_password' => 1]));
        $this->assertSame('', (string) Setting::get('mail_password'));

        // Chuyển về .env: không còn ghi đè
        $this->put(route('settings.mail.update'), ['mail_mailer' => 'env'])->assertSessionHas('success');
        $this->assertFalse(MailSettings::enabled());
    }

    public function test_send_test_email_success_and_friendly_error(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('settings.mail.test'), ['to' => 'nhan@congty.vn'])->assertSessionHasErrors('to');            // chưa bật SMTP
        $this->put(route('settings.mail.update'), $this->payload());

        Mail::shouldReceive('purge')->zeroOrMoreTimes();
        Mail::shouldReceive('raw')->once()->withArgs(fn ($text, $cb) => str_contains($text, 'TOP CRM'));
        $this->post(route('settings.mail.test'), ['to' => 'nhan@congty.vn'])->assertSessionHas('success');

        Mail::shouldReceive('raw')->once()->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('Expected response code "235" but got code "535": Username and Password not accepted'));
        $res = $this->post(route('settings.mail.test'), ['to' => 'nhan@congty.vn'])->assertSessionHasErrors('to');
        $this->assertStringContainsString(__('sai tên đăng nhập hoặc mật khẩu (Gmail cần “Mật khẩu ứng dụng”).'), session('errors')->first('to'));
        $this->assertStringNotContainsString('crm@congty.vn', session('errors')->first('to'));
    }
}
