<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppUserWelcomeNotification extends Notification
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
        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? $notifiable->getEmailForPasswordReset()
            : $notifiable->email;

        $label = $this->type === 'merchant' ? 'Merchant' : 'Member';

        $url = route('app.password.reset', [
            'token' => $this->token,
            'email' => $email,
            'type' => $this->type,
        ]);

        return (new MailMessage)
            ->subject('Welcome to PKPMS — Set Your Password')
            ->view('emails.account-created.app-user', [
                'label' => $label,
                'url' => $url,
                'expiresInMinutes' => 60,
            ]);
    }
}
