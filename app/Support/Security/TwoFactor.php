<?php

namespace App\Support\Security;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;

/**
 * Xác thực 2 lớp: bật/tắt, kiểm tra mã TOTP (chống dùng lại), mã khôi phục dùng một lần.
 * Mã khôi phục chỉ lưu dạng băm SHA-256 (trong cột đã mã hóa) — hiển thị bản gốc đúng một lần.
 */
class TwoFactor
{
    public const RECOVERY_CODE_COUNT = 8;

    /** Bảng chữ không có ký tự dễ nhầm (0/O, 1/I/L). */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function issuer(): string
    {
        return (string) (\App\Models\Setting::get('brand_name') ?: 'TOP CRM').' CRM';
    }

    public static function qrSvg(string $uri, int $size = 200): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        // Bỏ khai báo XML để nhúng trực tiếp vào HTML
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $writer->writeString($uri)));
    }

    /** @return array{0: list<string>, 1: list<string>} [mã gốc để hiển thị, mã băm để lưu] */
    public static function makeRecoveryCodes(): array
    {
        $plain = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $plain[] = self::randomChunk(5).'-'.self::randomChunk(5);
        }

        return [$plain, array_map([self::class, 'hashCode'], $plain)];
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)));
    }

    /** Bật 2FA sau khi người dùng đã nhập đúng mã từ ứng dụng. Trả về danh sách mã khôi phục (bản gốc). */
    public static function enable(User $user, string $secret, int $usedStep): array
    {
        [$plain, $hashes] = self::makeRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $hashes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $usedStep,
        ])->saveQuietly();

        return $plain;
    }

    public static function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->saveQuietly();
    }

    public static function regenerateRecoveryCodes(User $user): array
    {
        [$plain, $hashes] = self::makeRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $hashes])->saveQuietly();

        return $plain;
    }

    public static function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * Kiểm tra mã 6 số từ ứng dụng. Khóa dòng người dùng để hai yêu cầu đồng thời
     * không dùng được cùng một mã.
     */
    public static function verifyTotp(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $fresh = User::whereKey($user->getKey())->lockForUpdate()->first();
            if (! $fresh || ! $fresh->hasTwoFactorEnabled()) {
                return false;
            }
            $step = Totp::verify($fresh->two_factor_secret, $code, $fresh->two_factor_last_step);
            if ($step === null) {
                return false;
            }
            $fresh->forceFill(['two_factor_last_step' => $step])->saveQuietly();

            return true;
        });
    }

    /** Dùng một mã khôi phục (mỗi mã chỉ dùng được một lần). */
    public static function useRecoveryCode(User $user, string $code): bool
    {
        $hash = self::hashCode($code);
        if (strlen(preg_replace('/[^A-Za-z0-9]/', '', $code)) !== 10) {
            return false;
        }

        return DB::transaction(function () use ($user, $hash) {
            $fresh = User::whereKey($user->getKey())->lockForUpdate()->first();
            if (! $fresh || ! $fresh->hasTwoFactorEnabled()) {
                return false;
            }
            $codes = $fresh->two_factor_recovery_codes ?? [];
            $index = null;
            foreach ($codes as $i => $stored) {
                if (hash_equals($stored, $hash)) {
                    $index = $i;
                }
            }
            if ($index === null) {
                return false;
            }
            unset($codes[$index]);
            $fresh->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();

            return true;
        });
    }

    private static function randomChunk(int $length): string
    {
        $out = '';
        $max = strlen(self::CODE_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
