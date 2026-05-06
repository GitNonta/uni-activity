<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification สำหรับส่งอีเมล reset password ให้ Staff
 */
class StaffResetPasswordNotification extends Notification
{
    /** Token สำหรับ reset password */
    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    /** ช่องทางการส่ง notification */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /** สร้างข้อความอีเมล */
    public function toMail($notifiable): MailMessage
    {
        $url = route('admin.password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject('รีเซ็ตรหัสผ่าน - ระบบจัดการกิจกรรมนักศึกษา')
            ->view('emails.staff-password-reset', [
                'token' => $this->token,
                'email' => $notifiable->email,
                'url' => $url,
            ]);
    }
}
