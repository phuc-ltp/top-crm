<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Email cảnh báo: tài khoản vừa đăng nhập từ một trình duyệt/thiết bị chưa từng dùng. */
class NewDeviceLoginNotification extends Notification
{
    public function __construct(public string $device, public string $ip, public Carbon $at) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = Setting::get('company_name', 'TOP CRM');

        return (new MailMessage)
            ->subject('['.$company.'] '.__('Cảnh báo đăng nhập từ thiết bị mới'))
            ->greeting(__('Xin chào :name,', ['name' => $notifiable->name]))
            ->line(__('Tài khoản của bạn vừa được đăng nhập từ một thiết bị chưa từng sử dụng:'))
            ->line(__('Thiết bị').': '.$this->device)
            ->line(__('Địa chỉ IP').': '.$this->ip)
            ->line(__('Thời gian').': '.$this->at->timezone(config('app.timezone'))->format('d/m/Y H:i'))
            ->action(__('Kiểm tra bảo mật tài khoản'), route('profile.security'))
            ->line(__('Nếu không phải bạn, hãy đổi mật khẩu ngay, đăng xuất các thiết bị khác và bật xác thực 2 lớp.'))
            ->salutation($company);
    }
}
