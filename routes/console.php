<?php

use App\Jobs\SendAdminNotificationPushJob;
use App\Models\AdminNotification;
use App\Models\Announcement;
use App\Models\FundraisingCampaign;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Models\RedemptionItem;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pkpms:sync-lifecycle', function () {
    $now = now();
    $today = $now->toDateString();

    $expiredRewards = RedemptionItem::query()
        ->where('status', 'active')
        ->whereDate('expiry_date', '<', $today)
        ->update(['status' => 'inactive']);

    $expiredAnnouncements = Announcement::query()
        ->where('status', 'published')
        ->whereNotNull('ends_at')
        ->where('ends_at', '<=', $now)
        ->update(['status' => 'draft']);

    $expiredFundraisingCampaigns = FundraisingCampaign::query()
        ->where('status', 'active')
        ->whereNotNull('ends_at')
        ->where('ends_at', '<=', $now)
        ->update(['status' => 'ended']);

    $memberStateUpdates = 0;
    Member::query()
        ->select('id', 'status', 'subscription_state')
        ->chunkById(200, function ($members) use (&$memberStateUpdates): void {
            /** @var \App\Models\Member $member */
            foreach ($members as $member) {
                if ($member->refreshSubscriptionState()) {
                    $memberStateUpdates++;
                }
            }
        });

    $merchantStateUpdates = 0;
    Merchant::query()
        ->select('id', 'status', 'subscription_state')
        ->chunkById(200, function ($merchants) use (&$merchantStateUpdates): void {
            /** @var \App\Models\Merchant $merchant */
            foreach ($merchants as $merchant) {
                if ($merchant->refreshSubscriptionState()) {
                    $merchantStateUpdates++;
                }
            }
        });

    $this->info("Lifecycle sync complete | rewards_deactivated={$expiredRewards} announcements_drafted={$expiredAnnouncements} fundraising_ended={$expiredFundraisingCampaigns} member_states_updated={$memberStateUpdates} merchant_states_updated={$merchantStateUpdates}");
})->purpose('Deactivate expired rewards/announcements/campaigns and sync account subscription states.');

Artisan::command('pkpms:dispatch-scheduled-notifications', function () {
    $now = now();

    $dueNotifications = AdminNotification::query()
        ->where('status', 'scheduled')
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', $now)
        ->get(['id']);

    $dispatched = 0;
    foreach ($dueNotifications as $notification) {
        AdminNotification::query()
            ->whereKey((int) $notification->id)
            ->update(['status' => 'processing']);

        SendAdminNotificationPushJob::dispatch((int) $notification->id);
        $dispatched++;
    }

    $this->info("Scheduled notification dispatch complete | dispatched={$dispatched}");
})->purpose('Dispatch due scheduled notifications to Firebase queue.');

Artisan::command('pkpms:cleanup-stale-payments', function () {
    // TTL after which an unresolved pending transaction is considered abandoned.
    $ttlMinutes = (int) config('pkpms.stale_payment_ttl_minutes', 30);
    $cutoff     = now()->subMinutes($ttlMinutes);

    $updated = PaymentTransaction::query()
        ->where('status', 'pending')
        ->where('created_at', '<=', $cutoff)
        ->get()
        ->each(function (PaymentTransaction $tx) {
            $payload                   = $tx->provider_payload ?? [];
            $payload['abandon_reason'] = 'stale_timeout';
            $payload['abandoned_at']   = now()->toIso8601String();

            $tx->update([
                'status'           => 'failed',
                'provider_payload' => $payload,
            ]);
        })
        ->count();

    if ($updated > 0) {
        Log::info('[pkpms:cleanup-stale-payments] Marked stale transactions as failed', ['count' => $updated]);
    }

    $this->info("Stale payment cleanup complete | marked_failed={$updated}");
})->purpose('Mark pending PayCools transactions as failed after the configured TTL.');

Schedule::command('pkpms:sync-lifecycle')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('pkpms:dispatch-scheduled-notifications')->everyMinute()->withoutOverlapping();
Schedule::command('pkpms:detect-fraud')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('pkpms:cleanup-stale-payments')->everyThirtyMinutes()->withoutOverlapping();
