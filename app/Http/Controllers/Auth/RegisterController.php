<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\JoinRequest;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\Auth\EmailOtp;
use App\Support\NotificationCatalog;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * "Tạo tài khoản" ở trang đăng nhập: điền thông tin → mã 6 số qua email → gửi YÊU CẦU THAM GIA vào công ty
 * do quản trị nền tảng chọn (Cài đặt → Bảo mật đăng nhập). Tài khoản chỉ được tạo khi quản trị duyệt.
 * Tắt khi chưa chọn công ty hoặc email gửi đi chưa hoạt động (mã xác thực không thể đến).
 */
class RegisterController extends Controller
{
    private const SESSION = 'register.pending';

    /** Công ty nhận yêu cầu tham gia (null = tính năng đang tắt). */
    public static function organization(): ?Organization
    {
        $id = (int) Setting::get('signup_organization_id', '0');
        if (! $id || ! NotificationCatalog::mailEnabled()) {
            return null;
        }

        return Organization::whereKey($id)->where('status', 'active')->first();
    }

    private static function subject(Organization $org, string $email): string
    {
        return 'register:'.$org->id.':'.$email;
    }

    public function show()
    {
        $org = self::organization();

        return response()->view('auth.register', ['org' => $org,
            'company' => $org ? (Setting::allCached($org->id)['company_name'] ?? $org->name) : null])->header('X-Robots-Tag', 'noindex');
    }

    public function submit(Request $request)
    {
        $org = self::organization();
        abort_unless($org, 404);
        if (filled($request->input('website'))) {   // bẫy spam (trường ẩn)
            return redirect()->route('register');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'message' => ['nullable', 'string', 'max:500'],
            'terms' => ['accepted'],
        ], ['email.unique' => __('Email này đã có tài khoản — hãy đăng nhập hoặc dùng “Quên mật khẩu”.')],
            ['name' => __('Họ tên'), 'email' => 'Email', 'phone' => __('Số điện thoại'), 'password' => __('Mật khẩu'), 'message' => __('Lời nhắn'), 'terms' => __('Điều khoản sử dụng')]);
        $email = mb_strtolower($data['email']);

        if (JoinRequest::withoutGlobalScopes()->where('organization_id', $org->id)->where('email', $email)->where('status', 'pending')->exists()) {
            return redirect()->route('register.done')->with('register_email', EmailOtp::mask($email))->with('register_already', true);
        }

        $result = EmailOtp::send('register', self::subject($org, $email), $email, $data['name']);
        if ($result === 'failed') {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => __('Không gửi được email xác thực. Kiểm tra lại địa chỉ email.')]);
        }
        // Mật khẩu chỉ giữ dạng băm trong phiên cho tới khi xác thực xong
        $request->session()->put(self::SESSION, ['org' => $org->id, 'name' => $data['name'], 'email' => $email, 'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? null, 'password' => Hash::make($data['password']), 'at' => now()->getTimestamp()]);

        return redirect()->route('register.verify');
    }

    public function verifyForm(Request $request)
    {
        [$org, $pending] = $this->pending($request);
        if (! $pending) {
            return redirect()->route('register')->with('warning', __('Phiên đăng ký đã hết hạn, vui lòng điền lại.'));
        }

        return view('auth.register-verify', ['maskedEmail' => EmailOtp::mask($pending['email']),
            'cooldown' => EmailOtp::cooldown('register', self::subject($org, $pending['email']))]);
    }

    public function resend(Request $request)
    {
        [$org, $pending] = $this->pending($request);
        if (! $pending) {
            return redirect()->route('register');
        }
        $subject = self::subject($org, $pending['email']);

        return back()->with(...match (EmailOtp::send('register', $subject, $pending['email'], $pending['name'])) {
            'sent' => ['success', __('Đã gửi mã mới tới :email.', ['email' => EmailOtp::mask($pending['email'])])],
            'cooldown' => ['warning', __('Vui lòng đợi :s giây rồi gửi lại.', ['s' => EmailOtp::cooldown('register', $subject)])],
            default => ['error', __('Không gửi được email. Vui lòng thử lại sau.')],
        });
    }

    public function verify(Request $request)
    {
        [$org, $pending] = $this->pending($request);
        if (! $pending) {
            return redirect()->route('register')->with('warning', __('Phiên đăng ký đã hết hạn, vui lòng điền lại.'));
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => __('Mã xác thực')]);
        if (! EmailOtp::verify('register', self::subject($org, $pending['email']), $data['code'])) {
            return back()->withErrors(['code' => __('Mã xác thực không đúng hoặc đã hết hạn.')]);
        }

        $joinRequest = app(CurrentOrganization::class)->run($org->id, fn () => DB::transaction(function () use ($pending, $request) {
            abort_if(User::withTrashed()->where('email', $pending['email'])->exists(), 409, __('Email này đã có tài khoản.'));
            $existing = JoinRequest::where('email', $pending['email'])->where('status', 'pending')->lockForUpdate()->first();
            $jr = $existing ?? new JoinRequest;
            $jr->fill(['name' => $pending['name'], 'email' => $pending['email'], 'phone' => $pending['phone'], 'message' => $pending['message'], 'ip_address' => $request->ip()]);
            $jr->forceFill(['password' => $pending['password'], 'status' => 'pending'])->save();

            return $jr;
        }));
        $request->session()->forget(self::SESSION);
        $this->notifyReviewers($org, $joinRequest);

        return redirect()->route('register.done')->with('register_email', EmailOtp::mask($joinRequest->email));
    }

    public function done(Request $request)
    {
        if (! $request->session()->has('register_email')) {
            return redirect()->route('login');
        }

        return view('auth.register-done', ['maskedEmail' => session('register_email'), 'already' => (bool) session('register_already')]);
    }

    /** Báo người có quyền tạo người dùng trong công ty (chuông + email nếu họ bật). */
    private function notifyReviewers(Organization $org, JoinRequest $jr): void
    {
        app(CurrentOrganization::class)->run($org->id, function () use ($jr) {
            $reviewers = User::inOrganization()->where('status', 'active')->get()->filter(fn (User $u) => $u->can('users.create'));
            foreach ($reviewers as $u) {
                CrmNotification::send($u, 'join_request', __(':name (:email) xin tham gia công ty — cần duyệt.', ['name' => $jr->name, 'email' => $jr->email]),
                    route('join-requests.index'), 'bi-person-plus');
            }
        });
    }

    /** @return array{0:?Organization, 1:?array} */
    private function pending(Request $request): array
    {
        $p = $request->session()->get(self::SESSION);
        $org = self::organization();
        if (! $org || ! is_array($p) || (int) $p['org'] !== $org->id || now()->getTimestamp() - (int) $p['at'] > EmailOtp::TTL) {
            return [$org, null];
        }

        return [$org, $p];
    }
}
