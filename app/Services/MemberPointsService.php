<?php

namespace App\Services;

use App\Models\BonusRule;
use App\Models\ConversionSetting;
use App\Models\Member;
use App\Models\PaymentTransaction;
use App\Models\PointTransaction;
use Illuminate\Support\Facades\Log;

/**
 * Awards points to a member after a successful payment (subscription or donation).
 * Uses the same formula as merchant QR-scan grant-points:
 *   base  = amount × ConversionSetting.points_per_currency
 *   final = floor(base × BonusRule.multiplier)   // 1.0 if no active rule
 */
final class MemberPointsService
{
    /**
     * Award points for a captured PayCools payment (members only). Idempotent.
     */
    public static function awardForPaymentTransaction(PaymentTransaction $tx): void
    {
        if ($tx->status !== 'captured' || $tx->subscribable_type !== Member::class) {
            return;
        }

        $member = Member::find($tx->subscribable_id);
        if (! $member) {
            return;
        }

        [$reason, $ref] = $tx->transaction_type === 'donation'
            ? ['Donation payment', 'DON-' . $tx->id]
            : ['Subscription payment', 'SUB-' . $tx->id];

        self::awardForPayment($member, (float) $tx->amount_total, $reason, $ref);
    }

    /**
     * Award points to a member for a completed payment.
     *
     * Safe to call multiple times for the same $ref — idempotent via reference_number.
     * A failure here NEVER bubbles up; it only logs so the payment capture is unaffected.
     *
     * @param  Member  $member   The member receiving the points.
     * @param  float   $amount   The payment amount in the transaction currency (e.g. MYR).
     * @param  string  $reason   Human-readable label shown in the member's history
     *                           (e.g. "Subscription payment", "Donation payment").
     * @param  string  $ref      Unique reference tied to this payment (e.g. "SUB-42").
     */
    public static function awardForPayment(
        Member $member,
        float $amount,
        string $reason,
        string $ref,
    ): void {
        try {
            // Idempotency — skip if already awarded for this transaction.
            if (PointTransaction::where('reference_number', $ref)->exists()) {
                return;
            }

            $conversion = ConversionSetting::where('is_active', true)
                ->whereDate('effective_date', '<=', now())
                ->latest('effective_date')
                ->first();

            if (! $conversion) {
                Log::warning('[MemberPoints] No active conversion setting — points not awarded.', [
                    'member_id' => $member->id,
                    'ref'       => $ref,
                ]);

                return;
            }

            $bonusRule = BonusRule::where('is_enabled', true)
                ->where(function ($q) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->first();

            $multiplier  = $bonusRule ? (float) $bonusRule->multiplier : 1.0;
            $basePoints  = $amount * (float) $conversion->points_per_currency;
            $finalPoints = (int) floor($basePoints * $multiplier);

            if ($finalPoints < 1) {
                Log::info('[MemberPoints] Amount too low to earn points — skipping.', [
                    'member_id' => $member->id,
                    'amount'    => $amount,
                    'ref'       => $ref,
                ]);

                return;
            }

            PointTransaction::create([
                'merchant_id'     => null,
                'member_id'       => $member->id,
                'type'            => 'allocation',
                'currency_amount' => $amount,
                'points'          => $finalPoints,
                'reference_number' => $ref,
                'reason'          => $reason,
                'status'          => 'completed',
                'occurred_at'     => now(),
            ]);

            $member->increment('total_points', $finalPoints);

            Log::info('[MemberPoints] Points awarded.', [
                'member_id'   => $member->id,
                'amount'      => $amount,
                'points'      => $finalPoints,
                'reason'      => $reason,
                'ref'         => $ref,
                'multiplier'  => $multiplier,
            ]);
        } catch (\Throwable $e) {
            Log::error('[MemberPoints] Failed to award points — payment unaffected.', [
                'member_id' => $member->id,
                'ref'       => $ref,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
