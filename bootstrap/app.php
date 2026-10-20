<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\SetCurrentOrganization::class,
            \App\Http\Middleware\RequireTwoFactor::class,   // sau khi xác định tổ chức (vai trò theo tổ chức)
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        // Đứng sau reverse proxy / Cloudflare: TRUSTED_PROXIES=* hoặc danh sách IP (ngăn cách bởi dấu phẩy) để nhận đúng IP người dùng và HTTPS
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        // API: phiên web (cùng tên miền) hoặc khóa API Bearer
        $middleware->statefulApi();
        // Xác định tài khoản / tổ chức TRƯỚC khi tìm bản ghi trên route ({customer}...) để không bao giờ
        // tìm nhầm sang tổ chức khác (khóa API của tổ chức A không đọc được dữ liệu tổ chức B)
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\EnsureUserIsActive::class);
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\SetCurrentOrganization::class);
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\ApiContext::class);
        $middleware->alias([
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'setup.redirect' => \App\Http\Middleware\RedirectToSetup::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Phản hồi JSON tiếng Việt cho các yêu cầu AJAX
        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('Bạn không có quyền thực hiện thao tác này.')], 403);
            }
        });

        // Phiên hết hạn (CSRF 419): quay lại form, giữ dữ liệu đã nhập (trừ mật khẩu) và báo rõ ràng
        // (Laravel chuyển TokenMismatchException thành HttpException 419 trước khi gọi callback)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }
            \App\Http\Middleware\SetLocale::apply($request);   // CSRF chạy trước middleware ngôn ngữ
            if ($request->expectsJson()) {
                return response()->json(['message' => __('Phiên làm việc đã hết hạn. Vui lòng tải lại trang.')], 419);
            }
            if ($request->isMethod('GET')) {
                return null;
            }

            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']))
                ->with('warning', __('Phiên làm việc đã hết hạn do để trang mở quá lâu. Dữ liệu đã được giữ lại — vui lòng nhập lại mật khẩu (nếu có) và bấm gửi lần nữa.'));
        });
    })->create();
