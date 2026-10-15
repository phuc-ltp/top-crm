<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivityLogger
{
    /** Tắt khi chạy seeder để không sinh nhật ký giả. */
    public static bool $enabled = true;

    private const SENSITIVE = ['password', 'password_confirmation', 'remember_token', 'token', 'api_key', 'secret'];

    public static function log(string $action, ?Model $subject, string $description, array $properties = []): void
    {
        if (! static::$enabled) {
            return;
        }

        try {
            $request = app()->runningInConsole() ? null : request();

            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'description' => mb_substr($description, 0, 500),
                'properties' => $properties ? static::scrub($properties) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Nhật ký không được làm hỏng nghiệp vụ chính
            Log::warning('Không ghi được activity log: '.$e->getMessage());
        }
    }

    private static function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE, true)) {
                $data[$key] = '[ẩn]';
            } elseif (is_array($value)) {
                $data[$key] = static::scrub($value);
            }
        }

        return $data;
    }
}
