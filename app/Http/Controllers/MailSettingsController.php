<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Support\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Hệ thống → Email gửi đi (chỉ quản trị nền tảng): cấu hình SMTP trên giao diện, gửi email thử.
 * Mật khẩu lưu mã hóa, để trống khi lưu = giữ mật khẩu cũ; không bao giờ trả lại mật khẩu ra trang.
 */
class MailSettingsController extends Controller
{
    public function edit(Request $request)
    {
        abort_unless($request->user()->can('platform'), 403);
        $s = collect(MailSettings::KEYS)->mapWithKeys(fn ($k) => [$k => (string) Setting::get($k)])->except('mail_password')->all();

        return view('settings.mail', [
            's' => $s + ['mail_mailer' => $s['mail_mailer'] ?: 'env'], 'hasPassword' => filled(Setting::get('mail_password')),
            'presets' => MailSettings::presets(), 'active' => MailSettings::enabled(),
            'envMailer' => (string) env('MAIL_MAILER', 'log'),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->can('platform'), 403);
        $smtp = $request->input('mail_mailer') === 'smtp';
        $data = $request->validate([
            'mail_mailer' => ['required', Rule::in(['env', 'smtp'])],
            'mail_host' => [$smtp ? 'required' : 'nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'mail_port' => [$smtp ? 'required' : 'nullable', 'integer', 'between:1,65535'],
            'mail_encryption' => [$smtp ? 'required' : 'nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_username' => ['nullable', 'string', 'max:190'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => [$smtp ? 'required' : 'nullable', 'email:rfc', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:150'],
        ], [], ['mail_host' => __('Máy chủ SMTP'), 'mail_port' => __('Cổng'), 'mail_encryption' => __('Mã hóa'), 'mail_from_address' => __('Email người gửi')]);

        if ($request->boolean('clear_password')) {
            $data['mail_password'] = '';
        } elseif (filled($data['mail_password'] ?? null)) {
            $data['mail_password'] = Crypt::encryptString($data['mail_password']);
        } else {
            unset($data['mail_password']);                     // để trống = giữ mật khẩu đã lưu
        }
        Setting::put(array_map(fn ($v) => (string) $v, $data), 'mail');
        ActivityLogger::log('updated', null, 'Cập nhật cấu hình email gửi đi ('.($data['mail_mailer'] === 'smtp' ? 'SMTP '.$data['mail_host'] : 'theo .env').')');
        MailSettings::apply();

        return back()->with('success', $data['mail_mailer'] === 'smtp' ? __('Đã lưu cấu hình email. Hãy gửi email thử để kiểm tra.') : __('Đã chuyển về cấu hình email của máy chủ (.env).'));
    }

    public function test(Request $request)
    {
        abort_unless($request->user()->can('platform'), 403);
        $data = $request->validate(['to' => ['required', 'email:rfc', 'max:190']], [], ['to' => __('Email nhận thử')]);
        if (! MailSettings::enabled()) {
            return back()->withErrors(['to' => __('Chưa bật cấu hình SMTP — lưu cấu hình trước khi gửi thử.')]);
        }
        MailSettings::apply();
        try {
            Mail::raw(__('Email thử từ :app lúc :t. Nếu bạn nhận được thư này, cấu hình email gửi đi đã hoạt động.', ['app' => config('mail.from.name'), 't' => vn_date(now(), true)]),
                fn ($m) => $m->to($data['to'])->subject(__('[:app] Email thử cấu hình', ['app' => config('mail.from.name')])));
        } catch (\Throwable $e) {
            // Không đưa nguyên văn lỗi (có thể chứa tên đăng nhập) ra giao diện; ghi log máy chủ để tra cứu
            report($e);

            return back()->withErrors(['to' => __('Gửi thử thất bại: :r', ['r' => self::reason($e)])]);
        }
        ActivityLogger::log('updated', null, 'Gửi email thử cấu hình tới '.$data['to']);

        return back()->with('success', __('Đã gửi email thử tới :to — kiểm tra hộp thư (cả mục Spam).', ['to' => $data['to']]));
    }

    /** Diễn giải lỗi SMTP thường gặp thành tiếng Việt dễ hiểu. */
    private static function reason(\Throwable $e): string
    {
        $m = strtolower($e->getMessage());

        return match (true) {
            str_contains($m, '535') || str_contains($m, 'authentication') || str_contains($m, 'username and password') => __('sai tên đăng nhập hoặc mật khẩu (Gmail cần “Mật khẩu ứng dụng”).'),
            str_contains($m, 'connection') || str_contains($m, 'timed out') || str_contains($m, 'getaddrinfo') => __('không kết nối được máy chủ SMTP — kiểm tra máy chủ, cổng, mã hóa và mạng.'),
            str_contains($m, 'certificate') || str_contains($m, 'ssl') || str_contains($m, 'tls') => __('lỗi mã hóa SSL/TLS — thử đổi giữa TLS (cổng 587) và SSL (cổng 465).'),
            default => __('máy chủ SMTP từ chối — xem chi tiết trong nhật ký máy chủ.'),
        };
    }
}
