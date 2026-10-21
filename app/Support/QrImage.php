<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Mã QR dạng PNG vẽ bằng GD từ ma trận của bacon/bacon-qr-code (bước 5.5).
 * Dùng PNG thay vì SVG để dompdf in tem ổn định.
 */
final class QrImage
{
    /** @return string dữ liệu PNG nhị phân */
    public static function png(string $text, int $moduleSize = 6, int $margin = 2): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $n = $matrix->getWidth();
        $px = ($n + 2 * $margin) * $moduleSize;
        $img = imagecreatetruecolor($px, $px);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $x0 = ($x + $margin) * $moduleSize;
                    $y0 = ($y + $margin) * $moduleSize;
                    imagefilledrectangle($img, $x0, $y0, $x0 + $moduleSize - 1, $y0 + $moduleSize - 1, $black);
                }
            }
        }
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    public static function dataUri(string $text, int $moduleSize = 6): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($text, $moduleSize));
    }
}
