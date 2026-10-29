<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Cấu hình email gửi đi do quản trị nền tảng nhập trên giao diện (Hệ thống → Email gửi đi), áp dụng lúc chạy
 * thay cho MAIL_* trong .env. Mật khẩu SMTP lưu mã hóa bằng APP_KEY, không bao giờ hiển thị lại.
 * mail_mailer: 'env' = theo .env của máy chủ (mặc định), 'smtp' = dùng cấu hình bên dưới.
 */
final class MailSettings
{
    public const KEYS = ['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'];

    /** Mẫu nhà cung cấp: [máy chủ, cổng, mã hóa, ghi chú]. */
    public static function presets(): array
    {
        return [
            'gmail' => ['Gmail / Google Workspace', 'smtp.gmail.com', 587, 'tls', __('Bật xác minh 2 bước rồi tạo “Mật khẩu ứng dụng” (16 ký tự) tại myaccount.google.com/apppasswords — không dùng mật khẩu Gmail thường.')],
            'zoho' => ['Zoho Mail', 'smtp.zoho.com', 465, 'ssl', __('Tài khoản Zoho miễn phí / trả phí; nên dùng mật khẩu ứng dụng nếu đã bật xác minh 2 bước.')],
            'outlook' => ['Outlook / Microsoft 365', 'smtp.office365.com', 587, 'tls', __('Quản trị Microsoft 365 cần bật SMTP AUTH cho hộp thư.')],
            'custom' => [__('SMTP khác (hosting, Mailgun, SES…)'), '', 587, 'tls', __('Lấy thông số SMTP từ nhà cung cấp email / hosting.')],
        ];
    }

    public static function enabled(): bool
    {
        return Setting::get('mail_mailer') === 'smtp' && filled(Setting::get('mail_host')) && filled(Setting::get('mail_from_address'));
    }

    /** Áp dụng cấu hình đã lưu vào config mail (gọi khi khởi động ứng dụng và sau khi lưu / gửi thử). */
    public static function apply(): void
    {
        try {
            if (! self::enabled()) {                          // đọc từ cache cài đặt, không truy vấn thêm mỗi request
                return;
            }
        } catch (\Throwable) {
            return;                                           // chưa có CSDL (cài đặt lần đầu, lệnh console…)
        }
        $password = Setting::get('mail_password');
        try {
            $password = $password ? Crypt::decryptString($password) : null;
        } catch (\Throwable) {
            $password = null;
        }
        $encryption = Setting::get('mail_encryption');
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => Setting::get('mail_host'),
            'mail.mailers.smtp.port' => (int) Setting::get('mail_port') ?: 587,
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => Setting::get('mail_username') ?: null,
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.timeout' => 15,
            'mail.from.address' => Setting::get('mail_from_address'),
            'mail.from.name' => Setting::get('mail_from_name') ?: Setting::get('company_name') ?: config('app.name'),
        ]);
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->purge('smtp');               // bỏ mailer đã tạo với cấu hình cũ
        }
    }
}
