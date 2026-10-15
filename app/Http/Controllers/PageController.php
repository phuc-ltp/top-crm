<?php

namespace App\Http\Controllers;

use App\Models\Setting;

/** Trang công khai: điều khoản, chính sách bảo mật, hỗ trợ. Nội dung sửa được trong Cài đặt. */
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

        return view('pages.show', ['title' => $title, 'slug' => $slug, 'custom' => trim((string) Setting::get($key))]);
    }
}
