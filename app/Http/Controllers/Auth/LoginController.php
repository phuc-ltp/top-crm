<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
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

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [], ['login' => 'Email hoặc tên đăng nhập', 'password' => 'Mật khẩu']);

        // Đăng nhập bằng email (có @) hoặc tên đăng nhập
        $login = Str::lower(trim($data['login']));
        $field = str_contains($login, '@') ? 'email' : 'username';
        $key = $login.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'login' => 'Bạn đã đăng nhập sai quá '.self::MAX_ATTEMPTS.' lần. Vui lòng thử lại sau '.ceil($seconds / 60).' phút.',
            ]);
        }

        if (! Auth::attempt([$field => $login, 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $user = User::where($field, $login)->first();
            ActivityLogger::log('login_failed', $user, 'Đăng nhập thất bại: '.$login);
            $left = max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($key));
            throw ValidationException::withMessages([
                'login' => 'Thông tin đăng nhập hoặc mật khẩu không đúng.'.($left > 0 ? " Còn $left lần thử." : ''),
            ]);
        }

        $user = Auth::user();
        if (! $user->isActive()) {
            Auth::logout();
            throw ValidationException::withMessages(['login' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        ActivityLogger::log('login', $user, $user->name.' đăng nhập');

        return redirect()->intended(route('dashboard'))->with('success', 'Chào mừng trở lại, '.$user->name.'!');
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

        return redirect()->route('login')->with('success', 'Bạn đã đăng xuất.');
    }
}
