<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email chứa mã xác thực 6 số (đăng nhập hoặc đăng ký tài khoản). */
class EmailOtpNotification extends Notification
{
    public function __construct(public string $code, public string $purpose, public int $minutes, public ?string $name = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = Setting::get('company_name', 'TOP CRM');
        $what = match ($this->purpose) {
            'join', 'register' => __('đăng ký tài khoản'),
            'enable' => __('bật xác thực qua email'),
            default => __('đăng nhập'),
        };

        return (new MailMessage)
            ->subject('['.$company.'] '.__('Mã xác thực :code', ['code' => $this->code]))
            ->greeting($this->name ? __('Xin chào :name,', ['name' => $this->name]) : __('Xin chào,'))
            ->line(__('Mã xác thực để :what của bạn là:', ['what' => $what]))
            ->line('**'.trim(chunk_split($this->code, 3, ' ')).'**')
            ->line(__('Mã có hiệu lực trong :m phút. Không chia sẻ mã này cho bất kỳ ai — nhân viên :c không bao giờ hỏi mã của bạn.', ['m' => $this->minutes, 'c' => $company]))
            ->line(__('Nếu bạn không yêu cầu mã này, hãy bỏ qua email.'))
            ->salutation($company);
    }
}
