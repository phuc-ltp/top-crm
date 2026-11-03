<?php

namespace App\Services\Auth;

use App\Notifications\EmailOtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Mã xác thực 6 số gửi qua email (đăng nhập bước 2 / xác minh email khi đăng ký).
 * Chỉ lưu bản băm HMAC của mã trong cache, hết hạn sau TTL, tối đa MAX_ATTEMPTS lần nhập sai,
 * gửi lại cách nhau ít nhất RESEND_SECONDS.
 */
class EmailOtp
{
    public const TTL = 600;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    private static function cacheKey(string $purpose, string $subject): string
    {
        return 'email_otp:'.$purpose.':'.hash('sha256', $subject);
    }

    private static function digest(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /** Số giây còn phải chờ trước khi được gửi lại (0 = gửi được). */
    public static function cooldown(string $purpose, string $subject): int
    {
        $entry = Cache::get(self::cacheKey($purpose, $subject));

        return $entry ? max(0, self::RESEND_SECONDS - (now()->getTimestamp() - $entry['sent_at'])) : 0;
    }

    /**
     * Tạo và gửi mã mới. Trả về: 'sent' | 'cooldown' | 'failed' (không gửi được thư — mã không còn hiệu lực).
     */
    public static function send(string $purpose, string $subject, string $email, ?string $name = null): string
    {
        if (self::cooldown($purpose, $subject) > 0) {
            return 'cooldown';
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = self::cacheKey($purpose, $subject);
        Cache::put($key, ['hash' => self::digest($code), 'attempts' => 0, 'sent_at' => now()->getTimestamp(), 'email' => $email], self::TTL);
        try {
            Notification::route('mail', $email)->notify(new EmailOtpNotification($code, $purpose, (int) (self::TTL / 60), $name));
        } catch (\Throwable $e) {
            Cache::forget($key);
            Log::warning('Không gửi được mã xác thực email: '.$e->getMessage());

            return 'failed';
        }

        return 'sent';
    }

    /** Kiểm tra mã; đúng thì xóa (dùng một lần). Sai quá số lần cho phép thì mã bị hủy. */
    public static function verify(string $purpose, string $subject, string $code): bool
    {
        $key = self::cacheKey($purpose, $subject);
        $entry = Cache::get($key);
        $code = preg_replace('/\D/', '', $code);
        if (! $entry || strlen($code) !== 6) {
            return false;
        }
        if (hash_equals($entry['hash'], self::digest($code))) {
            Cache::forget($key);

            return true;
        }
        $entry['attempts']++;
        $entry['attempts'] >= self::MAX_ATTEMPTS ? Cache::forget($key) : Cache::put($key, $entry, max(1, self::TTL - (now()->getTimestamp() - $entry['sent_at'])));

        return false;
    }

    public static function pending(string $purpose, string $subject): bool
    {
        return Cache::has(self::cacheKey($purpose, $subject));
    }

    /** Che email để hiển thị: ph***@gmail.com */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('*', max(3, mb_strlen($local) - 2)).'@'.$domain;
    }
}
