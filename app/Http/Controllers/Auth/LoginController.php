<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Auth\LoginFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request, LoginFlow $flow)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [], ['login' => __('Email hoặc tên đăng nhập'), 'password' => __('Mật khẩu')]);

        // Đăng nhập bằng email (có @) hoặc tên đăng nhập
        $login = Str::lower(trim($data['login']));
        $field = str_contains($login, '@') ? 'email' : 'username';
        $key = $login.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'login' => __('Bạn đã đăng nhập sai quá :max lần. Vui lòng thử lại sau :minutes phút.', ['max' => self::MAX_ATTEMPTS, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        // Chỉ kiểm tra mật khẩu, CHƯA đăng nhập (có thể còn bước xác thực 2 lớp)
        if (! Auth::validate([$field => $login, 'password' => $data['password']])) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $user = User::where($field, $login)->first();
            ActivityLogger::log('login_failed', $user, 'Đăng nhập thất bại: '.$login);
            $left = max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($key));
            // Không lưu chuỗi đã gõ khi không khớp tài khoản nào (có thể là mật khẩu gõ nhầm ô)
            $flow->record($request, $user, $left > 0 ? 'failed' : 'locked_out', 'password');
            throw ValidationException::withMessages([
                'login' => __('Thông tin đăng nhập hoặc mật khẩu không đúng.').($left > 0 ? ' '.__('Còn :left lần thử.', ['left' => $left]) : ''),
            ]);
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();
        if (! $user->isActive()) {
            $flow->record($request, $user, 'blocked', 'password');
            throw ValidationException::withMessages(['login' => __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.')]);
        }

        RateLimiter::clear($key);

        return $flow->begin($request, $user, 'password', $request->boolean('remember'));
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            ActivityLogger::log('logout', $user, $user->name.' đăng xuất');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', __('Bạn đã đăng xuất.'));
    }
}
