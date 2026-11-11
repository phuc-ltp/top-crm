<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email báo kết quả yêu cầu tham gia cho người đăng ký (chưa có tài khoản → gửi theo địa chỉ email). */
class JoinRequestDecisionNotification extends Notification
{
    public function __construct(public bool $approved, public string $name, public string $company, public ?string $role = null, public ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting(__('Xin chào :name,', ['name' => $this->name]));
        if ($this->approved) {
            return $mail->subject('['.$this->company.'] '.__('Tài khoản của bạn đã được duyệt'))
                ->line(__('Yêu cầu tham gia :company đã được duyệt với vai trò :role.', ['company' => $this->company, 'role' => __($this->role)]))
                ->line(__('Đăng nhập bằng email này và mật khẩu bạn đã đặt khi đăng ký.'))
                ->action(__('Đăng nhập'), route('login'))
                ->salutation($this->company);
        }

        $mail->subject('['.$this->company.'] '.__('Yêu cầu tham gia chưa được chấp nhận'))
            ->line(__('Yêu cầu tham gia :company của bạn chưa được chấp nhận.', ['company' => $this->company]));
        if ($this->reason) {
            $mail->line(__('Lý do: :reason', ['reason' => $this->reason]));
        }

        return $mail->line(__('Nếu cần, hãy liên hệ trực tiếp người quản lý của bạn.'))->salutation($this->company);
    }
}
