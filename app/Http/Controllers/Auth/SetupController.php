<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * Khởi tạo tài khoản Super Admin lần đầu. Trang này tự khóa ngay khi đã có Super Admin.
 */
class SetupController extends Controller
{
    public static function needsSetup(): bool
    {
        try {
            return ! User::role('Super Admin')->exists();
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
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ], ['username.regex' => 'Tên đăng nhập chỉ gồm chữ không dấu, số, dấu chấm, gạch ngang, gạch dưới.'],
            ['name' => 'Họ tên', 'email' => 'Email', 'username' => 'Tên đăng nhập', 'password' => 'Mật khẩu']);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'], 'email' => mb_strtolower($data['email']),
                'username' => ! empty($data['username']) ? mb_strtolower($data['username']) : null, 'password' => $data['password'],
                'status' => 'active', 'position' => 'Quản trị hệ thống',
                'department_id' => Department::where('code', 'BGD')->value('id'),
            ]);
            $user->assignRole('Super Admin');

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        ActivityLogger::log('created', $user, 'Khởi tạo tài khoản Super Admin');

        return redirect()->route('dashboard')->with('success', 'Đã tạo tài khoản quản trị. Chào mừng bạn đến với OrangeCRM!');
    }
}
