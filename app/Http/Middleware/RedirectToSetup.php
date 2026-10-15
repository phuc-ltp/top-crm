<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\SetupController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Khi chưa có Super Admin, chuyển hướng trang đăng nhập sang trang khởi tạo. */
class RedirectToSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && SetupController::needsSetup()) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
