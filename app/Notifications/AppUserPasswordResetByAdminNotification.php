<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppUserPasswordResetByAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $type,
        public string $temporaryPassword,
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
            ->subject('Your PKPMS Password Has Been Reset')
            ->view('emails.password-reset.admin-reset', [
                'label' => $label,
                'url' => $url,
                'temporaryPassword' => $this->temporaryPassword,
                'expiresInMinutes' => 60,
            ]);
    }
}
