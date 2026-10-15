<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/** Email xác minh địa chỉ email (liên kết có chữ ký, hết hạn sau 60 phút). */
class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $company = Setting::get('company_name', 'TOP CRM');

        return (new MailMessage)
            ->subject('['.$company.'] '.__('Xác minh địa chỉ email'))
            ->greeting(__('Xin chào :name,', ['name' => $notifiable->name]))
            ->line(__('Vui lòng bấm nút bên dưới để xác minh địa chỉ email của tài khoản.'))
            ->action(__('Xác minh email'), $this->verificationUrl($notifiable))
            ->line(__('Liên kết sẽ hết hạn sau :minutes phút.', ['minutes' => config('auth.verification.expire', 60)]))
            ->line(__('Nếu bạn không yêu cầu, hãy bỏ qua email này.'))
            ->salutation($company);
    }
}
