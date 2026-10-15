<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Trạng thái "hệ thống đã khởi tạo" — cờ vĩnh viễn trong bảng settings.
 *
 * Một khi cờ đã ghi, trang /setup đóng hẳn, kể cả khi tài khoản Super Admin sau đó bị xóa
 * hoặc mất vai trò (tránh người lạ chiếm quyền). Khôi phục quản trị chỉ bằng lệnh
 * `php artisan crm:create-admin` trên máy chủ.
 */
class AppInitialization
{
    public const KEY = 'app_initialized_at';

    /** Đã khởi tạo chưa? Đọc thẳng từ CSDL (không qua cache) để không bị dữ liệu cũ. */
    public static function done(): bool
    {
        return Setting::query()->where('key', self::KEY)->exists()
            || User::where('is_platform_admin', true)->exists();   // cài đặt cũ có quản trị nhưng chưa có cờ
    }

    /**
     * Giành quyền khởi tạo một cách nguyên tử: dựa vào khóa UNIQUE của settings.key,
     * nên dù nhiều yêu cầu chạy song song thì chỉ đúng một yêu cầu trả về true.
     * Gọi bên trong transaction để cờ bị hoàn tác nếu bước tạo tài khoản thất bại.
     */
    public static function claim(): bool
    {
        try {
            Setting::query()->insert([
                'key' => self::KEY, 'value' => now()->toIso8601String(), 'group' => 'system',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }
        Setting::flushCache();

        return true;
    }

    /** Ghi cờ nếu chưa có (dùng cho lệnh tạo admin bằng dòng lệnh). */
    public static function mark(): void
    {
        if (! Setting::query()->where('key', self::KEY)->exists()) {
            self::claim();
        }
    }
}
