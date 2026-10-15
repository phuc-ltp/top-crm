<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\Organization;
use App\Support\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ngữ cảnh cho mọi yêu cầu /api: chạy TRƯỚC khi tìm bản ghi trên route (xem bootstrap/app.php).
 * - Tài khoản phải đang hoạt động.
 * - Khóa API: chỉ dùng trong tổ chức của khóa; người tạo phải còn là thành viên đang hoạt động
 *   và tổ chức chưa bị tạm ngưng (quản trị nền tảng: tổ chức phải đang hoạt động).
 * - Phiên đăng nhập web: dùng tổ chức đang chọn, cũng phải còn là thành viên.
 * - Áp dụng chính sách bắt buộc 2FA cho tài khoản quản trị.
 */
class ApiContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);   // để auth:sanctum trả lỗi 401
        }
        if (! $user->isActive()) {
            return $this->deny(__('Tài khoản đã bị khóa.'));
        }

        $token = $user->currentAccessToken();
        $orgId = $token instanceof ApiToken ? (int) $token->organization_id : (int) $user->current_organization_id;

        $allowed = $orgId && ($user->isPlatformAdmin()
            ? Organization::whereKey($orgId)->where('status', 'active')->exists()
            : $user->organizations()->whereKey($orgId)->wherePivot('status', 'active')->where('organizations.status', 'active')->exists());
        if (! $allowed) {
            return $this->deny(__('Khóa API hoặc tài khoản không còn quyền truy cập tổ chức này.'));
        }
        app(CurrentOrganization::class)->set($orgId);

        if (RequireTwoFactor::enabled() && ! $user->hasTwoFactorEnabled() && RequireTwoFactor::appliesTo($user)) {
            return $this->deny(__('Tài khoản quản trị phải bật xác thực 2 lớp trước khi tiếp tục làm việc.'));
        }

        if ($token instanceof ApiToken && $token->last_used_ip !== $request->ip()) {
            $token->forceFill(['last_used_ip' => $request->ip()])->save();
        }

        SetLocale::apply($request);

        return $next($request);
    }

    private function deny(string $message): Response
    {
        return response()->json(['success' => false, 'message' => $message], 403);
    }
}
