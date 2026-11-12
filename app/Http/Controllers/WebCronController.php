<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * GET /cron/{token} — chạy bộ lập lịch (php artisan schedule:run) khi hosting KHÔNG có cron / Terminal.
 * Một dịch vụ cron bên ngoài (VD cron-job.org, miễn phí) gọi đường dẫn này MỖI PHÚT.
 * Token ngẫu nhiên 48 ký tự (Hệ thống → Tác vụ & sao lưu), so sánh hằng thời gian; không trả thông tin nội bộ.
 */
class WebCronController extends Controller
{
    public const TOKEN_KEY = 'cron_token';

    public const LAST_RUN_KEY = 'cron_last_run';

    public function __invoke(string $token): Response
    {
        $expected = (string) Setting::get(self::TOKEN_KEY, '');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(404);
        }
        $lock = Cache::lock('web-cron', 300);
        if (! $lock->get()) {
            return response("busy\n", 200)->header('Content-Type', 'text/plain');   // lần trước chưa chạy xong
        }
        try {
            ignore_user_abort(true);
            @set_time_limit(300);
            Artisan::call('schedule:run');
            Setting::put([self::LAST_RUN_KEY => (string) now()->getTimestamp()], 'system');
        } catch (\Throwable $e) {
            Log::error('Web cron lỗi: '.$e->getMessage());

            return response("error\n", 500)->header('Content-Type', 'text/plain');
        } finally {
            $lock->release();
        }

        return response("ok\n", 200)->header('Content-Type', 'text/plain')->header('X-Robots-Tag', 'noindex');
    }
}
