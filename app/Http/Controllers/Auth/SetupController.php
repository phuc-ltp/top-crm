<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\AppInitialization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * Khởi tạo tài khoản Super Admin lần đầu. Trang tự khóa vĩnh viễn sau khi khởi tạo
 * (xem AppInitialization); an toàn khi nhiều yêu cầu gửi cùng lúc.
 */
class SetupController extends Controller
{
    public static function needsSetup(): bool
    {
        try {
            return ! AppInitialization::done();
        } catch (\Throwable) {
            return false;
        }
    }

    public function show()
    {
        abort_unless(self::needsSetup(), 404);

        return view('auth.setup');
    }

    public function store(Request $request)
    {
        abort_unless(self::needsSetup(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._\-]+$/', 'unique:users,username'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], ['username.regex' => __('Tên đăng nhập chỉ gồm chữ không dấu, số, dấu chấm, gạch ngang, gạch dưới.')],
            ['name' => __('Họ tên'), 'email' => 'Email', 'username' => __('Tên đăng nhập'), 'password' => __('Mật khẩu')]);

        // Lớp 1: khóa nguyên tử — chỉ một yêu cầu được xử lý tại một thời điểm.
        $lock = Cache::lock('crm:setup', 30);
        if (! $lock->get()) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['name' => __('Đang có một yêu cầu khởi tạo khác được xử lý. Vui lòng tải lại trang sau vài giây.')]);
        }

        try {
            // Lớp 2: giành cờ khởi tạo bằng khóa UNIQUE trong CSDL, cùng transaction với việc tạo tài khoản.
            $user = DB::transaction(function () use ($data) {
                if (User::where('is_platform_admin', true)->exists() || ! AppInitialization::claim()) {
                    return null;
                }

                return $this->createOwner($data);
            });
        } finally {
            $lock->release();
        }
        abort_unless($user, 404);

        Auth::login($user);
        $request->session()->regenerate();
        ActivityLogger::log('created', $user, 'Khởi tạo tài khoản Super Admin');

        return redirect()->route('dashboard')->with('success', __('Đã tạo tài khoản quản trị. Chào mừng bạn đến với TOP CRM!'));
    }

    private function createOwner(array $data): User
    {
        $user = User::create([
            'name' => $data['name'], 'email' => mb_strtolower($data['email']),
            'username' => ! empty($data['username']) ? mb_strtolower($data['username']) : null, 'password' => $data['password'],
            'status' => 'active', 'position' => 'Quản trị hệ thống',
            'department_id' => Department::where('code', 'BGD')->value('id'),
        ]);
        // Người khởi tạo = quản trị nền tảng + chủ sở hữu tổ chức mặc định
        $user->forceFill(['is_platform_admin' => true])->save();
        app(\App\Support\Tenancy\RoleProvisioner::class)->provision(\App\Models\Organization::DEFAULT_ID);
        $user->assignRole(\App\Support\PermissionCatalog::OWNER);
        // Người khởi tạo hệ thống là chủ sở hữu tổ chức mặc định
        \App\Models\Organization::whereKey(\App\Models\Organization::DEFAULT_ID)->whereNull('owner_id')->update(['owner_id' => $user->id]);

        return $user;
    }
}
