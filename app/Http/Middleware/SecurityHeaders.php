<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header bảo mật cho mọi phản hồi web.
 *  - Luôn bật: chống nhúng iframe từ trang khác, chống đoán MIME, giới hạn Referrer, tắt camera / micro / vị trí.
 *  - Chỉ ở production (APP_ENV=production):
 *    + Content-Security-Policy: chỉ tải tài nguyên từ chính tên miền (+ Google Fonts); không plugin, không nhúng từ trang khác.
 *      'unsafe-inline' cho script/style vì các trang có script nội tuyến nhỏ (đã escape dữ liệu ở Blade).
 *    + Strict-Transport-Security khi chạy HTTPS: trình duyệt luôn dùng HTTPS cho tên miền trong 1 năm.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->isProduction()) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: blob:",
                "connect-src 'self'",
                "frame-ancestors 'self'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]));
            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        }

        return $response;
    }
}
