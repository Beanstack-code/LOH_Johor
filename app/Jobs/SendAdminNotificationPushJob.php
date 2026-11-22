<?php

namespace App\Jobs;

use App\Models\AdminNotification;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\PushDeviceToken;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAdminNotificationPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $notificationId) {}

    public function handle(FcmService $fcmService): void
    {
        $notification = AdminNotification::find($this->notificationId);
        if (! $notification) {
            return;
        }

        $tokenQuery = PushDeviceToken::query()->active();
        $audience = (string) $notification->target_audience;

        if ($audience === 'member') {
            $tokenQuery->where('authenticatable_type', Member::class);
        } elseif ($audience === 'merchant') {
            $tokenQuery->where('authenticatable_type', Merchant::class);
        } else {
            $tokenQuery->whereIn('authenticatable_type', [Member::class, Merchant::class]);
        }

        $tokens = $tokenQuery->pluck('fcm_token')->all();

        $response = $fcmService->sendToTokens(
            $tokens,
            [
                'title' => (string) $notification->title,
                'body' => (string) $notification->message,
            ],
            [
                'notification_id' => (string) $notification->id,
                'type' => (string) $notification->notification_type,
                'deep_link' => (string) ($notification->deep_link ?? ''),
                'audience' => $audience,
            ]
        );

        if (! empty($response['invalid_tokens'])) {
            PushDeviceToken::query()
                ->whereIn('fcm_token', $response['invalid_tokens'])
                ->update(['is_active' => false]);
        }

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
            'push_total_targets' => $response['total'],
            'push_sent_count' => $response['sent'],
            'push_failed_count' => $response['failed'],
            'last_push_attempt_at' => now(),
            'last_push_error' => empty($response['errors']) ? null : implode("\n", array_slice($response['errors'], 0, 5)),
        ]);
    }
}

