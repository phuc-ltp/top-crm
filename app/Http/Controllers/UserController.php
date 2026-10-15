<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Lookup;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Spatie\Permission\Models\Role;

/**
 * Quản lý thành viên của tổ chức đang làm việc.
 *
 * Một tài khoản có thể thuộc nhiều tổ chức. Quản trị viên tổ chức chỉ được sửa THÔNG TIN TÀI KHOẢN (tên, email, mật khẩu...)
 * khi tài khoản chỉ thuộc riêng tổ chức của họ; với tài khoản dùng chung, họ chỉ quản lý vai trò, phòng ban và tư cách thành viên
 * trong tổ chức mình (chống chiếm tài khoản xuyên tổ chức). Quản trị nền tảng quản lý được mọi thứ.
 */
class UserController extends Controller
{
    private function orgId(): int
    {
        return (int) app(CurrentOrganization::class)->id();
    }

    /** Người thao tác có được sửa thông tin tài khoản (tên, email, mật khẩu, trạng thái toàn hệ thống) của $user không. */
    private function managesAccount(Request $request, User $user): bool
    {
        return $request->user()->isPlatformAdmin()
            || ! $user->organizations()->where('organizations.id', '!=', $this->orgId())->exists();
    }

    /** Tên các vai trò của tổ chức đang làm việc. */
    private function organizationRoleNames(): array
    {
        return Role::where('organization_id', $this->orgId())->orderBy('id')->pluck('name')->all();
    }

