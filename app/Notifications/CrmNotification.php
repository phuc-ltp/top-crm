<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Thông báo nội bộ lưu trong bảng notifications (kênh database).
 * Không gửi email — tránh gửi thư hàng loạt khi chưa cấu hình dịch vụ email.
 */
class CrmNotification extends Notification
{
    use Queueable;

    /** Loại thông báo → khóa cài đặt bật/tắt. */
    private const SETTING_KEYS = [
        'assigned' => 'notify_lead_assigned',
        'lead_assigned' => 'notify_lead_assigned',
        'task_due' => 'notify_task_due',
        'task_overdue' => 'notify_task_due',
        'task_assigned' => 'notify_task_due',
        'deal_stage' => 'notify_deal_stage',
        'ticket_new' => 'notify_ticket_new',
        'low_stock' => 'notify_low_stock',
    ];

    public function __construct(
        public string $kind,
        public string $message,
        public ?string $url = null,
        public string $icon = 'bi-bell',
    ) {}

    public static function send(?User $user, string $kind, string $message, ?string $url = null, string $icon = 'bi-bell'): void
    {
        if (! $user || ! $user->isActive()) {
            return;
        }
        $settingKey = self::SETTING_KEYS[$kind] ?? null;
        if ($settingKey && Setting::get($settingKey, '1') !== '1') {
            return;
        }

        // Lưu đường dẫn tương đối để hoạt động với mọi địa chỉ truy cập (localhost/orangecrm, 127.0.0.1:8000)
        if ($url) {
            $path = parse_url($url, PHP_URL_PATH) ?? '/';
            $base = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base)) ?: '/';
            }
            $query = parse_url($url, PHP_URL_QUERY);
            $url = $path.($query ? '?'.$query : '');
        }

        try {
            $user->notify(new self($kind, $message, $url, $icon));
        } catch (\Throwable $e) {
            Log::warning('Không gửi được thông báo: '.$e->getMessage());
        }
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }
}
