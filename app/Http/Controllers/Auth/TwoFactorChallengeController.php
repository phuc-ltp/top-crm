<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\Auth\LoginFlow;
use App\Support\Security\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Bước 2 của đăng nhập: nhập mã 6 số từ ứng dụng xác thực hoặc một mã khôi phục. */
class TwoFactorChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function show(Request $request, LoginFlow $flow)
    {
        if (! $flow->pendingUser($request)) {
            return redirect()->route('login')->with('warning', __('Phiên xác thực đã hết hạn. Vui lòng đăng nhập lại.'));
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, LoginFlow $flow)
    {
        $user = $flow->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->with('warning', __('Phiên xác thực đã hết hạn. Vui lòng đăng nhập lại.'));
        }

        $data = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:30'],
        ], [], ['code' => __('Mã xác thực'), 'recovery_code' => __('Mã khôi phục')]);

        // Giới hạn theo TÀI KHOẢN (không theo IP) để không thể dò mã bằng nhiều địa chỉ
        $key = 'two-factor|'.$user->getKey();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $request->session()->forget(LoginFlow::PENDING_KEY);
            $flow->record($request, $user, 'locked_out', 'totp');

            return redirect()->route('login')->with('error', __('Bạn đã nhập sai mã quá nhiều lần. Vui lòng thử lại sau :minutes phút.', ['minutes' => ceil(RateLimiter::availableIn($key) / 60)]));
        }

        $usingRecovery = filled($data['recovery_code'] ?? null);
        $ok = $usingRecovery
            ? TwoFactor::useRecoveryCode($user, $data['recovery_code'])
            : TwoFactor::verifyTotp($user, $data['code']);

        if (! $ok) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $flow->record($request, $user, 'two_factor_failed', $usingRecovery ? 'recovery_code' : 'totp');
            $field = $usingRecovery ? 'recovery_code' : 'code';
            throw ValidationException::withMessages([
                $field => $usingRecovery ? __('Mã khôi phục không đúng hoặc đã được dùng.') : __('Mã xác thực không đúng hoặc đã hết hạn.'),
            ]);
        }

        RateLimiter::clear($key);
        $pending = $flow->pendingData($request);
        $response = $flow->complete($request, $user, (string) ($pending['method'] ?? 'password'), (bool) ($pending['remember'] ?? false), $usingRecovery ? 'recovery_code' : 'totp');

        if ($usingRecovery) {
            $left = TwoFactor::remainingRecoveryCodes($user->fresh());
            ActivityLogger::log('updated', $user, 'Dùng mã khôi phục để đăng nhập (còn '.$left.' mã)');
            $response->with('warning', __('Bạn vừa dùng một mã khôi phục, còn lại :count mã. Hãy tạo bộ mã mới nếu sắp hết.', ['count' => $left]));
        }

        return $response;
    }
}