    /** Vai trò của tổ chức hiện tại mà người thao tác được phép gán (chỉ chủ sở hữu gán được vai trò chủ sở hữu). */
    private function assignableRoles(Request $request): array
    {
        $canGrantOwner = $request->user()->isPlatformAdmin() || $request->user()->isOrganizationOwner();

        return Role::where('organization_id', $this->orgId())->orderBy('id')->get()
            ->reject(fn ($r) => $r->name === PermissionCatalog::OWNER && ! $canGrantOwner)
            ->mapWithKeys(fn ($r) => [$r->name => __($r->name)])->all();
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $orgId = $this->orgId();
        $users = User::inOrganization(null, false)
            ->with(['department:id,name', 'roles:id,name', 'organizations' => fn ($q) => $q->whereKey($orgId)])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('role') && in_array($request->role, $this->organizationRoleNames(), true), fn ($q) => $q->role($request->role))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->department))
            ->when($request->status === 'locked', fn ($q) => $q->where(fn ($w) => $w->where('status', 'locked')
                ->orWhereHas('organizations', fn ($o) => $o->whereKey($orgId)->where('organization_user.status', 'suspended'))))
            ->when($request->status === 'active', fn ($q) => $q->where('status', 'active')
                ->whereHas('organizations', fn ($o) => $o->whereKey($orgId)->where('organization_user.status', 'active')))
            ->orderBy('name')->paginate($this->perPage($request))->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => collect($this->organizationRoleNames())->mapWithKeys(fn ($n) => [$n => __($n)])->all(), 'departments' => Lookup::departments()]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', User::class);

        return view('users.form', ['user' => new User(['status' => 'active']), 'roles' => $this->assignableRoles($request), 'departments' => Lookup::departments(), 'managesAccount' => true]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        $orgId = $this->orgId();

        // Email đã có tài khoản → thêm người đó vào tổ chức (không đụng tới mật khẩu / thông tin tài khoản của họ)
        $existing = $request->filled('email') ? User::whereRaw('LOWER(email) = ?', [mb_strtolower((string) $request->input('email'))])->first() : null;
        if ($existing) {
            $data = $request->validate([
                'role' => ['required', Rule::in(array_keys($this->assignableRoles($request)))],
                'department_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('departments')],
            ], [], $this->attributes());
            if ($existing->organizations()->whereKey($orgId)->exists()) {
                return back()->withInput()->withErrors(['email' => __('Người dùng này đã là thành viên của tổ chức.')]);
            }
            DB::transaction(function () use ($existing, $orgId, $data) {
                $existing->organizations()->attach($orgId, ['status' => 'active', 'joined_at' => now()]);
                $existing->unsetRelation('roles');
                $existing->syncRoles([$data['role']]);
            });
            ActivityLogger::log('updated', $existing, 'Thêm tài khoản có sẵn '.$existing->email.' vào tổ chức (vai trò: '.$data['role'].')');

            return redirect()->route('users.show', $existing)->with('success', __('Đã thêm :email vào tổ chức. Người này dùng tài khoản và mật khẩu hiện có.', ['email' => $existing->email]));
        }

        $data = $request->validate($this->rules($request) + [
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [], $this->attributes());

        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->except(['role', 'password_confirmation'])->all());   // tự là thành viên tổ chức hiện tại
            $user->syncRoles([$data['role']]);

            return $user;
        });

        return redirect()->route('users.show', $user)->with('success', __('Đã tạo tài khoản :email. Hãy gửi mật khẩu cho nhân viên qua kênh an toàn.', ['email' => $user->email]));
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);
        $user->load(['department', 'roles']);
        $logs = \App\Models\ActivityLog::where('user_id', $user->id)->latest('created_at')->limit(15)->get();
        $stats = [
            'customers' => \App\Models\Customer::where('assigned_to', $user->id)->count(),
            'leads' => \App\Models\Lead::where('assigned_to', $user->id)->count(),
            'opportunities' => \App\Models\Opportunity::where('assigned_to', $user->id)->open()->count(),
            'tasks' => \App\Models\Task::where('assigned_to', $user->id)->open()->count(),
        ];
        $managesAccount = $this->managesAccount($request, $user);
        $membershipStatus = $user->membershipStatus();

        return view('users.show', compact('user', 'logs', 'stats', 'managesAccount', 'membershipStatus'));
    }

    public function edit(Request $request, User $user)
    {
        $this->authorize('update', $user);

        return view('users.form', [
            'user' => $user, 'roles' => $this->assignableRoles($request), 'departments' => Lookup::departments(),
            'managesAccount' => $this->managesAccount($request, $user),
            'membershipStatus' => $user->membershipStatus(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $managesAccount = $this->managesAccount($request, $user);
        $rules = $managesAccount
            ? $this->rules($request, $user)
            : collect($this->rules($request, $user))->only(['department_id', 'status', 'role'])->all();   // tài khoản dùng chung
        $data = $request->validate($rules, [], $this->attributes());

        if ($user->id === $request->user()->id && $data['status'] === 'locked') {
            return back()->withInput()->with('error', __('Bạn không thể tự khóa tài khoản của mình.'));
        }
        if ($user->id === $request->user()->id && $user->isOrganizationOwner() && $data['role'] !== PermissionCatalog::OWNER) {
            return back()->withInput()->with('error', __('Bạn không thể tự bỏ vai trò chủ sở hữu tổ chức của mình.'));
        }

        DB::transaction(function () use ($request, $user, $data) {
            $account = collect($data)->except(['role', 'status'])->all();
            if ($request->user()->isPlatformAdmin()) {
                $account['status'] = $data['status'];   // khóa / mở toàn hệ thống
            } else {
                // Quản trị tổ chức: "khóa" = tạm ngưng tư cách thành viên trong tổ chức này
                $user->organizations()->updateExistingPivot($this->orgId(), ['status' => $data['status'] === 'locked' ? 'suspended' : 'active']);
            }
            $user->update($account);
            $user->syncRoles([$data['role']]);
        });
        ActivityLogger::log('updated', $user, 'Cập nhật tài khoản '.$user->email.' (vai trò: '.$data['role'].')');

        return redirect()->route('users.show', $user)->with('success', __('Đã cập nhật tài khoản.'));
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);
        abort_if($user->id === $request->user()->id, 422, __('Không thể xóa chính mình.'));
        $orgId = $this->orgId();

        // Còn thuộc tổ chức khác → chỉ gỡ khỏi tổ chức này, tài khoản vẫn giữ nguyên
        if ($user->organizations()->where('organizations.id', '!=', $orgId)->exists()) {
            DB::transaction(function () use ($user, $orgId) {
                $user->syncRoles([]);   // gỡ vai trò trong tổ chức hiện tại
                $user->organizations()->detach($orgId);
                if ((int) $user->current_organization_id === $orgId) {
                    $user->forceFill(['current_organization_id' => null])->saveQuietly();
                }
            });
            ActivityLogger::log('updated', $user, 'Gỡ '.$user->email.' khỏi tổ chức');

            return redirect()->route('users.index')->with('success', __('Đã gỡ :email khỏi tổ chức. Tài khoản vẫn được giữ ở các tổ chức khác.', ['email' => $user->email]));
        }

        $user->update(['status' => 'locked']);
        $user->delete(); // xóa mềm, dữ liệu liên quan được giữ nguyên
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return redirect()->route('users.index')->with('success', __('Đã xóa tài khoản :email.', ['email' => $user->email]));
    }

    /** Khóa / mở tư cách thành viên trong tổ chức hiện tại (không ảnh hưởng tổ chức khác). */
    public function toggleStatus(Request $request, User $user)
    {
        $this->authorize('update', $user);
        abort_if($user->id === $request->user()->id, 422, __('Bạn không thể tự khóa tài khoản của mình.'));

        $suspend = $user->membershipStatus() !== 'suspended';
        $user->organizations()->updateExistingPivot($this->orgId(), ['status' => $suspend ? 'suspended' : 'active']);
        ActivityLogger::log('updated', $user, ($suspend ? 'Tạm ngưng ' : 'Kích hoạt lại ').$user->email.' trong tổ chức');

        return back()->with('success', $suspend ? __('Đã tạm ngưng thành viên trong tổ chức. Người này không thể làm việc trong tổ chức cho tới khi được mở lại.') : __('Đã mở lại thành viên trong tổ chức.'));
    }

    /** Gửi liên kết đặt lại mật khẩu qua email (quy trình chuẩn, an toàn). */
    public function sendReset(Request $request, User $user)
    {
        $this->authorize('update', $user);
        abort_unless($this->managesAccount($request, $user), 403, __('Tài khoản này thuộc nhiều tổ chức; chỉ chủ tài khoản hoặc quản trị nền tảng được thay đổi mật khẩu.'));
        $status = Password::sendResetLink(['email' => $user->email]);
        ActivityLogger::log('updated', $user, 'Gửi liên kết đặt lại mật khẩu cho '.$user->email);

        $message = $status === Password::RESET_LINK_SENT
            ? (config('mail.default') === 'log' ? __('Đã tạo liên kết đặt lại. Máy chủ chưa cấu hình email nên liên kết được ghi vào storage/logs/laravel.log.') : __('Đã gửi email đặt lại mật khẩu.'))
            : __('Không gửi được: :reason', ['reason' => __($status)]);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', $message);
    }

    /** Quản trị viên đặt mật khẩu tạm thời (khi chưa có email). Mật khẩu không được ghi log. */
    public function setPassword(Request $request, User $user)
    {
        $this->authorize('update', $user);
        abort_unless($this->managesAccount($request, $user), 403, __('Tài khoản này thuộc nhiều tổ chức; chỉ chủ tài khoản hoặc quản trị nền tảng được thay đổi mật khẩu.'));
        $request->validate(['password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()]], [], ['password' => __('Mật khẩu mới')]);

        $user->forceFill(['password' => $request->password, 'remember_token' => null])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        ActivityLogger::log('updated', $user, 'Quản trị viên đặt lại mật khẩu cho '.$user->email);

        return back()->with('success', __('Đã đặt mật khẩu mới. Nhân viên nên đổi mật khẩu sau khi đăng nhập.'));
    }

    private function rules(Request $request, ?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._\-]+$/', Rule::unique('users', 'username')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'position' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('departments')],
            'status' => ['required', Rule::in(array_keys(User::STATUSES))],
            'role' => ['required', Rule::in(array_keys($this->assignableRoles($request)))],
        ];
    }

    private function attributes(): array
    {
        return ['name' => __('Họ tên'), 'role' => __('Vai trò'), 'department_id' => __('Phòng ban')];
    }
}
