<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\ActivityLogger;
use App\Services\Auth\EmailOtp;
use App\Services\Auth\LoginFlow;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Đăng ký tài khoản qua link mời (công khai): điền thông tin → nhận mã 6 số qua email → nhập mã → tạo tài khoản
 * trong tổ chức với vai trò của lời mời và đăng nhập. Email đã có tài khoản bị từ chối (không thể chiếm tài khoản).
 */
class JoinController extends Controller
{
    private const SESSION = 'join.pending';

    private function invitationOr404(string $token): Invitation
    {
        $inv = Invitation::findByToken($token);
        abort_unless($inv, 404);

        return $inv;
    }

    public function show(Request $request, string $token)
    {
        if ($request->user()) {
            return redirect()->route('dashboard')->with('info', __('Bạn đã đăng nhập — hãy đăng xuất trước khi đăng ký tài khoản khác.'));
        }
        $inv = $this->invitationOr404($token);

        return response()->view('auth.join', ['token' => $token, 'invitation' => $inv, 'usable' => $inv->isUsable(),
            'company' => \App\Models\Setting::allCached($inv->organization_id)['company_name'] ?? ''])->header('X-Robots-Tag', 'noindex');
    }

    public function submit(Request $request, string $token)
    {
        $inv = $this->invitationOr404($token);
        abort_unless($inv->isUsable(), 410);
        if (filled($request->input('website'))) {                     // bẫy spam (trường ẩn)
            return redirect()->route('join.show', $token);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ], ['email.unique' => __('Email này đã có tài khoản — hãy đăng nhập hoặc dùng “Quên mật khẩu”.')],
            ['name' => __('Họ tên'), 'email' => 'Email', 'phone' => __('Số điện thoại'), 'password' => __('Mật khẩu'), 'terms' => __('Điều khoản sử dụng')]);
        $email = mb_strtolower($data['email']);

        $result = EmailOtp::send('join', 'join:'.$inv->id.':'.$email, $email, $data['name']);
        if ($result === 'failed') {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => __('Không gửi được email xác thực. Kiểm tra lại địa chỉ email hoặc liên hệ người đã gửi link.')]);
        }
        // Mật khẩu chỉ giữ dạng băm trong phiên cho tới khi xác thực xong
        $request->session()->put(self::SESSION, ['invitation' => $inv->id, 'name' => $data['name'], 'email' => $email,
            'phone' => $data['phone'] ?? null, 'password' => Hash::make($data['password']), 'at' => now()->getTimestamp()]);

        return redirect()->route('join.verify', $token);
    }

    public function verifyForm(Request $request, string $token)
    {
        $inv = $this->invitationOr404($token);
        $pending = $this->pending($request, $inv);
        if (! $pending) {
            return redirect()->route('join.show', $token)->with('warning', __('Phiên đăng ký đã hết hạn, vui lòng điền lại.'));
        }

        return view('auth.join-verify', ['token' => $token, 'maskedEmail' => EmailOtp::mask($pending['email']),
            'cooldown' => EmailOtp::cooldown('join', 'join:'.$inv->id.':'.$pending['email'])]);
    }

    public function resend(Request $request, string $token)
    {
        $inv = $this->invitationOr404($token);
        $pending = $this->pending($request, $inv);
        if (! $pending) {
            return redirect()->route('join.show', $token);
        }
        $result = EmailOtp::send('join', 'join:'.$inv->id.':'.$pending['email'], $pending['email'], $pending['name']);

        return back()->with(...match ($result) {
            'sent' => ['success', __('Đã gửi mã mới tới :email.', ['email' => EmailOtp::mask($pending['email'])])],
            'cooldown' => ['warning', __('Vui lòng đợi :s giây rồi gửi lại.', ['s' => EmailOtp::cooldown('join', 'join:'.$inv->id.':'.$pending['email'])])],
            default => ['error', __('Không gửi được email. Vui lòng liên hệ người đã gửi link.')],
        });
    }

    public function verify(Request $request, string $token, LoginFlow $flow)
    {
        $inv = $this->invitationOr404($token);
        $pending = $this->pending($request, $inv);
        if (! $pending) {
            return redirect()->route('join.show', $token)->with('warning', __('Phiên đăng ký đã hết hạn, vui lòng điền lại.'));
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => __('Mã xác thực')]);
        if (! EmailOtp::verify('join', 'join:'.$inv->id.':'.$pending['email'], $data['code'])) {
            return back()->withErrors(['code' => __('Mã xác thực không đúng hoặc đã hết hạn.')]);
        }

        $user = app(CurrentOrganization::class)->run($inv->organization_id, fn () => DB::transaction(function () use ($inv, $pending) {
            $locked = Invitation::withoutGlobalScopes()->whereKey($inv->id)->lockForUpdate()->first();
            abort_unless($locked && $locked->isUsable(), 410, __('Link mời đã hết hạn hoặc hết lượt đăng ký.'));
            abort_if(User::withTrashed()->where('email', $pending['email'])->exists(), 409, __('Email này đã có tài khoản.'));
            $user = app(\App\Services\Auth\AccountProvisioner::class)->create($locked->organization_id, ['name' => $pending['name'], 'email' => $pending['email'],
                'phone' => $pending['phone'], 'password_hash' => $pending['password'], 'role' => $locked->role, 'invited_via_id' => $locked->id]);
            $locked->increment('uses');
            ActivityLogger::log('created', $user, 'Đăng ký tài khoản qua link mời '.$user->email.' (vai trò: '.$locked->role.')');

            return $user;
        }));
        $request->session()->forget(self::SESSION);
        if ($inv->created_by && ($creator = User::find($inv->created_by))) {
            CrmNotification::send($creator, 'security', __(':name (:email) vừa đăng ký tài khoản qua link mời của bạn.', ['name' => $user->name, 'email' => $user->email]),
                route('users.index'), 'bi-person-plus');
        }

        return $flow->complete($request, $user, 'invitation')
            ->with('success', __('Chào mừng :name! Tài khoản của bạn đã được tạo.', ['name' => $user->name]));
    }

    private function pending(Request $request, Invitation $inv): ?array
    {
        $p = $request->session()->get(self::SESSION);
        if (! is_array($p) || (int) $p['invitation'] !== $inv->id || now()->getTimestamp() - (int) $p['at'] > EmailOtp::TTL) {
            return null;
        }

        return $p;
    }
}
