<?php

namespace App\Jobs;

use App\Mail\SubscriptionReceiptMail;
use App\Models\PaymentTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionReceiptEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $transactionId) {}

    public function handle(): void
    {
        $tx = PaymentTransaction::with(['plan', 'subscribable'])->find($this->transactionId);
        if (! $tx) {
            return;
        }

        if ($tx->receipt_emailed_at !== null) {
            return;
        }

        if ((string) $tx->status !== 'captured') {
            return;
        }

        if ($tx->subscription_plan_id === null || $tx->fundraising_campaign_id !== null) {
            return;
        }

        $subscriber = $tx->subscribable;
        if (! $subscriber) {
            return;
        }

        $recipient = trim((string) ($subscriber->email ?? $subscriber->company_email ?? ''));
        if ($recipient === '') {
            return;
        }

        $subscriberName = (string) ($subscriber->full_name ?? $subscriber->company_name ?? 'Subscriber');
        $planName = (string) ($tx->plan?->name ?? 'Subscription Plan');

        Mail::to($recipient)->send(new SubscriptionReceiptMail($tx, $subscriberName, $planName));

        $tx->forceFill([
            'receipt_emailed_at' => now(),
        ])->save();
    }
}
