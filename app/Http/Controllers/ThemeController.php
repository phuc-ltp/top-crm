<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Lưu lựa chọn giao diện sáng/tối vào hồ sơ người dùng để dùng chung trên mọi thiết bị. */
class ThemeController extends Controller
{
    public const THEMES = ['light', 'dark', 'system'];

    public function update(Request $request)
    {
        $data = $request->validate(['theme' => ['required', 'string', Rule::in(self::THEMES)]]);
        $theme = $data['theme'] === 'system' ? null : $data['theme'];

        $request->user()->forceFill(['theme' => $theme])->saveQuietly();   // không ghi nhật ký cho thao tác cá nhân này

        return $request->expectsJson()
            ? response()->json(['theme' => $theme ?? 'system'])
            : back();
    }
}
