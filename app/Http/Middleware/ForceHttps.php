<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production có APP_URL https:// → mọi truy cập http:// chuyển hẳn (301) sang https://.
 * Cookie phiên chỉ gửi qua https (SESSION_SECURE_COOKIE), nên vào bằng http sẽ không đăng nhập được ("419").
 * Chạy sau TrustProxies nên đứng sau Cloudflare / proxy vẫn nhận đúng giao thức.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! $request->secure() && str_starts_with((string) config('app.url'), 'https://')) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
