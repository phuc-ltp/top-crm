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
            ->subject('[OrangeCRM] Đặt lại mật khẩu')
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line('Bạn nhận được email này vì hệ thống nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.')
            ->action('Đặt lại mật khẩu', $url)
            ->line("Liên kết sẽ hết hạn sau {$minutes} phút.")
            ->line('Nếu bạn không yêu cầu, hãy bỏ qua email này.')
            ->salutation('OrangeCRM Enterprise');
    }
}
