<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /** Vai trò mà người thao tác được phép gán (chỉ Super Admin gán được Super Admin). */
    private function assignableRoles(Request $request): array
    {
        return Role::orderBy('id')->get()
            ->reject(fn ($r) => $r->name === 'Super Admin' && ! $request->user()->isSuperAdmin())
            ->pluck('name', 'name')->all();
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $users = User::with(['department:id,name', 'roles:id,name'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->role($request->role))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->department))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('name')->paginate($this->perPage($request))->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => Role::orderBy('id')->pluck('name', 'name')->all(), 'departments' => Lookup::departments()]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', User::class);

        return view('users.form', ['user' => new User(['status' => 'active']), 'roles' => $this->assignableRoles($request), 'departments' => Lookup::departments()]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        $data = $request->validate($this->rules($request) + [
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [], $this->attributes());

        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->except(['role', 'password_confirmation'])->all());
            $user->syncRoles([$data['role']]);

            return $user;
        });

        return redirect()->route('users.show', $user)->with('success', 'Đã tạo tài khoản '.$user->email.'. Hãy gửi mật khẩu cho nhân viên qua kênh an toàn.');
    }

    public function show(User $user)
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

        return view('users.show', compact('user', 'logs', 'stats'));
    }

    public function edit(Request $request, User $user)
    {
        $this->authorize('update', $user);

        return view('users.form', ['user' => $user, 'roles' => $this->assignableRoles($request), 'departments' => Lookup::departments()]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $data = $request->validate($this->rules($request, $user), [], $this->attributes());

        if ($user->id === $request->user()->id && $data['status'] === 'locked') {
            return back()->withInput()->with('error', 'Bạn không thể tự khóa tài khoản của mình.');
        }
        if ($user->id === $request->user()->id && $user->isSuperAdmin() && $data['role'] !== 'Super Admin') {
            return back()->withInput()->with('error', 'Bạn không thể tự bỏ vai trò Super Admin của mình.');
        }

        DB::transaction(function () use ($user, $data) {
            $user->update(collect($data)->except('role')->all());
            $user->syncRoles([$data['role']]);
        });
        ActivityLogger::log('updated', $user, 'Cập nhật tài khoản '.$user->email.' (vai trò: '.$data['role'].')');

        return redirect()->route('users.show', $user)->with('success', 'Đã cập nhật tài khoản.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);
        abort_if($user->id === $request->user()->id, 422, 'Không thể xóa chính mình.');
        $user->update(['status' => 'locked']);
        $user->delete(); // xóa mềm, dữ liệu liên quan được giữ nguyên
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return redirect()->route('users.index')->with('success', 'Đã xóa tài khoản '.$user->email.'.');
    }

    public function toggleStatus(Request $request, User $user)
    {
        $this->authorize('update', $user);
        abort_if($user->id === $request->user()->id, 422, 'Bạn không thể tự khóa tài khoản của mình.');

        $user->update(['status' => $user->status === 'active' ? 'locked' : 'active']);
        if ($user->status === 'locked') {
            DB::table('sessions')->where('user_id', $user->id)->delete(); // đăng xuất ngay
        }

        return back()->with('success', $user->status === 'active' ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản và đăng xuất mọi phiên.');
    }

    /** Gửi liên kết đặt lại mật khẩu qua email (quy trình chuẩn, an toàn). */
    public function sendReset(User $user)
    {
        $this->authorize('update', $user);
        $status = Password::sendResetLink(['email' => $user->email]);
        ActivityLogger::log('updated', $user, 'Gửi liên kết đặt lại mật khẩu cho '.$user->email);

        $message = $status === Password::RESET_LINK_SENT
            ? (config('mail.default') === 'log' ? 'Đã tạo liên kết đặt lại. Máy chủ chưa cấu hình email nên liên kết được ghi vào storage/logs/laravel.log.' : 'Đã gửi email đặt lại mật khẩu.')
            : 'Không gửi được: '.__($status);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', $message);
    }

    /** Quản trị viên đặt mật khẩu tạm thời (khi chưa có email). Mật khẩu không được ghi log. */
    public function setPassword(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $request->validate(['password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()]], [], ['password' => 'Mật khẩu mới']);

        $user->forceFill(['password' => $request->password, 'remember_token' => null])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        ActivityLogger::log('updated', $user, 'Quản trị viên đặt lại mật khẩu cho '.$user->email);

        return back()->with('success', 'Đã đặt mật khẩu mới. Nhân viên nên đổi mật khẩu sau khi đăng nhập.');
    }

    private function rules(Request $request, ?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._\-]+$/', Rule::unique('users', 'username')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'position' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'status' => ['required', Rule::in(array_keys(User::STATUSES))],
            'role' => ['required', Rule::in(array_keys($this->assignableRoles($request)))],
        ];
    }

    private function attributes(): array
    {
        return ['name' => 'Họ tên', 'role' => 'Vai trò', 'department_id' => 'Phòng ban'];
    }
}
