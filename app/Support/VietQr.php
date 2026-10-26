<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Tạo chuỗi VietQR chuyển khoản (chuẩn EMVCo QR / NAPAS 247) — tạo cục bộ, không gọi dịch vụ ngoài.
 * Cấu trúc TLV: 00 phiên bản · 01 kiểu (12 = có số tiền) · 38 tài khoản thụ hưởng (GUID A000000727, BIN + số tài khoản,
 * dịch vụ QRIBFTTA) · 53 tiền tệ 704 · 54 số tiền · 58 VN · 62/08 nội dung · 63 CRC16-CCITT.
 */
final class VietQr
{
    public static function payload(string $bin, string $account, ?int $amount = null, ?string $memo = null): string
    {
        $tlv = fn (string $id, string $value) => $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
        $account = preg_replace('/[^0-9A-Za-z]/', '', $account);
        $beneficiary = $tlv('00', $bin).$tlv('01', $account);
        $data = $tlv('00', '01')
            .$tlv('01', $amount ? '12' : '11')
            .$tlv('38', $tlv('00', 'A000000727').$tlv('01', $beneficiary).$tlv('02', 'QRIBFTTA'))
            .$tlv('53', '704')
            .($amount ? $tlv('54', (string) $amount) : '')
            .$tlv('58', 'VN');
        $memo = self::memo($memo);
        if ($memo !== '') {
            $data .= $tlv('62', $tlv('08', $memo));
        }
        $data .= '6304';

        return $data.self::crc16($data);
    }

    /** Nội dung chuyển khoản: bỏ dấu, chỉ chữ / số / khoảng trắng, tối đa 25 ký tự (app ngân hàng hiển thị ổn định). */
    public static function memo(?string $memo): string
    {
        return trim(mb_substr(preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii((string) $memo)), 0, 25));
    }

    /** CRC16-CCITT (đa thức 0x1021, giá trị đầu 0xFFFF), 4 ký tự hex in hoa. */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public static function bankName(?string $bin): ?string
    {
        return $bin ? (config('vietqr.banks')[$bin] ?? null) : null;
    }
}
