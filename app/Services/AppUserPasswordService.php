<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Merchant;
use App\Notifications\AppUserPasswordResetByAdminNotification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AppUserPasswordService
{
    public static function generateFromEmail(string $email, string $fallbackPrefix = 'user'): string
    {
        $prefix = Str::before($email, '@');
        $cleanPrefix = preg_replace('/[^a-zA-Z0-9]/', '', $prefix) ?: $fallbackPrefix;
        $seed = strtoupper(substr(md5($email), 0, 4));

        return $cleanPrefix.'@'.$seed;
    }

    /**
     * @return array{password: string, email_sent: bool}
     */
    public static function resetByAdmin(Member|Merchant $user, string $type): array
    {
        $email = $type === 'merchant'
            ? $user->company_email
            : $user->email;

        $fallback = $type === 'merchant' ? 'merchant' : 'member';
        $temporaryPassword = self::generateFromEmail($email, $fallback);

        $user->password = $temporaryPassword;
        $user->temp_password_issued_at = now();
        $user->save();

        $broker = $type === 'merchant' ? 'merchants' : 'members';
        $token = Password::broker($broker)->createToken($user);

        $emailSent = true;
        try {
            $user->notify(new AppUserPasswordResetByAdminNotification($token, $type, $temporaryPassword));
        } catch (\Throwable $e) {
            report($e);
            $emailSent = false;
        }

        return [
            'password' => $temporaryPassword,
            'email_sent' => $emailSent,
        ];
    }
}
