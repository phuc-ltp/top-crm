<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Đăng xuất ngay người dùng bị khóa tài khoản (kể cả đang có phiên). */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => __('Tài khoản đã bị khóa.')], 403);
            }

            return redirect()->route('login')->withErrors(['login' => __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.')]);
        }

        return $next($request);
    }
}
