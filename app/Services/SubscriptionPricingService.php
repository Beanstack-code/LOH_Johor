<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Merchant;
use App\Models\SubscriptionPlan;

class SubscriptionPricingService
{
    /**
     * Compute the full cost breakdown for a member / plan combination.
     *
     * This is the **single authoritative pricing function** used by:
     *   - GET  /subscription/quote              (display to user before payment)
     *   - POST /subscription/paycools/order     (create the gateway order)
     *   - POST /subscribe                       (direct activation when allowed)
     *
     * Never accept plan_amount, admin_fee or total from the client — always
     * call this method server-side with a fresh DB record.
     *
     * @return array{
     *   plan_id: int,
     *   plan_name: string,
     *   plan_amount: float,
     *   admin_fee: float,
     *   total: float,
     *   total_minor: int,
     *   currency: string,
     *   fee_reason: string,
     *   quote_hash: string,
     * }
     */
    public function amountsForMemberAndPlan(Member $member, SubscriptionPlan $plan): array
    {
        $planAmount = (float) $plan->final_price;
        $adminFee   = $member->pendingAdminFee();
        $total      = round($planAmount + $adminFee, 2);
        $currency   = 'MYR';
        $feeReason  = $member->adminFeeReason();

        // Convert to smallest currency unit (sen) for gateway.
        // MYR amounts are commonly transmitted in sen (100 sen = RM 1.00).
        // TODO(paycools-spec): confirm PayCools accepts minor units; adjust if it expects major units.
        $totalMinor = (int) round($total * 100);

        // HMAC of the key pricing facts lets the server detect stale-quote
        // submissions without trusting any client-supplied amount.
        $quoteHash = $this->makeQuoteHash(
            'member',
            $member->id,
            $plan->id,
            $planAmount,
            $adminFee,
            $feeReason,
        );

        return [
            'plan_id'     => $plan->id,
            'plan_name'   => $plan->name,
            'plan_amount' => $planAmount,
            'admin_fee'   => $adminFee,
            'total'       => $total,
            'total_minor' => $totalMinor,
            'currency'    => $currency,
            'fee_reason'  => $feeReason,
            'quote_hash'  => $quoteHash,
        ];
    }

    /**
     * Merchants pay plan price only (no admin fee).
     *
     * @return array{
     *   plan_id: int,
     *   plan_name: string,
     *   plan_amount: float,
     *   admin_fee: float,
     *   total: float,
     *   total_minor: int,
     *   currency: string,
     *   fee_reason: string,
     *   quote_hash: string,
     * }
     */
    public function amountsForMerchantAndPlan(Merchant $merchant, SubscriptionPlan $plan): array
    {
        $planAmount = (float) $plan->final_price;
        $adminFee   = 0.0;
        $total      = round($planAmount, 2);
        $currency   = 'MYR';
        $feeReason  = 'not_applicable';

        $totalMinor = (int) round($total * 100);

        $quoteHash = $this->makeQuoteHash(
            'merchant',
            $merchant->id,
            $plan->id,
            $planAmount,
            $adminFee,
            $feeReason,
        );

        return [
            'plan_id'     => $plan->id,
            'plan_name'   => $plan->name,
            'plan_amount' => $planAmount,
            'admin_fee'   => $adminFee,
            'total'       => $total,
            'total_minor' => $totalMinor,
            'currency'    => $currency,
            'fee_reason'  => $feeReason,
            'quote_hash'  => $quoteHash,
        ];
    }

    /**
     * Unified pricing for either a Member or Merchant.
     * Use this in controllers so the right logic is always applied regardless of role.
     */
    public function pricingForSubscribable(Member|Merchant $user, SubscriptionPlan $plan): array
    {
        return match (true) {
            $user instanceof Member   => $this->amountsForMemberAndPlan($user, $plan),
            $user instanceof Merchant => $this->amountsForMerchantAndPlan($user, $plan),
            default                   => throw new \InvalidArgumentException('Unsupported subscribable type.'),
        };
    }

    /**
     * Verify that a quote_hash submitted by the client still matches the
     * current server-computed pricing.  Use this before creating a gateway
     * order to catch stale / tampered quotes.
     */
    public function verifyQuoteHash(
        Member|Merchant $user,
        SubscriptionPlan $plan,
        string $clientHash
    ): bool {
        $current = $this->pricingForSubscribable($user, $plan);

        return hash_equals($current['quote_hash'], $clientHash);
    }

    /**
     * $subscribableKind ('member' | 'merchant') is included first in the pipe
     * string so member id=5 and merchant id=5 never produce the same hash.
     */
    private function makeQuoteHash(
        string $subscribableKind,
        int $subscribableId,
        int $planId,
        float $planAmount,
        float $adminFee,
        string $feeReason,
    ): string {
        $payload = implode('|', [
            $subscribableKind,
            $subscribableId,
            $planId,
            number_format($planAmount, 2, '.', ''),
            number_format($adminFee, 2, '.', ''),
            $feeReason,
        ]);

        return hash_hmac('sha256', $payload, config('app.key'));
    }
}
