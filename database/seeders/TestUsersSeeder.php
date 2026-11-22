<?php

namespace Database\Seeders;

use App\Models\Donation;
use App\Models\FundraisingCampaign;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\RedemptionItem;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    /**
     * Seed test users for development/testing.
     */
    public function run(): void
    {
        // Test account passwords are random unless a local value is provided.
        $testPasswordHash = bcrypt(env('TEST_USERS_PASSWORD') ?: bin2hex(random_bytes(32)));

        // ─── Test Member ─────────────────────────────────────────────────────
        $member = Member::updateOrCreate(
            ['email' => 'test-member@example.test'],
            [
                'membership_id' => Member::generateMembershipId(),
                'full_name' => 'Test Member',
                'email' => 'test-merchant@example.test',
                'password' => $testPasswordHash,
                'phone' => '+60123456789',
                'status' => 'active',
                'subscription_state' => 'active_subscription',
                'membership_status' => 'active',
                'total_points' => 1000,
            ]
        );

        // ─── Test Member subscription (required for /home and core features) ─
        $memberBasicPlan = SubscriptionPlan::where('name', 'Member Basic')
            ->where('audience', 'member')
            ->first();

        if ($memberBasicPlan) {
            Subscription::updateOrCreate(
                [
                    'subscribable_type' => Member::class,
                    'subscribable_id'   => $member->id,
                    'status'            => 'active',
                ],
                [
                    'subscription_plan_id' => $memberBasicPlan->id,
                    'starts_at'            => now(),
                    'status'               => 'active',
                ]
            );
            $member->recordSubscriptionActivated(true);
        }

        // ─── Test Merchant (unsubscribed — lands in paywall) ─────────────────
        $merchant = Merchant::updateOrCreate(
            ['company_email' => 'merchant@test.com'],
            [
                'membership_id' => Merchant::generateMembershipId(),
                'company_name' => 'Test Merchant',
                'company_email' => 'merchant@test.com',
                'password' => $testPasswordHash,
                'phone' => '+60123456790',
                'categories' => ['Food & Beverage'],
                'registration_number' => 'TEST-123456',
                'street_address' => '123 Test Street',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'Malaysia',
                'status' => 'pending_subscription',
                'subscription_state' => 'pending_subscription',
            ]
        );

        Subscription::where('subscribable_type', Merchant::class)
            ->where('subscribable_id', $merchant->id)
            ->delete();

        // ─── Sample point transactions between test merchant & member ────────
        // A few historical allocations so the mobile app has data to display.
        foreach ([7, 3, 1] as $daysAgo) {
            $amount = 50 * (4 - $daysAgo % 3); // 50, 100, 150 pattern
            $points = $amount;                 // 1 MYR = 1 point for test

            PointTransaction::updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'member_id' => $member->id,
                    'reference_number' => 'TEST-' . now()->subDays($daysAgo)->format('Ymd') . "-$daysAgo",
                ],
                [
                    'type' => 'allocation',
                    'currency_amount' => $amount,
                    'points' => $points,
                    'reason' => 'Test purchase at Test Merchant',
                    'status' => 'completed',
                    'occurred_at' => now()->subDays($daysAgo)->setTime(12 + $daysAgo, 15),
                ]
            );
        }

        // ─── Test redemption item & redemption for member ─────────────────────
        $voucherItem = RedemptionItem::updateOrCreate(
            ['name' => 'Test RM20 Voucher'],
            [
                'description' => 'Demo voucher for development/testing only.',
                'required_points' => 200,
                'quantity_available' => 999,
                'expiry_date' => now()->addYear()->toDateString(),
                'status' => 'active',
            ]
        );

        Redemption::updateOrCreate(
            [
                'member_id' => $member->id,
                'redemption_item_id' => $voucherItem->id,
                'reference_number' => 'RD-TST-0001',
            ],
            [
                'points_used' => 200,
                'status' => 'completed',
                'redeemed_at' => now()->subDays(2),
            ]
        );

        // ─── Simple fundraising campaign & donation from test member ─────────
        $campaign = FundraisingCampaign::updateOrCreate(
            ['campaign_name' => 'Test Community Fund'],
            [
                'organization_name' => 'PKPMS Demo Org',
                'description' => 'Demo campaign used for app development screens.',
                'sub_description' => null,
                'impact' => null,
                'how_it_helps' => [],
                'target_amount' => 10000,
                'current_collected_amount' => 500,
                'starts_at' => now()->subDays(10),
                'ends_at' => now()->addDays(20),
                'status' => 'active',
            ]
        );

        Donation::updateOrCreate(
            [
                'fundraising_campaign_id' => $campaign->id,
                'member_email' => $member->email,
            ],
            [
                'member_name' => $member->full_name,
                'amount' => 120,
                'donated_at' => now()->subDays(1),
            ]
        );

    }
}
