<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Đăng nhập bằng Google / Facebook (OAuth).
 * Chỉ cho phép tài khoản ĐÃ ĐƯỢC CẤP trong hệ thống (khớp email) — không tự tạo tài khoản mới.
 */
class SocialLoginController extends Controller
{
    private const LABELS = ['google' => 'Google', 'facebook' => 'Facebook'];

    private function configure(string $provider): bool
    {
        if (! Setting::socialEnabled($provider)) {
            return false;
        }
        config(["services.$provider" => [
            'client_id' => Setting::get($provider.'_client_id'),
            'client_secret' => Setting::secret($provider.'_client_secret'),
            'redirect' => route('social.callback', $provider),
        ]]);

        return true;
    }

    public function redirect(string $provider)
    {
        if (! $this->configure($provider)) {
            return redirect()->route('login')->with('warning',
                __('Đăng nhập bằng :provider chưa được bật. Vui lòng đăng nhập bằng email hoặc liên hệ quản trị viên.', ['provider' => self::LABELS[$provider]]));
        }

        $driver = Socialite::driver($provider);

        return ($provider === 'facebook' ? $driver->scopes(['email']) : $driver)->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        $label = self::LABELS[$provider];
        if (! $this->configure($provider)) {
            return redirect()->route('login')->with('warning', __('Đăng nhập bằng :provider chưa được bật.', ['provider' => $label]));
        }
        if ($request->has('error')) {
            return redirect()->route('login')->with('warning', __('Bạn đã hủy đăng nhập bằng :provider.', ['provider' => $label]));
        }

        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->with('error', __('Không xác thực được với :provider. Vui lòng thử lại.', ['provider' => $label]));
        }

        $email = mb_strtolower((string) $social->getEmail());
        if ($email === '') {
            return redirect()->route('login')->with('error', __('Tài khoản :provider không cung cấp email. Vui lòng đăng nhập bằng email.', ['provider' => $label]));
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            ActivityLogger::log('login_failed', null, "Đăng nhập $label thất bại: $email chưa được cấp tài khoản");

            return redirect()->route('login')->with('error', __('Email :email chưa được cấp tài khoản. Liên hệ quản trị viên để được cấp tài khoản.', ['email' => $email]));
        }
        if (! $user->isActive()) {
            return redirect()->route('login')->with('error', __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.'));
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        ActivityLogger::log('login', $user, $user->name." đăng nhập bằng $label");

        return redirect()->intended(route('dashboard'))->with('success', __('Chào mừng trở lại, :name!', ['name' => $user->name]));
    }
}
