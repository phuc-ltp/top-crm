<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác định tổ chức đang làm việc cho người dùng đã đăng nhập và kiểm tra quyền thành viên.
 * Tổ chức hiện tại không hợp lệ (bị gỡ khỏi tổ chức / tổ chức tạm ngưng) → chuyển sang tổ chức hợp lệ khác;
 * không còn tổ chức nào → đăng xuất.
 */
class SetCurrentOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $memberships = $user->isPlatformAdmin()
            ? Organization::where('status', 'active')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)
            : $user->organizations()
                ->wherePivot('status', 'active')
                ->where('organizations.status', 'active')
                ->orderBy('organizations.id')
                ->pluck('organizations.id')->map(fn ($id) => (int) $id);

        $orgId = (int) $user->current_organization_id;
        if (! $memberships->contains($orgId)) {
            $orgId = (int) $memberships->first();
            if (! $orgId) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                SetLocale::apply($request);
                $message = __('Tài khoản của bạn chưa thuộc tổ chức nào đang hoạt động. Vui lòng liên hệ quản trị viên.');

                return $request->expectsJson()
                    ? response()->json(['message' => $message], 403)
                    : redirect()->route('login')->withErrors(['login' => $message]);
            }
            $user->forceFill(['current_organization_id' => $orgId])->saveQuietly();
        }

        app(CurrentOrganization::class)->set($orgId);

        return $next($request);
    }
}
