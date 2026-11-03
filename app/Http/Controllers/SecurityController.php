<?php

namespace App\Http\Controllers;

use App\Models\LoginEvent;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Support\Security\Totp;
use App\Support\Security\TwoFactor;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Bảo mật tài khoản cá nhân: xác thực 2 lớp, mã khôi phục, lịch sử đăng nhập, xác minh email.
 * Mọi thao tác thay đổi đều yêu cầu nhập lại mật khẩu.
 */
class SecurityController extends Controller
{
    /** Khóa flash chứa mã khôi phục (đã mã hóa) để hiển thị đúng một lần. */
    private const CODES_FLASH = 'two_factor_codes';

    public function show(Request $request)
    {
        $user = $request->user();
        $setup = null;
        if (! $user->hasTwoFactorEnabled() && $user->two_factor_secret) {
            $uri = Totp::provisioningUri($user->two_factor_secret, $user->email, TwoFactor::issuer());
            $setup = ['secret' => trim(chunk_split($user->two_factor_secret, 4, ' ')), 'qr' => TwoFactor::qrSvg($uri)];
        }

        $recoveryCodes = null;
        if ($encrypted = $request->session()->get(self::CODES_FLASH)) {
            try {
                $recoveryCodes = Crypt::decrypt($encrypted);
            } catch (\Throwable) {
                $recoveryCodes = null;
            }
        }

        $events = $user->loginEvents()->latest('created_at')->latest('id')->limit(30)->get();

        return view('profile.security', [
            'user' => $user,
            'setup' => $setup,
            'recoveryCodes' => $recoveryCodes,
            'remainingCodes' => TwoFactor::remainingRecoveryCodes($user),
            'events' => $events,
            'retentionDays' => LoginEvent::RETENTION_DAYS,
        ]);
    }

