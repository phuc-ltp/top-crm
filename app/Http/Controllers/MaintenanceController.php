<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Support\DatabaseBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Hệ thống → Tác vụ & sao lưu (chỉ quản trị nền tảng): đường dẫn cron, lần chạy gần nhất, sao lưu database. */
class MaintenanceController extends Controller
{
    public function edit()
    {
        $this->authorize('platform');
        if (! Setting::get(WebCronController::TOKEN_KEY)) {
            Setting::put([WebCronController::TOKEN_KEY => Str::random(48)], 'system');
        }
        $last = (int) Setting::get(WebCronController::LAST_RUN_KEY, '0');

        return view('settings.maintenance', [
            'cronUrl' => route('cron.run', Setting::get(WebCronController::TOKEN_KEY)),
            'lastRun' => $last ? \Illuminate\Support\Carbon::createFromTimestamp($last) : null,
            'cronHealthy' => $last && now()->getTimestamp() - $last <= 600,
            'backups' => DatabaseBackup::list(),
        ]);
    }

    public function backup()
    {
        $this->authorize('platform');
        @set_time_limit(300);
        $r = DatabaseBackup::run();
        ActivityLogger::log('updated', null, 'Sao lưu database thủ công: '.$r['file']);

        return back()->with('success', __('Đã sao lưu :tables bảng, :rows dòng (:size KB).', ['tables' => $r['tables'], 'rows' => number_format($r['rows'], 0, ',', '.'), 'size' => round($r['bytes'] / 1024)]));
    }

    public function download(string $file)
    {
        $this->authorize('platform');
        $path = DatabaseBackup::path($file);
        abort_unless($path, 404);
        ActivityLogger::log('exported', null, 'Tải bản sao lưu database '.$file);

        return response()->download($path, $file, ['Content-Type' => 'application/gzip']);
    }

    public function regenerate()
    {
        $this->authorize('platform');
        Setting::put([WebCronController::TOKEN_KEY => Str::random(48)], 'system');
        ActivityLogger::log('updated', null, 'Đổi đường dẫn cron');

        return back()->with('warning', __('Đã tạo đường dẫn cron mới — hãy cập nhật lại trên dịch vụ cron, đường dẫn cũ không còn dùng được.'));
    }
}
