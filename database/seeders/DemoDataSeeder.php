<?php

namespace Database\Seeders;

use App\Models\AdminNotification;
use App\Models\Announcement;
use App\Models\BonusRule;
use App\Models\ConversionSetting;
use App\Models\Donation;
use App\Models\FundraisingCampaign;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\RedemptionItem;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Demo account passwords are random unless a local value is provided.
        $demoPasswordHash = bcrypt(env('DEMO_DATA_PASSWORD') ?: bin2hex(random_bytes(32)));

        // ─── Conversion Setting ───────────────────────────────────────────
        ConversionSetting::updateOrCreate(
            ['effective_date' => now()->toDateString()],
            [
                'currency_unit' => 'MYR',
                'points_per_currency' => 1.00,
                'is_active' => true,
            ]
        );

        // ─── Bonus Rule ───────────────────────────────────────────────────
        BonusRule::updateOrCreate(
            ['name' => '2x Weekend Bonus'],
            [
                'description' => 'Double points every weekend.',
                'multiplier' => 2.00,
                'is_enabled' => false,
                'starts_at' => null,
                'ends_at' => null,
            ]
        );

        // ─── Members ──────────────────────────────────────────────────────
        // Set DEMO_DATA_PASSWORD locally to use a known password for demo accounts.
        $members = collect([
            [
                'full_name' => 'Ahmad Fauzi',
                'email' => 'demo-account-1@example.test',
                'phone' => '0121111111',
                'password' => $demoPasswordHash,
                'total_points' => 0,
                'status' => 'pending_subscription',
                'subscription_state' => 'pending_subscription',
                'membership_status' => 'active',
            ],
            [
                'full_name' => 'Nur Aisyah',
                'email' => 'demo-account-2@example.test',
                'phone' => '0122222222',
                'password' => $demoPasswordHash,
                'total_points' => 0,
                'status' => 'pending_subscription',
                'subscription_state' => 'pending_subscription',
                'membership_status' => 'active',
            ],
            [
                'full_name' => 'Jason Lim',
                'email' => 'demo-account-3@example.test',
                'phone' => '0123333333',
                'password' => $demoPasswordHash,
                'total_points' => 0,
                'status' => 'pending_subscription',
                'subscription_state' => 'pending_subscription',
                'membership_status' => 'active',
            ],
        ])->map(fn ($data) => Member::updateOrCreate(['email' => $data['email']], $data));

        // ─── Merchants ────────────────────────────────────────────────────
        // categories must be array (JSON), separate address fields, password included
        $merchants = collect([
            [
                'company_name' => 'ABC Mart',
                'company_email' => 'abc@merchant.test',
                'phone' => '0388881111',
                'categories' => ['Retail', 'Supermarket & Grocery'],
                'registration_number' => 'SSM-12345',
                'street_address' => '12 Jalan Bukit Bintang',
                'city' => 'Kuala Lumpur',
                'postcode' => '55100',
                'country' => 'Malaysia',
                'short_description' => 'Your neighbourhood mart with a wide range of everyday essentials.',
                'about_description' => 'ABC Mart has been a neighbourhood favourite for years. We focus on fresh produce, pantry staples, and household essentials with friendly service and fair prices. Visit us for weekly specials and member-only offers.',
                'password' => $demoPasswordHash,
                'status' => 'pending',
                'subscription_state' => 'pending_subscription',
            ],
            [
                'company_name' => 'Blue Cafe',
                'company_email' => 'blue@merchant.test',
                'phone' => '0388882222',
                'categories' => ['Café & Coffee Shop', 'Food & Beverage'],
                'registration_number' => 'SSM-67890',
                'street_address' => '88 Jalan Ibrahim',
                'city' => 'Johor Bahru',
                'postcode' => '80000',
                'country' => 'Malaysia',
                'short_description' => 'Specialty coffee and light bites in the heart of JB.',
                'about_description' => 'Blue Cafe roasts small-batch beans and serves all-day brunch in Johor Bahru. Relax indoors or on our terrace, enjoy Wi‑Fi, and ask our baristas about seasonal blends and brewing workshops.',
                'password' => $demoPasswordHash,
                'status' => 'pending',
                'subscription_state' => 'pending_subscription',
            ],
        ])->map(fn ($data) => Merchant::updateOrCreate(['company_email' => $data['company_email']], $data));

        // ─── Subscription Plans ───────────────────────────────────────────
        $memberPlan = SubscriptionPlan::updateOrCreate(
            ['name' => 'Member Basic', 'audience' => 'member'],
            [
                'name_ms' => 'Ahli Asas',
                'name_zh' => '基本会员',
                'price' => 19.90,
                'discount_type' => 'none',
                'discount_value' => 0,
                'final_price' => 19.90,
                'is_active' => true,
                'display_order' => 1,
            ]
        );
        $memberPlan->descriptions()->delete();
        $memberPlan->descriptions()->createMany([
            ['description' => 'Earn points at all participating merchants', 'display_order' => 1],
            ['description' => 'Access to member exclusive deals', 'display_order' => 2],
            ['description' => 'Digital membership card with QR code', 'display_order' => 3],
        ]);

        $memberYearlyPlan = SubscriptionPlan::updateOrCreate(
            ['name' => 'Member Premium', 'audience' => 'member'],
            [
                'name_ms' => 'Ahli Premium',
                'name_zh' => '高级会员',
                'price' => 199.00,
                'discount_type' => 'percentage',
                'discount_value' => 17,
                'final_price' => 165.17,
                'is_active' => true,
                'display_order' => 2,
            ]
        );
        $memberYearlyPlan->descriptions()->delete();
        $memberYearlyPlan->descriptions()->createMany([
            ['description' => 'Everything in Member Basic', 'display_order' => 1],
            ['description' => 'Best value lifetime access', 'display_order' => 2],
            ['description' => 'Priority customer support', 'display_order' => 3],
        ]);

        $merchantPlan = SubscriptionPlan::updateOrCreate(
            ['name' => 'Merchant Pro', 'audience' => 'merchant'],
            [
                'price' => 99.00,
                'discount_type' => 'none',
                'discount_value' => 0,
                'final_price' => 99.00,
                'is_active' => true,
                'display_order' => 3,
            ]
        );
        $merchantPlan->descriptions()->delete();
        $merchantPlan->descriptions()->createMany([
            ['description' => 'Grant points to members via QR scan', 'display_order' => 1],
            ['description' => 'Business analytics dashboard', 'display_order' => 2],
            ['description' => 'Listed in member-facing merchant directory', 'display_order' => 3],
        ]);

        // ─── Subscriptions ────────────────────────────────────────────────
        // First member gets active subscription
        $firstMember = $members->first();
        Subscription::updateOrCreate(
            [
                'subscribable_type' => Member::class,
                'subscribable_id' => $firstMember->id,
                'subscription_plan_id' => $memberPlan->id,
            ],
            [
                'starts_at' => now()->subDays(5),
                'status' => 'active',
                'notes' => 'Demo seeded subscription',
            ]
        );
        $firstMember->refreshSubscriptionState();
        $firstMember->recordSubscriptionActivated(true);

        // Second member also gets active subscription
        $secondMember = $members->get(1);
        Subscription::updateOrCreate(
            [
                'subscribable_type' => Member::class,
                'subscribable_id' => $secondMember->id,
                'subscription_plan_id' => $memberYearlyPlan->id,
            ],
            [
                'starts_at' => now()->subDays(10),
                'status' => 'active',
                'notes' => 'Demo seeded subscription',
            ]
        );
        $secondMember->refreshSubscriptionState();
        $secondMember->recordSubscriptionActivated(true);

        // First merchant gets active subscription + admin approval
        $firstMerchant = $merchants->first();
        Subscription::updateOrCreate(
            [
                'subscribable_type' => Merchant::class,
                'subscribable_id' => $firstMerchant->id,
                'subscription_plan_id' => $merchantPlan->id,
            ],
            [
                'starts_at' => now()->subDays(3),
                'status' => 'active',
                'notes' => 'Demo seeded subscription',
            ]
        );
        $firstMerchant->refreshSubscriptionState();
        $firstMerchant->update(['status' => 'active']); // admin approved

        // ─── Point Transactions ───────────────────────────────────────────
        $activeMembers = $members->take(2);
        $now = now();
        $totalPointsByMember = [];

        foreach (range(0, 14) as $step) {
            $member = $activeMembers->random();
            $amount = rand(20, 300);
            $points = (int) floor($amount * 1.0);

            PointTransaction::create([
                'merchant_id' => $firstMerchant->id,
                'member_id' => $member->id,
                'type' => 'allocation',
                'currency_amount' => $amount,
                'points' => $points,
                'reference_number' => 'QR-' . $now->copy()->subDays(14 - $step)->format('Ymd') . '-' . str_pad((string) $step, 3, '0', STR_PAD_LEFT),
                'status' => 'completed',
                'occurred_at' => $now->copy()->subDays(14 - $step)->setTime(rand(9, 20), rand(0, 59)),
            ]);

            $totalPointsByMember[$member->id] = ($totalPointsByMember[$member->id] ?? 0) + $points;
        }

        // Update member total_points to reflect seeded transactions
        foreach ($totalPointsByMember as $memberId => $points) {
            Member::where('id', $memberId)->increment('total_points', $points);
        }

        // ─── Redemption Items ─────────────────────────────────────────────
        RedemptionItem::updateOrCreate(
            ['name' => 'Shopping Voucher RM50'],
            [
                'description' => 'Redeem for a RM50 shopping voucher valid at all participating merchants.',
                'required_points' => 500,
                'quantity_available' => 50,
                'expiry_date' => now()->addYear()->toDateString(),
                'status' => 'active',
            ]
        );

        RedemptionItem::updateOrCreate(
            ['name' => 'Free Coffee'],
            [
                'description' => 'One free coffee of your choice at Blue Cafe.',
                'required_points' => 100,
                'quantity_available' => 100,
                'expiry_date' => now()->addMonths(6)->toDateString(),
                'status' => 'active',
            ]
        );

        RedemptionItem::updateOrCreate(
            ['name' => 'RM20 Cash Rebate'],
            [
                'description' => 'RM20 cash rebate credited to your account.',
                'required_points' => 200,
                'quantity_available' => 30,
                'expiry_date' => now()->addMonths(3)->toDateString(),
                'status' => 'active',
            ]
        );

        // ─── Announcements (Home banner carousel) ─────────────────────────
        Announcement::updateOrCreate(
            ['title' => 'Double Points Weekend'],
            [
                'category' => 'Promotion',
                'content' => 'Earn double points this weekend at all participating merchants! Valid Saturday and Sunday only.',
                'terms_and_conditions' => 'Offer valid on weekends only. Cannot be combined with other promotions.',
                'status' => 'published',
                'is_highlighted' => true,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(7),
            ]
        );

        Announcement::updateOrCreate(
            ['title' => 'New Merchants Joined PKPMS'],
            [
                'category' => 'News',
                'content' => 'Welcome our newest merchants! Start earning points at ABC Mart and Blue Cafe today.',
                'terms_and_conditions' => null,
                'status' => 'published',
                'is_highlighted' => false,
                'starts_at' => now()->subDays(3),
                'ends_at' => now()->addDays(14),
            ]
        );

        Announcement::updateOrCreate(
            ['title' => 'Ramadan Special Campaign'],
            [
                'category' => 'Campaign',
                'content' => 'Celebrate Ramadan with exclusive rewards. Earn 1.5x points at Food & Beverage merchants throughout the month.',
                'terms_and_conditions' => 'Valid at participating F&B merchants only.',
                'status' => 'published',
                'is_highlighted' => true,
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
            ]
        );

        // ─── Admin Notifications (in-app inbox) ───────────────────────────
        // target_audience must be: 'all', 'member', or 'merchant'
        AdminNotification::updateOrCreate(
            ['title' => 'Welcome to PKPMS!'],
            [
                'message' => 'Thank you for joining PKPMS. Start earning points by visiting participating merchants.',
                'notification_type' => 'info',
                'target_audience' => 'member',
                'status' => 'sent',
                'sent_at' => now()->subDay(),
            ]
        );

        AdminNotification::updateOrCreate(
            ['title' => 'Double Points Weekend Active'],
            [
                'message' => 'Double points are now active this weekend. Visit any merchant to earn 2x points!',
                'notification_type' => 'promotional',
                'target_audience' => 'all',
                'status' => 'sent',
                'sent_at' => now()->subHours(2),
            ]
        );

        AdminNotification::updateOrCreate(
            ['title' => 'New Redemption Items Available'],
            [
                'message' => 'Check out the new redemption catalog — Free Coffee is now available for just 100 points!',
                'notification_type' => 'promotional',
                'target_audience' => 'member',
                'status' => 'sent',
                'sent_at' => now()->subHours(6),
            ]
        );

        AdminNotification::updateOrCreate(
            ['title' => 'Subscription Reminder'],
            [
                'message' => 'Your merchant subscription will expire in 3 days. Renew now to continue granting points.',
                'notification_type' => 'reminder',
                'target_audience' => 'merchant',
                'status' => 'sent',
                'sent_at' => now()->subHours(12),
            ]
        );

        // ─── Fundraising Campaign ─────────────────────────────────────────
        $campaign = FundraisingCampaign::updateOrCreate(
            ['campaign_name' => 'Community Relief 2026'],
            [
                'description' => 'Supporting worker welfare and community initiatives across Malaysia.',
                'target_amount' => 100000,
                'current_collected_amount' => 23500,
                'starts_at' => Carbon::now()->subDays(15),
                'ends_at' => Carbon::now()->addDays(45),
                'status' => 'active',
            ]
        );

        Donation::updateOrCreate(
            ['fundraising_campaign_id' => $campaign->id, 'member_name' => 'Ahmad Fauzi'],
            ['member_email' => 'ahmad@member.test', 'amount' => 300, 'donated_at' => now()->subDays(5)]
        );

        Donation::updateOrCreate(
            ['fundraising_campaign_id' => $campaign->id, 'member_name' => 'Nur Aisyah'],
            ['member_email' => 'aisyah@member.test', 'amount' => 200, 'donated_at' => now()->subDays(2)]
        );
    }
}
