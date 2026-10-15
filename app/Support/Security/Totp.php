<?php

namespace App\Support\Security;

/**
 * Mã dùng một lần theo thời gian (TOTP, RFC 6238): 6 chữ số, chu kỳ 30 giây, HMAC-SHA1.
 * Tương thích Google Authenticator, Microsoft Authenticator, Authy...
 */
class Totp
{
    public const DIGITS = 6;

    public const PERIOD = 30;

    /** Chấp nhận lệch ±1 chu kỳ (±30 giây) do đồng hồ điện thoại sai lệch. */
    public const WINDOW = 1;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Khóa bí mật ngẫu nhiên 160 bit, mã hóa Base32 (32 ký tự). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function currentStep(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), self::PERIOD);
    }

    public static function codeAt(string $secret, int $step): string
    {
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Kiểm tra mã. Trả về bước thời gian khớp (để lưu lại chống dùng lại) hoặc null.
     * Mã thuộc bước <= $lastUsedStep bị từ chối dù đúng.
     */
    public static function verify(string $secret, string $code, ?int $lastUsedStep = null, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{'.self::DIGITS.'}$/', (string) $code)) {
            return null;
        }
        $now = self::currentStep($timestamp);
        for ($step = $now - self::WINDOW; $step <= $now + self::WINDOW; $step++) {
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::codeAt($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** Địa chỉ otpauth:// để ứng dụng xác thực quét mã QR. */
    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => self::DIGITS, 'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[\s=]+/', '', $secret));
        $bits = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid base32 secret.');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