    /** Bắt đầu bật 2FA: tạo khóa bí mật (lưu mã hóa, CHƯA có hiệu lực cho tới khi xác nhận mã). */
    public function enable(Request $request)
    {
        $this->confirmPassword($request);
        $user = $request->user();
        abort_if($user->hasTwoFactorEnabled(), 422, __('Xác thực 2 lớp đã được bật.'));

        $user->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null])->saveQuietly();

        return redirect()->route('profile.security')->with('info', __('Quét mã QR bằng ứng dụng xác thực rồi nhập mã 6 số để hoàn tất.'));
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => __('Mã xác thực')]);
        $user = $request->user();
        if ($user->hasTwoFactorEnabled() || ! $user->two_factor_secret) {
            return redirect()->route('profile.security');
        }

        $step = Totp::verify($user->two_factor_secret, $request->code);
        if ($step === null) {
            throw ValidationException::withMessages(['code' => __('Mã xác thực không đúng. Kiểm tra lại giờ trên điện thoại và nhập mã mới nhất.')]);
        }

        $codes = TwoFactor::enable($user, $user->two_factor_secret, $step);
        ActivityLogger::log('updated', $user, 'Bật xác thực 2 lớp');

        return redirect()->route('profile.security')
            ->with(self::CODES_FLASH, Crypt::encrypt($codes))
            ->with('success', __('Đã bật xác thực 2 lớp. Hãy lưu các mã khôi phục bên dưới ở nơi an toàn.'));
    }

    /** Tắt 2FA (hoặc hủy thiết lập đang dở). Cần mật khẩu + mã hiện tại / mã khôi phục. */
    public function disable(Request $request)
    {
        $this->confirmPassword($request);
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            $request->validate(['code' => ['required', 'string', 'max:30']], [], ['code' => __('Mã xác thực')]);
            $code = (string) $request->code;
            $ok = preg_match('/^\s*\d{6}\s*$/', $code) ? TwoFactor::verifyTotp($user, $code) : TwoFactor::useRecoveryCode($user, $code);
            if (! $ok) {
                throw ValidationException::withMessages(['code' => __('Mã xác thực không đúng hoặc đã hết hạn.')]);
            }
            ActivityLogger::log('updated', $user, 'Tắt xác thực 2 lớp');
        }

        TwoFactor::disable($user);

        return redirect()->route('profile.security')->with('success', __('Đã tắt xác thực 2 lớp.'));
    }

    public function regenerateCodes(Request $request)
    {
        $this->confirmPassword($request, 'regen_password');
        $user = $request->user();
        abort_unless($user->hasTwoFactorEnabled(), 422, __('Chưa bật xác thực 2 lớp.'));

        $codes = TwoFactor::regenerateRecoveryCodes($user);
        ActivityLogger::log('updated', $user, 'Tạo lại mã khôi phục xác thực 2 lớp');

        return redirect()->route('profile.security')
            ->with(self::CODES_FLASH, Crypt::encrypt($codes))
            ->with('success', __('Đã tạo bộ mã khôi phục mới. Các mã cũ không còn dùng được.'));
    }

    /** Bật mã xác thực qua email — bước 1: gửi mã tới email của chính mình (chứng minh email nhận được thư). */
    public function emailOtpStart(Request $request)
    {
        $this->confirmPassword($request);
        $user = $request->user();
        abort_if($user->hasTwoFactorEnabled(), 422, __('Bạn đang dùng ứng dụng xác thực — không cần mã qua email.'));
        $result = \App\Services\Auth\EmailOtp::send('enable', 'user:'.$user->getKey(), $user->email, $user->name);
        if ($result === 'failed') {
            return back()->with('error', __('Không gửi được email tới :email. Kiểm tra cấu hình email gửi đi (Hệ thống → Email gửi đi).', ['email' => $user->email]));
        }

        return redirect()->route('profile.security')->with('email_otp_sent', true)
            ->with($result === 'sent' ? 'success' : 'warning', $result === 'sent'
                ? __('Đã gửi mã 6 số tới :email. Nhập mã để hoàn tất.', ['email' => \App\Services\Auth\EmailOtp::mask($user->email)])
                : __('Vui lòng đợi :s giây rồi gửi lại.', ['s' => \App\Services\Auth\EmailOtp::cooldown('enable', 'user:'.$user->getKey())]));
    }

    /** Bật mã xác thực qua email — bước 2: nhập đúng mã vừa nhận. */
    public function emailOtpConfirm(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => __('Mã xác thực')]);
        if (! \App\Services\Auth\EmailOtp::verify('enable', 'user:'.$user->getKey(), $data['code'])) {
            return back()->with('email_otp_sent', true)->withErrors(['email_otp_code' => __('Mã xác thực không đúng hoặc đã hết hạn.')]);
        }
        $user->forceFill(['email_otp_enabled' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->saveQuietly();
        ActivityLogger::log('updated', $user, 'Bật mã xác thực qua email khi đăng nhập');

        return redirect()->route('profile.security')->with('success', __('Đã bật mã xác thực qua email. Lần đăng nhập sau, nhập mã gửi tới email sau khi nhập mật khẩu.'));
    }

    public function emailOtpDisable(Request $request)
    {
        $this->confirmPassword($request);
        $user = $request->user();
        abort_if(Setting::get('require_email_otp', '0') === '1', 422, __('Quản trị đang bắt buộc mã xác thực qua email cho mọi người.'));
        $user->forceFill(['email_otp_enabled' => false])->saveQuietly();
        ActivityLogger::log('updated', $user, 'Tắt mã xác thực qua email khi đăng nhập');

        return redirect()->route('profile.security')->with('success', __('Đã tắt mã xác thực qua email.'));
    }

    public function sendVerification(Request $request)
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return back()->with('info', __('Email của bạn đã được xác minh.'));
        }
        $user->sendEmailVerificationNotification();

        return back()->with('success', config('mail.default') === 'log'
            ? __('Đã tạo liên kết xác minh. Máy chủ chưa cấu hình email nên liên kết được ghi vào storage/logs/laravel.log.')
            : __('Đã gửi email xác minh tới :email.', ['email' => $user->email]));
    }

    /** Liên kết trong email (có chữ ký, hết hạn). Phải đăng nhập đúng tài khoản đó. */
    public function verify(EmailVerificationRequest $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
            ActivityLogger::log('updated', $request->user(), 'Xác minh email '.$request->user()->email);
        }

        return redirect()->route('profile.security')->with('success', __('Đã xác minh email thành công.'));
    }

    private function confirmPassword(Request $request, string $field = 'password'): void
    {
        $request->validate([$field => ['required', 'string', 'current_password']], [], [$field => __('Mật khẩu hiện tại')]);
    }
}
