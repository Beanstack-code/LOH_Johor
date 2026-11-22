<?php

namespace App\Jobs;

use App\Mail\DonationReceiptMail;
use App\Models\Donation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendDonationReceiptEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $donationId) {}

    public function handle(): void
    {
        $donation = Donation::with('campaign')->find($this->donationId);
        if (! $donation) {
            return;
        }

        if ($donation->receipt_emailed_at !== null) {
            return;
        }

        if ((string) $donation->payment_status !== 'captured') {
            return;
        }

        $recipient = trim((string) ($donation->member_email ?? ''));
        if ($recipient === '') {
            return;
        }

        $campaignName = (string) ($donation->campaign?->campaign_name ?? 'Fundraising Campaign');

        Mail::to($recipient)->send(new DonationReceiptMail($donation, $campaignName));

        $donation->forceFill([
            'receipt_emailed_at' => now(),
        ])->save();
    }
}
