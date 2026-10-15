<?php

namespace App\Http\Controllers;

use App\Models\Setting;

/**
 * Trang công khai: điều khoản, chính sách bảo mật, hỗ trợ. Nội dung sửa được trong Cài đặt;
 * nếu chưa sửa thì dùng nội dung mẫu theo ngôn ngữ (pages/default hoặc pages/default/en).
 */
class PageController extends Controller
{
    private const PAGES = [
        'terms' => ['Điều khoản sử dụng', 'page_terms'],
        'privacy' => ['Chính sách bảo mật', 'page_privacy'],
        'support' => ['Hỗ trợ', 'page_support'],
    ];

    public function show(string $slug)
    {
        abort_unless(isset(self::PAGES[$slug]), 404);
        [$title, $key] = self::PAGES[$slug];

        $default = view()->exists('pages.default.'.app()->getLocale().'.'.$slug)
            ? 'pages.default.'.app()->getLocale().'.'.$slug
            : 'pages.default.'.$slug;

        return view('pages.show', ['title' => __($title), 'default' => $default, 'custom' => trim((string) Setting::get($key))]);
    }
}
