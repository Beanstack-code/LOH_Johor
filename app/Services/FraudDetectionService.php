<?php

namespace App\Services;

use App\Models\FraudAlert;
use App\Models\FraudRule;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\RedemptionItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FraudDetectionService
{
    public function detect(): array
    {
        $rules = FraudRule::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get();

        $summary = [
            'rules_evaluated' => $rules->count(),
            'alerts_created_or_updated' => 0,
        ];

        /** @var FraudRule $rule */
        foreach ($rules as $rule) {
            $matches = $this->evaluateRule($rule);
            $triggeredAt = null;

            /** @var array<string, mixed> $match */
            foreach ($matches as $match) {
                $alert = $this->upsertAlert($rule, $match);
                $triggeredAt = $alert->triggered_at;
                $summary['alerts_created_or_updated']++;
            }

            if ($triggeredAt) {
                $rule->last_triggered_at = $triggeredAt;
                $rule->save();
            }
        }

        return $summary;
    }

    private function evaluateRule(FraudRule $rule): Collection
    {
        return match ($rule->code) {
            'rapid_repeat_redemptions' => $this->rapidRepeatRedemptions($rule),
            'same_item_repeat_redemption' => $this->sameItemRepeatRedemption($rule),
            'high_hourly_redemption_points' => $this->highHourlyRedemptionPoints($rule),
            'earn_then_redeem_burst' => $this->earnThenRedeemBurst($rule),
            'merchant_member_pair_concentration' => $this->merchantMemberPairConcentration($rule),
            'duplicate_reference_number' => $this->duplicateReferenceNumber($rule),
            'redemption_without_matching_transaction' => $this->redemptionWithoutMatchingTransaction($rule),
            'stock_drain_anomaly' => $this->stockDrainAnomaly($rule),
            default => collect(),
        };
    }

    private function upsertAlert(FraudRule $rule, array $match): FraudAlert
    {
        $entityType = $match['entity_type'] ?? null;
        $entityId = $match['entity_id'] ?? null;
        $key = $match['event_key'] ?? null;
        $fingerprint = sha1(implode('|', [
            $rule->code,
            (string) $entityType,
            (string) $entityId,
            is_scalar($key) ? (string) $key : json_encode($key),
        ]));

        return FraudAlert::updateOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'fraud_rule_id' => $rule->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'risk_score' => (int) ($match['risk_score'] ?? $this->defaultRiskScore($rule->severity)),
                'severity' => $match['severity'] ?? $rule->severity,
                'status' => 'open',
                'triggered_at' => $match['triggered_at'] ?? now(),
                'payload_json' => $match['payload'] ?? null,
                'resolved_by' => null,
                'resolved_at' => null,
            ]
        );
    }

    private function defaultRiskScore(string $severity): int
    {
        return match ($severity) {
            'critical' => 90,
            'high' => 75,
            'medium' => 60,
            default => 50,
        };
    }

    private function rapidRepeatRedemptions(FraudRule $rule): Collection
    {
        $thresholds = (array) ($rule->thresholds_json ?? []);
        $minCount = (int) ($thresholds['min_count'] ?? 3);
        $windowMinutes = (int) ($thresholds['lookback_minutes'] ?? $rule->window_minutes);
        $from = now()->subMinutes($windowMinutes);

        return Redemption::query()
            ->select('member_id', DB::raw('COUNT(*) as redemption_count'), DB::raw('MAX(redeemed_at) as last_redeemed_at'))
            ->where('redeemed_at', '>=', $from)
            ->groupBy('member_id')
            ->having('redemption_count', '>=', $minCount)
            ->get()
            ->map(fn ($row) => [
                'entity_type' => Member::class,
                'entity_id' => (int) $row->member_id,
                'event_key' => ['member_id' => (int) $row->member_id, 'window' => $windowMinutes],
                'triggered_at' => Carbon::parse($row->last_redeemed_at),
                'risk_score' => min(100, 70 + (((int) $row->redemption_count - $minCount) * 5)),
                'payload' => [
                    'redemption_count' => (int) $row->redemption_count,
                    'window_minutes' => $windowMinutes,
                    'threshold' => $minCount,
                ],
            ]);
    }

    private function sameItemRepeatRedemption(FraudRule $rule): Collection
    {
        $thresholds = (array) ($rule->thresholds_json ?? []);
        $minCount = (int) ($thresholds['min_count'] ?? 2);
        $windowMinutes = (int) ($thresholds['lookback_minutes'] ?? $rule->window_minutes);
        $from = now()->subMinutes($windowMinutes);

        return Redemption::query()
            ->select(
                'member_id',
                'redemption_item_id',
                DB::raw('COUNT(*) as redemption_count'),
                DB::raw('MAX(redeemed_at) as last_redeemed_at')
            )
            ->where('redeemed_at', '>=', $from)
            ->groupBy('member_id', 'redemption_item_id')
            ->having('redemption_count', '>=', $minCount)
            ->get()
            ->map(fn ($row) => [
                'entity_type' => Member::class,
                'entity_id' => (int) $row->member_id,
                'event_key' => [
                    'member_id' => (int) $row->member_id,
                    'item_id' => (int) $row->redemption_item_id,
                ],
                'triggered_at' => Carbon::parse($row->last_redeemed_at),
                'risk_score' => min(100, 75 + (((int) $row->redemption_count - $minCount) * 8)),
                'payload' => [
                    'redemption_count' => (int) $row->redemption_count,
                    'redemption_item_id' => (int) $row->redemption_item_id,
                    'window_minutes' => $windowMinutes,
                    'threshold' => $minCount,
                ],
            ]);
    }

    private function highHourlyRedemptionPoints(FraudRule $rule): Collection
    {
        $thresholds = (array) ($rule->thresholds_json ?? []);
        $minPoints = (int) ($thresholds['min_points'] ?? 2000);
        $windowMinutes = (int) ($thresholds['lookback_minutes'] ?? $rule->window_minutes);
        $from = now()->subMinutes($windowMinutes);

        return Redemption::query()
            ->select('member_id', DB::raw('SUM(points_used) as total_points_used'), DB::raw('MAX(redeemed_at) as last_redeemed_at'))
            ->where('redeemed_at', '>=', $from)
            ->groupBy('member_id')
            ->having('total_points_used', '>=', $minPoints)
            ->get()
            ->map(fn ($row) => [
                'entity_type' => Member::class,
                'entity_id' => (int) $row->member_id,
                'event_key' => ['member_id' => (int) $row->member_id, 'window' => $windowMinutes],
                'triggered_at' => Carbon::parse($row->last_redeemed_at),
                'risk_score' => min(100, 85 + (int) floor(((int) $row->total_points_used - $minPoints) / 200)),
                'payload' => [
                    'total_points_used' => (int) $row->total_points_used,
                    'window_minutes' => $windowMinutes,
                    'threshold' => $minPoints,
                ],
            ]);
    }

    private function earnThenRedeemBurst(FraudRule $rule): Collection
    {
        $thresholds = (array) ($rule->thresholds_json ?? []);
        $minEarnedPoints = (int) ($thresholds['min_earned_points'] ?? 1000);
        $redeemRatio = (float) ($thresholds['redeem_ratio'] ?? 0.8);
        $windowMinutes = (int) ($thresholds['lookback_minutes'] ?? $rule->window_minutes);
        $from = now()->subMinutes($windowMinutes);

        $earnedByMember = PointTransaction::query()
            ->select('member_id', DB::raw('SUM(points) as earned_points'), DB::raw('MAX(occurred_at) as last_occurred_at'))
            ->whereNotNull('member_id')
            ->where('points', '>', 0)
            ->where('occurred_at', '>=', $from)
            ->groupBy('member_id')
            ->having('earned_points', '>=', $minEarnedPoints)
            ->get()
            ->keyBy('member_id');

        if ($earnedByMember->isEmpty()) {
            return collect();
        }

        $redeemedByMember = Redemption::query()
            ->select('member_id', DB::raw('SUM(points_used) as redeemed_points'), DB::raw('MAX(redeemed_at) as last_redeemed_at'))
            ->whereIn('member_id', $earnedByMember->keys()->all())
            ->where('redeemed_at', '>=', $from)
            ->groupBy('member_id')
            ->get()
            ->keyBy('member_id');

        return $earnedByMember->map(function ($earnedRow, $memberId) use ($redeemedByMember, $redeemRatio, $windowMinutes) {
            $redeemedRow = $redeemedByMember->get($memberId);
            $earned = (int) ($earnedRow->earned_points ?? 0);
            $redeemed = (int) ($redeemedRow->redeemed_points ?? 0);

            if ($earned <= 0 || $redeemed < (int) floor($earned * $redeemRatio)) {
                return null;
            }

            return [
                'entity_type' => Member::class,
                'entity_id' => (int) $memberId,
                'event_key' => ['member_id' => (int) $memberId, 'window' => $windowMinutes],
                'triggered_at' => Carbon::parse($redeemedRow->last_redeemed_at ?? $earnedRow->last_occurred_at),
                'risk_score' => min(100, 88 + (int) floor(($redeemed / max(1, $earned)) * 10)),
                'payload' => [
                    'earned_points' => $earned,
                    'redeemed_points' => $redeemed,
                    'redeem_ratio' => round($redeemed / max(1, $earned), 2),
                    'window_minutes' => $windowMinutes,
                ],
            ];
        })->filter()->values();
    }

    private function merchantMemberPairConcentration(FraudRule $rule): Collection
    {
        $thresholds = (array) ($rule->thresholds_json ?? []);
        $minTransactions = (int) ($thresholds['min_transactions'] ?? 5);
        $windowMinutes = (int) ($thresholds['lookback_minutes'] ?? $rule->window_minutes);
        $from = now()->subMinutes($windowMinutes);

        return PointTransaction::query()
            ->select(
                'merchant_id',
                'member_id',
                DB::raw('COUNT(*) as txn_count'),
                DB::raw('MAX(occurred_at) as last_occurred_at')
            )
            ->whereNotNull('merchant_id')
            ->whereNotNull('member_id')
            ->where('points', '>', 0)
            ->where('occurred_at', '>=', $from)
            ->groupBy('merchant_id', 'member_id')
            ->having('txn_count', '>=', $minTransactions)
            ->get()
            ->map(fn ($row) => [
                'entity_type' => Merchant::class,
                'entity_id' => (int) $row->merchant_id,
                'event_key' => [
                    'merchant_id' => (int) $row->merchant_id,
                    'member_id' => (int) $row->member_id,
                    'window' => $windowMinutes,
                ],
                'triggered_at' => Carbon::parse($row->last_occurred_at),
                'risk_score' => min(100, 72 + (((int) $row->txn_count - $minTransactions) * 6)),
                'payload' => [
                    'merchant_id' => (int) $row->merchant_id,
                    'member_id' => (int) $row->member_id,
                    'transaction_count' => (int) $row->txn_count,
                    'window_minutes' => $windowMinutes,
                    'threshold' => $minTransactions,
                ],
            ]);
    }

    private function duplicateReferenceNumber(FraudRule $rule): Collection
    {
        $thresholds = (array) ($rule->thresholds_json ?? []);
        $minCount = (int) ($thresholds['min_count'] ?? 2);
        $windowMinutes = (int) ($thresholds['lookback_minutes'] ?? $rule->window_minutes);
        $from = now()->subMinutes($windowMinutes);

        $fromTransactions = PointTransaction::query()
            ->select('reference_number', DB::raw('COUNT(*) as duplicate_count'), DB::raw('MAX(occurred_at) as last_seen_at'))
            ->whereNotNull('reference_number')
            ->where('reference_number', '!=', '')
            ->where('occurred_at', '>=', $from)
            ->groupBy('reference_number')
            ->having('duplicate_count', '>=', $minCount)
            ->get()
            ->map(fn ($row) => [
                'entity_type' => null,
                'entity_id' => null,
                'event_key' => ['source' => 'point_transactions', 'reference_number' => (string) $row->reference_number],
                'triggered_at' => Carbon::parse($row->last_seen_at),
                'risk_score' => min(100, 60 + (((int) $row->duplicate_count - $minCount) * 5)),
                'payload' => [
                    'source' => 'point_transactions',
                    'reference_number' => (string) $row->reference_number,
                    'duplicate_count' => (int) $row->duplicate_count,
                    'window_minutes' => $windowMinutes,
                ],
            ]);

        $fromRedemptions = Redemption::query()
            ->select('reference_number', DB::raw('COUNT(*) as duplicate_count'), DB::raw('MAX(redeemed_at) as last_seen_at'))
            ->whereNotNull('reference_number')
            ->where('reference_number', '!=', '')
            ->where('redeemed_at', '>=', $from)
            ->groupBy('reference_number')
            ->having('duplicate_count', '>=', $minCount)
            ->get()
            ->map(fn ($row) => [
                'entity_type' => null,
                'entity_id' => null,
                'event_key' => ['source' => 'redemptions', 'reference_number' => (string) $row->reference_number],
                'triggered_at' => Carbon::parse($row->last_seen_at),
                'risk_score' => min(100, 60 + (((int) $row->duplicate_count - $minCount) * 5)),
                'payload' => [
                    'source' => 'redemptions',
                    'reference_number' => (string) $row->reference_number,
                    'duplicate_count' => (int) $row->duplicate_count,
                    'window_minutes' => $windowMinutes,
                ],
            ]);

        return $fromTransactions->concat($fromRedemptions)->values();
    }

    private function redemptionWithoutMatchingTransaction(FraudRule $rule): Collection
    {
        $thresholds = (array) ($rule->thresholds_json ?? []);
        $windowMinutes = (int) ($thresholds['lookback_minutes'] ?? $rule->window_minutes);
        $from = now()->subMinutes($windowMinutes);

        return Redemption::query()
            ->leftJoin('point_transactions as pt', function ($join) {
                $join->on('pt.reference_number', '=', 'redemptions.reference_number')
                    ->on('pt.member_id', '=', 'redemptions.member_id')
                    ->where('pt.type', '=', 'redemption');
            })
            ->where('redemptions.redeemed_at', '>=', $from)
            ->whereNull('pt.id')
            ->select('redemptions.id', 'redemptions.member_id', 'redemptions.reference_number', 'redemptions.points_used', 'redemptions.redeemed_at')
            ->get()
            ->map(fn ($row) => [
                'entity_type' => Member::class,
                'entity_id' => (int) $row->member_id,
                'event_key' => ['redemption_id' => (int) $row->id],
                'triggered_at' => Carbon::parse($row->redeemed_at),
                'risk_score' => 92,
                'payload' => [
                    'redemption_id' => (int) $row->id,
                    'reference_number' => (string) $row->reference_number,
                    'points_used' => (int) $row->points_used,
                ],
            ]);
    }

    private function stockDrainAnomaly(FraudRule $rule): Collection
    {
        $thresholds = (array) ($rule->thresholds_json ?? []);
        $minRedemptions = (int) ($thresholds['min_redemptions'] ?? 5);
        $windowMinutes = (int) ($thresholds['lookback_minutes'] ?? $rule->window_minutes);
        $from = now()->subMinutes($windowMinutes);

        return Redemption::query()
            ->select(
                'redemption_item_id',
                DB::raw('COUNT(*) as redemption_count'),
                DB::raw('MAX(redeemed_at) as last_redeemed_at')
            )
            ->where('redeemed_at', '>=', $from)
            ->groupBy('redemption_item_id')
            ->having('redemption_count', '>=', $minRedemptions)
            ->get()
            ->map(fn ($row) => [
                'entity_type' => RedemptionItem::class,
                'entity_id' => (int) $row->redemption_item_id,
                'event_key' => ['item_id' => (int) $row->redemption_item_id, 'window' => $windowMinutes],
                'triggered_at' => Carbon::parse($row->last_redeemed_at),
                'risk_score' => min(100, 58 + (((int) $row->redemption_count - $minRedemptions) * 4)),
                'payload' => [
                    'redemption_item_id' => (int) $row->redemption_item_id,
                    'redemption_count' => (int) $row->redemption_count,
                    'window_minutes' => $windowMinutes,
                    'threshold' => $minRedemptions,
                ],
            ]);
    }
}
