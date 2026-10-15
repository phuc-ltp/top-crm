<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], false));
        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('['.\App\Models\Setting::get('company_name', 'TOP CRM').'] '.__('Đặt lại mật khẩu'))
            ->greeting(__('Xin chào :name,', ['name' => $notifiable->name]))
            ->line(__('Bạn nhận được email này vì hệ thống nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.'))
            ->action(__('Đặt lại mật khẩu'), $url)
            ->line(__('Liên kết sẽ hết hạn sau :minutes phút.', ['minutes' => $minutes]))
            ->line(__('Nếu bạn không yêu cầu, hãy bỏ qua email này.'))
            ->salutation(\App\Models\Setting::get('company_name', 'TOP CRM'));
    }
}
