<?php

namespace App\Support;

/**
 * Số thập phân chính xác cho tiền và số lượng (bcmath) — tránh sai số của số thực dấu phẩy động
 * (VD 0.1 + 0.2 ≠ 0.3). Giá trị vào/ra là chuỗi số; tính nội bộ với 8 chữ số thập phân,
 * làm tròn kết quả theo kiểu "nửa lên" (half away from zero) giống cách làm tròn tiền thông thường.
 */
final class Decimal
{
    private const SCALE = 8;

    /** Chuẩn hóa đầu vào (chuỗi / số nguyên / số thực / null) thành chuỗi số hợp lệ cho bcmath. */
    public static function of(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new \InvalidArgumentException('Số không hợp lệ.');
            }
            $value = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }
        $value = trim((string) $value);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new \InvalidArgumentException("Số không hợp lệ: $value");
        }

        return $value === '-0' ? '0' : $value;
    }

    public static function add(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcadd(self::of($a), self::of($b), self::SCALE);
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcsub(self::of($a), self::of($b), self::SCALE);
    }

    public static function mul(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcmul(self::of($a), self::of($b), self::SCALE);
    }

    public static function div(string|int|float|null $a, string|int|float|null $b): string
    {
        if (self::cmp($b, 0) === 0) {
            throw new \DivisionByZeroError('Chia cho 0.');
        }

        return bcdiv(self::of($a), self::of($b), self::SCALE);
    }

    /** So sánh: -1 / 0 / 1. */
    public static function cmp(string|int|float|null $a, string|int|float|null $b): int
    {
        return bccomp(self::of($a), self::of($b), self::SCALE);
    }

    /** Làm tròn nửa lên tới $scale chữ số thập phân; trả chuỗi có đúng $scale chữ số. */
    public static function round(string|int|float|null $value, int $scale = 2): string
    {
        $value = self::of($value);
        $half = '0.'.str_repeat('0', $scale).'5';
        $rounded = str_starts_with($value, '-') ? bcsub($value, $half, $scale) : bcadd($value, $half, $scale);

        return self::cmp($rounded, 0) === 0 ? bcadd('0', '0', $scale) : $rounded;
    }

    /** Giới hạn trong [min, max]. */
    public static function clamp(string|int|float|null $value, string|int|float $min, string|int|float|null $max = null): string
    {
        $value = self::of($value);
        if (self::cmp($value, $min) < 0) {
            return self::of($min);
        }
        if ($max !== null && self::cmp($value, $max) > 0) {
            return self::of($max);
        }

        return $value;
    }

    /** Tổng một dãy giá trị. */
    public static function sum(iterable $values): string
    {
        $total = '0';
        foreach ($values as $v) {
            $total = self::add($total, $v);
        }

        return $total;
    }
}
