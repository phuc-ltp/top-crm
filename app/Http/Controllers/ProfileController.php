<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()->load('roles')]);
    }

    public function update(Request $request, UploadService $uploads)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'position' => ['nullable', 'string', 'max:100'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_avatar' => ['nullable', 'boolean'],
        ], [], ['name' => __('Họ tên'), 'phone' => __('Số điện thoại'), 'position' => __('Chức vụ'), 'avatar' => __('Ảnh đại diện')]);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $uploads->replacePublicImage($request->file('avatar'), 'avatars', $user->avatar);
        } elseif ($request->boolean('remove_avatar')) {
            $uploads->deletePublic($user->avatar);
            $data['avatar'] = null;
        } else {
            unset($data['avatar']);
        }
        unset($data['remove_avatar']);

        $user->update($data);

        return back()->with('success', __('Đã cập nhật hồ sơ.'));
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ], [], ['current_password' => __('Mật khẩu hiện tại'), 'password' => __('Mật khẩu mới')]);

        $request->user()->update(['password' => $request->password]);
        // Đăng xuất các thiết bị khác sau khi đổi mật khẩu
        Auth::logoutOtherDevices($request->password);
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        ActivityLogger::log('updated', $request->user(), 'Đổi mật khẩu');

        return back()->with('success', __('Đổi mật khẩu thành công. Các phiên đăng nhập khác đã bị đăng xuất.'));
    }

    public function sessions(Request $request)
    {
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get()
            ->map(fn ($s) => (object) [
                'id' => $s->id,
                'ip' => $s->ip_address,
                'agent' => \App\Support\Security\UserAgent::describe($s->user_agent),
                'last_active' => \Illuminate\Support\Carbon::createFromTimestamp($s->last_activity)->timezone(crm_timezone()),
                'current' => $s->id === $request->session()->getId(),
            ]);

        return view('profile.sessions', compact('sessions'));
    }

    public function destroyOtherSessions(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']], [], ['password' => __('Mật khẩu')]);

        Auth::logoutOtherDevices($request->password);
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();

        return back()->with('success', __('Đã đăng xuất khỏi tất cả thiết bị khác.'));
    }
}
