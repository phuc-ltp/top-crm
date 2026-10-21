<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tài khoản được tạo nhanh với mật khẩu tạm (users.must_change_password) phải đổi mật khẩu trước khi làm việc:
 * chỉ được vào trang Hồ sơ (đổi mật khẩu) hoặc đăng xuất.
 */
class RequirePasswordChange
{
    private const ALLOWED_ROUTES = ['profile.edit', 'profile.password', 'logout', 'locale.update', 'preferences.theme',
        'pages.terms', 'pages.privacy', 'pages.support'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->must_change_password || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        $message = __('Bạn đang dùng mật khẩu tạm. Vui lòng đặt mật khẩu mới trước khi tiếp tục.');
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('profile.edit')->with('warning', $message);
    }
}
