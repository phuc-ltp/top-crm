<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\User;
use App\Support\NotificationCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Thông báo nội bộ: luôn lưu trong ứng dụng (chuông), gửi thêm email khi
 *  - tổ chức đã cấu hình máy chủ thư thật (NotificationCatalog::mailEnabled), và
 *  - người nhận tự bật email cho loại đó (Hồ sơ › Thông báo).
 * Người dùng có thể tắt từng loại (trừ thông báo bảo mật); tổ chức có thể tắt một số loại cho cả công ty (Cài đặt).
 */
class CrmNotification extends Notification
{
    use Queueable;

    /** Loại thông báo → khóa cài đặt bật/tắt của tổ chức. */
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

    /** @var list<string> */
    public array $channels = ['database'];

    public function __construct(
        public string $kind,
        public string $message,
        public ?string $url = null,
        public string $icon = 'bi-bell',
    ) {}

    /** Người dùng có nhận loại này qua kênh $channel (app | mail) không. */
    public static function wants(User $user, string $kind, string $channel): bool
    {
        if ($channel === 'app' && NotificationCatalog::mandatory($kind)) {
            return true;
        }
        $pref = ($user->notification_preferences ?? [])[$kind][$channel] ?? null;

        return $pref === null ? $channel === 'app' : (bool) $pref;   // mặc định: có trong ứng dụng, không email
    }

    public static function send(?User $user, string $kind, string $message, ?string $url = null, string $icon = 'bi-bell'): void
    {
        if (! $user || ! $user->isActive()) {
            return;
        }
        $settingKey = self::SETTING_KEYS[$kind] ?? null;
        if ($settingKey && Setting::get($settingKey, '1') !== '1') {
            return;
        }
        $channels = array_values(array_filter([
            self::wants($user, $kind, 'app') ? 'database' : null,
            NotificationCatalog::mailEnabled() && $user->email && self::wants($user, $kind, 'mail') ? 'mail' : null,
        ]));
        if ($channels === []) {
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
            $notification = new self($kind, $message, $url, $icon);
            $notification->channels = $channels;
            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Không gửi được thông báo: '.$e->getMessage());
        }
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'group' => NotificationCatalog::group($this->kind),
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$group] = NotificationCatalog::groupMeta($this->kind);
        $mail = (new MailMessage)
            ->subject(__('[:app] :group', ['app' => Setting::get('company_name', config('app.name')), 'group' => __($group)]))
            ->greeting(__('Xin chào :name,', ['name' => $notifiable->name]))
            ->line($this->message);
        if ($this->url) {
            $mail->action(__('Xem chi tiết'), url($this->url));
        }

        return $mail->line(__('Bạn có thể tắt email cho loại thông báo này ở Hồ sơ › Thông báo.'));
    }
}
