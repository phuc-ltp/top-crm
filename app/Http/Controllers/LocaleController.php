<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Đổi ngôn ngữ giao diện: lưu vào phiên + cookie (khách) và hồ sơ người dùng (đã đăng nhập). */
class LocaleController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate(['locale' => ['required', 'string', Rule::in(SetLocale::supported())]]);
        $locale = $data['locale'];

        $request->session()->put('locale', $locale);
        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->saveQuietly();   // không ghi nhật ký cho thao tác cá nhân này
        }

        return back()->withCookie(cookie()->forever(SetLocale::COOKIE, $locale, sameSite: 'lax'));
    }
}
