<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppUserResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $type
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset();
        $label = $this->type === 'merchant' ? 'Merchant' : 'Member';

        $url = route('app.password.reset', [
            'token' => $this->token,
            'email' => $email,
            'type' => $this->type,
        ]);

        return (new MailMessage)
            ->subject('App Password Reset Request')
            ->view('emails.password-reset.app-user', [
                'label' => $label,
                'url' => $url,
                'expiresInMinutes' => 60,
            ]);
    }
}
