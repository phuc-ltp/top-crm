<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chọn ngôn ngữ giao diện cho mỗi yêu cầu, theo thứ tự ưu tiên:
 * lựa chọn của người dùng đã đăng nhập → phiên làm việc → cookie (khách) → mặc định hệ thống.
 */
class SetLocale
{
    public const COOKIE = 'crm_locale';

    public static function supported(): array
    {
        return array_keys(config('crm.locales', ['vi' => 'Tiếng Việt']));
    }

    public function handle(Request $request, Closure $next): Response
    {
        self::apply($request);

        return $next($request);
    }

    /** Xác định và áp dụng ngôn ngữ cho yêu cầu (dùng cả ở trình xử lý lỗi, nơi middleware chưa chạy). */
    public static function apply(Request $request): string
    {
        $candidates = [
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->cookie(self::COOKIE),
        ];
        $locale = collect($candidates)->first(fn ($l) => is_string($l) && in_array($l, self::supported(), true))
            ?? config('app.locale');

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $locale;
    }
}
