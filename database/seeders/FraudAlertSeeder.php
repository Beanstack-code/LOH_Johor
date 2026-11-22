<?php

namespace Database\Seeders;

use App\Models\FraudAlert;
use App\Models\FraudRule;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\RedemptionItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class FraudAlertSeeder extends Seeder
{
    public function run(): void
    {
        $rules = FraudRule::query()->pluck('id', 'code');
        $resolverId = User::query()->orderBy('id')->value('id');

        $alerts = [
            [
                'rule_code' => 'rapid_repeat_redemptions',
                'entity_type' => Member::class,
                'entity_id' => 1,
                'risk_score' => 78,
                'severity' => 'high',
                'status' => 'open',
                'triggered_at' => now()->subMinutes(18),
                'payload_json' => [
                    'redemption_count' => 4,
                    'window_minutes' => 10,
                    'threshold' => 3,
                ],
            ],
            [
                'rule_code' => 'same_item_repeat_redemption',
                'entity_type' => Member::class,
                'entity_id' => 2,
                'risk_score' => 83,
                'severity' => 'high',
                'status' => 'acknowledged',
                'triggered_at' => now()->subMinutes(55),
                'payload_json' => [
                    'redemption_count' => 3,
                    'redemption_item_id' => 1,
                    'window_minutes' => 5,
                    'threshold' => 2,
                ],
            ],
            [
                'rule_code' => 'high_hourly_redemption_points',
                'entity_type' => Member::class,
                'entity_id' => 3,
                'risk_score' => 92,
                'severity' => 'critical',
                'status' => 'resolved',
                'triggered_at' => now()->subHours(4),
                'payload_json' => [
                    'total_points_used' => 2600,
                    'window_minutes' => 60,
                    'threshold' => 2000,
                ],
                'resolved_by' => $resolverId,
                'resolved_at' => now()->subHours(3)->subMinutes(40),
            ],
            [
                'rule_code' => 'merchant_member_pair_concentration',
                'entity_type' => Merchant::class,
                'entity_id' => 1,
                'risk_score' => 74,
                'severity' => 'high',
                'status' => 'open',
                'triggered_at' => now()->subMinutes(95),
                'payload_json' => [
                    'merchant_id' => 1,
                    'member_id' => 1,
                    'transaction_count' => 6,
                    'window_minutes' => 30,
                    'threshold' => 5,
                ],
            ],
            [
                'rule_code' => 'redemption_without_matching_transaction',
                'entity_type' => RedemptionItem::class,
                'entity_id' => 1,
                'risk_score' => 60,
                'severity' => 'medium',
                'status' => 'false_positive',
                'triggered_at' => now()->subDays(1)->subMinutes(10),
                'payload_json' => [
                    'reference_number' => 'FP-DEMO-001',
                    'notes' => 'Sample seeded alert for QA triage flow.',
                ],
                'resolved_by' => $resolverId,
                'resolved_at' => now()->subDays(1),
            ],
        ];

        foreach ($alerts as $index => $alert) {
            $ruleId = $rules->get($alert['rule_code']);
            if (! $ruleId) {
                continue;
            }

            $fingerprint = sha1('seeded-fraud-alert-'.$index.'-'.$alert['rule_code']);

            FraudAlert::updateOrCreate(
                ['fingerprint' => $fingerprint],
                [
                    'fraud_rule_id' => $ruleId,
                    'entity_type' => $alert['entity_type'],
                    'entity_id' => $alert['entity_id'],
                    'risk_score' => $alert['risk_score'],
                    'severity' => $alert['severity'],
                    'status' => $alert['status'],
                    'triggered_at' => $alert['triggered_at'],
                    'payload_json' => $alert['payload_json'],
                    'resolved_by' => $alert['resolved_by'] ?? null,
                    'resolved_at' => $alert['resolved_at'] ?? null,
                ]
            );
        }
    }
}
