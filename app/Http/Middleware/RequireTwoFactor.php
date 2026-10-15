<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Khi cài đặt "Bắt buộc xác thực 2 lớp cho tài khoản quản trị" được bật: tài khoản quản trị
 * chưa bật 2FA chỉ được vào trang Bảo mật tài khoản để thiết lập (không bị khóa ngoài).
 */
class RequireTwoFactor
{
    public const SETTING = 'require_2fa_admins';

    public const ADMIN_ROLES = ['Organization Owner', 'Organization Administrator'];

    /** Các trang vẫn mở để người dùng tự thiết lập 2FA hoặc thoát ra. */
    private const ALLOWED_ROUTES = [
        'profile.security', 'two-factor.enable', 'two-factor.confirm', 'two-factor.disable', 'two-factor.recovery-codes',
        'verification.send', 'verification.verify', 'logout', 'locale.update', 'preferences.theme', 'workspace.switch',
        'pages.terms', 'pages.privacy', 'pages.support',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->hasTwoFactorEnabled() || ! self::enabled() || ! self::appliesTo($user)
            || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        $message = __('Tài khoản quản trị phải bật xác thực 2 lớp trước khi tiếp tục làm việc.');
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('profile.security')->with('warning', $message);
    }

    public static function enabled(): bool
    {
        return Setting::get(self::SETTING, '0') === '1';
    }

    public static function appliesTo(User $user): bool
    {
        return $user->isPlatformAdmin() || $user->hasAnyRole(self::ADMIN_ROLES);
    }
}
