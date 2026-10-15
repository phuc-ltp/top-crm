<?php

namespace App\Services\Auth;

use App\Http\Middleware\SetLocale;
use App\Models\LoginEvent;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Notifications\NewDeviceLoginNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Quy trình đăng nhập dùng chung cho mật khẩu và Google/Facebook:
 * mật khẩu đúng → (nếu bật 2FA) chờ mã → hoàn tất: ghi lịch sử, cảnh báo thiết bị mới.
 */
class LoginFlow
{
    /** Khóa phiên giữ người dùng đang chờ nhập mã 2 lớp (CHƯA đăng nhập). */
    public const PENDING_KEY = 'auth.two_factor_pending';

    /** Thời gian tối đa để nhập mã 2 lớp. */
    public const PENDING_TTL = 300;

    /** Cookie nhận diện trình duyệt (chuỗi ngẫu nhiên; chỉ lưu bản băm trong DB). */
    public const DEVICE_COOKIE = 'crm_device';

    /** Bước 1 đã qua (mật khẩu / OAuth đúng, tài khoản đang hoạt động). */
    public function begin(Request $request, User $user, string $method, bool $remember = false)
    {
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->regenerate();
            $request->session()->put(self::PENDING_KEY, [
                'id' => $user->getKey(), 'remember' => $remember, 'method' => $method, 'at' => now()->getTimestamp(),
            ]);

            return redirect()->route('two-factor.challenge');
        }

        return $this->complete($request, $user, $method, $remember);
    }

    /** Người dùng đang chờ nhập mã 2 lớp (null nếu không có hoặc đã hết hạn). */
    public function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING_KEY);
        if (! is_array($pending) || (now()->getTimestamp() - (int) ($pending['at'] ?? 0)) > self::PENDING_TTL) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }
        $user = User::find($pending['id'] ?? null);
        if (! $user || ! $user->isActive() || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        return $user;
    }

    public function pendingData(Request $request): array
    {
        return (array) $request->session()->get(self::PENDING_KEY, []);
    }

    public function complete(Request $request, User $user, string $method, bool $remember = false, ?string $secondFactor = null)
    {
        $request->session()->forget(self::PENDING_KEY);
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        // Ngôn ngữ đã chọn ở trang đăng nhập trở thành lựa chọn của tài khoản (nếu tài khoản chưa chọn)
        if (! $user->locale && in_array(app()->getLocale(), SetLocale::supported(), true) && $request->cookie(SetLocale::COOKIE)) {
            $user->forceFill(['locale' => app()->getLocale()])->saveQuietly();
        }

        $label = LoginEvent::METHODS[$method] ?? $method;
        ActivityLogger::log('login', $user, $user->name.' đăng nhập'.($method === 'password' ? '' : " bằng $label").($secondFactor ? ' (xác thực 2 lớp)' : ''));

        $deviceHash = $this->deviceHash($request);
        $hadLogins = $user->loginEvents()->where('event', 'login')->exists();
        $isNew = $hadLogins && ! $user->loginEvents()->where('event', 'login')->where('device_hash', $deviceHash)->exists();
        $this->record($request, $user, 'login', $secondFactor ?? $method, $deviceHash, $isNew);

        if ($isNew) {
            $this->alertNewDevice($request, $user);
        }

        return redirect()->intended(route('dashboard'))->with('success', __('Chào mừng trở lại, :name!', ['name' => $user->name]));
    }

    public function record(Request $request, ?User $user, string $event, ?string $method = null, ?string $deviceHash = null, bool $isNew = false): void
    {
        LoginEvent::create([
            'user_id' => $user?->getKey(),
            'event' => $event,
            'method' => $method,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'device_hash' => $deviceHash ?? $this->deviceHash($request, false),
            'is_new_device' => $isNew,
        ]);
    }

    /** Băm cookie thiết bị; tạo cookie mới (1 năm, HttpOnly) nếu trình duyệt chưa có. */
    public function deviceHash(Request $request, bool $issue = true): ?string
    {
        $token = (string) $request->cookie(self::DEVICE_COOKIE);
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            if (! $issue) {
                return null;
            }
            $token = Str::random(40);
            Cookie::queue(Cookie::make(self::DEVICE_COOKIE, $token, 60 * 24 * 365, null, null, null, true, false, 'lax'));
        }

        return hash('sha256', $token);
    }

    private function alertNewDevice(Request $request, User $user): void
    {
        $device = \App\Support\Security\UserAgent::describe($request->userAgent());
        $locale = $user->preferredLocale() ?: config('app.locale');
        try {
            CrmNotification::send($user, 'security',
                __('Đăng nhập mới từ thiết bị chưa từng dùng: :device (IP :ip). Nếu không phải bạn, hãy đổi mật khẩu ngay.', ['device' => $device, 'ip' => $request->ip()], $locale),
                route('profile.security'), 'bi-shield-exclamation');
            $user->notify(new NewDeviceLoginNotification($device, (string) $request->ip(), now()));
        } catch (\Throwable $e) {
            // Không để lỗi gửi thư làm hỏng việc đăng nhập
            Log::warning('Không gửi được cảnh báo đăng nhập mới: '.$e->getMessage());
        }
    }
}
