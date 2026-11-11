<?php

namespace App\Http\Controllers;

use App\Models\JoinRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\JoinRequestDecisionNotification;
use App\Services\ActivityLogger;
use App\Services\Auth\AccountProvisioner;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Người dùng → Yêu cầu tham gia: duyệt (chọn vai trò → tạo tài khoản) hoặc từ chối. Quyền tạo người dùng. */
class JoinRequestController extends Controller
{
    private function roles(Request $request): array
    {
        return AccountProvisioner::assignableRoles($request->user(), (int) app(CurrentOrganization::class)->id());
    }

    public function index(Request $request)
    {
        $this->authorize('create', User::class);
        $status = array_key_exists((string) $request->query('status'), JoinRequest::statuses()) ? $request->query('status') : 'pending';

        return view('join-requests.index', [
            'requests' => JoinRequest::with('reviewer:id,name')->where('status', $status)->latest()->paginate($this->perPage($request))->withQueryString(),
            'status' => $status,
            'counts' => JoinRequest::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all(),
            'roles' => $this->roles($request),
            'signupOpen' => \App\Http\Controllers\Auth\RegisterController::organization()?->id === (int) app(CurrentOrganization::class)->id(),
        ]);
    }

    public function approve(Request $request, JoinRequest $joinRequest, AccountProvisioner $accounts)
    {
        $this->authorize('create', User::class);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys($this->roles($request)))]], [], ['role' => __('Vai trò')]);

        $user = DB::transaction(function () use ($joinRequest, $data, $request, $accounts) {
            $jr = JoinRequest::whereKey($joinRequest->id)->lockForUpdate()->firstOrFail();
            if ($jr->status !== 'pending') {
                throw ValidationException::withMessages(['role' => __('Yêu cầu này đã được xử lý.')]);
            }
            if (User::withTrashed()->where('email', $jr->email)->exists()) {
                throw ValidationException::withMessages(['role' => __('Email :email đã có tài khoản — hãy từ chối yêu cầu này.', ['email' => $jr->email])]);
            }
            $user = $accounts->create($jr->organization_id, ['name' => $jr->name, 'email' => $jr->email, 'phone' => $jr->phone,
                'password_hash' => $jr->password, 'role' => $data['role']]);
            $jr->forceFill(['status' => 'approved', 'role' => $data['role'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
                'user_id' => $user->id, 'password' => '-'])->save();   // không giữ bản băm mật khẩu ở yêu cầu sau khi đã chuyển sang tài khoản
            ActivityLogger::log('created', $user, 'Duyệt yêu cầu tham gia '.$jr->email.' (vai trò: '.$data['role'].')');

            return $user;
        });
        $this->tell($joinRequest->email, new JoinRequestDecisionNotification(true, $user->name, $this->company(), $data['role']));

        return back()->with('success', __('Đã duyệt — :name đăng nhập được ngay bằng email :email.', ['name' => $user->name, 'email' => $user->email]));
    }

    public function reject(Request $request, JoinRequest $joinRequest)
    {
        $this->authorize('create', User::class);
        $data = $request->validate(['reject_reason' => ['nullable', 'string', 'max:255']], [], ['reject_reason' => __('Lý do')]);
        abort_unless($joinRequest->status === 'pending', 422, __('Yêu cầu này đã được xử lý.'));
        $joinRequest->forceFill(['status' => 'rejected', 'reject_reason' => $data['reject_reason'] ?? null, 'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(), 'password' => '-'])->save();
        ActivityLogger::log('updated', null, 'Từ chối yêu cầu tham gia '.$joinRequest->email);
        $this->tell($joinRequest->email, new JoinRequestDecisionNotification(false, $joinRequest->name, $this->company(), null, $data['reject_reason'] ?? null));

        return back()->with('success', __('Đã từ chối yêu cầu của :email.', ['email' => $joinRequest->email]));
    }

    private function company(): string
    {
        return (string) Setting::get('company_name', 'TOP CRM');
    }

    /** Email báo kết quả — lỗi gửi thư không làm hỏng việc duyệt. */
    private function tell(string $email, JoinRequestDecisionNotification $n): void
    {
        try {
            Notification::route('mail', $email)->notify($n);
        } catch (\Throwable $e) {
            Log::warning('Không gửi được email kết quả yêu cầu tham gia: '.$e->getMessage());
        }
    }
}
