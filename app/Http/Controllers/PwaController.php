<?php

namespace App\Http\Controllers;

use App\Models\Setting;

/**
 * Cài CRM lên điện thoại / máy tính như một ứng dụng (PWA): manifest sinh theo cấu hình
 * (tên công ty, đường dẫn gốc đúng cả khi chạy dưới thư mục con như /orangecrm).
 */
class PwaController extends Controller
{
    public function manifest()
    {
        $name = Setting::get('company_name') ?: config('app.name');
        $base = rtrim(url('/'), '/').'/';

        return response()->json([
            'name' => $name,
            'short_name' => mb_strimwidth($name, 0, 12, ''),
            'description' => __('Phần mềm quản lý khách hàng, bán hàng, nhân sự và tài sản.'),
            'id' => $base,
            'start_url' => route('dashboard'),
            'scope' => $base,
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#FFF7EF',
            'theme_color' => '#9A4A1C',
            'lang' => app()->getLocale(),
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => __('Chấm công'), 'url' => route('attendance.index'), 'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']]],
                ['name' => __('Công việc'), 'url' => route('tasks.index'), 'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
