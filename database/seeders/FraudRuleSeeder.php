<?php

namespace Database\Seeders;

use App\Models\FraudRule;
use Illuminate\Database\Seeder;

class FraudRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'code' => 'rapid_repeat_redemptions',
                'name' => 'Rapid Repeat Redemptions',
                'description' => 'Flags members performing many redemptions within a short period.',
                'severity' => 'high',
                'is_enabled' => true,
                'thresholds_json' => ['min_count' => 3, 'lookback_minutes' => 10],
                'window_minutes' => 10,
                'sort_order' => 10,
            ],
            [
                'code' => 'same_item_repeat_redemption',
                'name' => 'Same Item Repeat Redemption',
                'description' => 'Flags repeated redemption of the same item by the same member in a short window.',
                'severity' => 'high',
                'is_enabled' => true,
                'thresholds_json' => ['min_count' => 2, 'lookback_minutes' => 5],
                'window_minutes' => 5,
                'sort_order' => 20,
            ],
            [
                'code' => 'high_hourly_redemption_points',
                'name' => 'High Hourly Redemption Points',
                'description' => 'Flags unusually high total redeemed points by a member within one hour.',
                'severity' => 'critical',
                'is_enabled' => true,
                'thresholds_json' => ['min_points' => 2000, 'lookback_minutes' => 60],
                'window_minutes' => 60,
                'sort_order' => 30,
            ],
            [
                'code' => 'earn_then_redeem_burst',
                'name' => 'Earn Then Redeem Burst',
                'description' => 'Flags a large point-earning event quickly followed by heavy redemption.',
                'severity' => 'critical',
                'is_enabled' => true,
                'thresholds_json' => ['min_earned_points' => 1000, 'redeem_ratio' => 0.8, 'lookback_minutes' => 30],
                'window_minutes' => 30,
                'sort_order' => 40,
            ],
            [
                'code' => 'merchant_member_pair_concentration',
                'name' => 'Merchant-Member Pair Concentration',
                'description' => 'Flags concentrated point issuance from one merchant to one member in a short period.',
                'severity' => 'high',
                'is_enabled' => true,
                'thresholds_json' => ['min_transactions' => 5, 'lookback_minutes' => 30],
                'window_minutes' => 30,
                'sort_order' => 50,
            ],
            [
                'code' => 'duplicate_reference_number',
                'name' => 'Duplicate Reference Number',
                'description' => 'Flags transactions or redemptions sharing the same reference number.',
                'severity' => 'medium',
                'is_enabled' => true,
                'thresholds_json' => ['min_count' => 2, 'lookback_minutes' => 1440],
                'window_minutes' => 1440,
                'sort_order' => 60,
            ],
            [
                'code' => 'redemption_without_matching_transaction',
                'name' => 'Redemption Without Matching Transaction',
                'description' => 'Flags redemption records that are missing an equivalent redemption point transaction.',
                'severity' => 'critical',
                'is_enabled' => true,
                'thresholds_json' => ['lookback_minutes' => 1440],
                'window_minutes' => 1440,
                'sort_order' => 70,
            ],
        ];

        foreach ($rules as $rule) {
            FraudRule::updateOrCreate(
                ['code' => $rule['code']],
                $rule
            );
        }
    }
}
