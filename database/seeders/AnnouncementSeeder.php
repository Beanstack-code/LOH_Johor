<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $announcements = [
            [
                'title'              => 'Double Points Weekend',
                'category'           => 'LIMITED OFFER',
                'content'            => "This weekend only, earn DOUBLE points on every purchase at all participating PKPMS merchants!\n\nWhether you're dining out, shopping for fashion or treating yourself to wellness services — every ringgit you spend earns 2× the usual points automatically.\n\nLog in to the app and check the merchant directory to find participating outlets near you. Points are credited within 24 hours of your transaction.",
                'terms_and_conditions' => "Valid Saturday and Sunday only. Double points apply to all eligible purchases at participating merchants. Cannot be combined with other promotional offers. PKPMS reserves the right to amend or withdraw this promotion at any time.",
                'starts_at'          => now()->subDays(2),
                'ends_at'            => now()->addDays(3),
                'is_highlighted'     => true,
                'status'             => 'published',
            ],
            [
                'title'              => 'New Members Welcome Bonus',
                'category'           => 'NEW',
                'content'            => "New to PKPMS? Welcome aboard! As a new member, you receive 500 bonus points credited instantly to your account upon completing your first transaction.\n\nUse your points to redeem exclusive deals from our merchant partners — from food and beverages to fashion, health and lifestyle.\n\nExplore the Redemption tab to see what's available and start enjoying your membership perks today!",
                'terms_and_conditions' => "Welcome bonus of 500 points is valid for new members only. Bonus is credited after the first eligible transaction. Offer valid for accounts registered in the current membership cycle. One bonus per member. Subject to programme terms.",
                'starts_at'          => now()->subDays(30),
                'ends_at'            => null,
                'is_highlighted'     => false,
                'status'             => 'published',
            ],
            [
                'title'              => 'Refer a Friend, Earn Together',
                'category'           => 'REFER & EARN',
                'content'            => "Did you know you can earn bonus points simply by inviting your friends to join PKPMS?\n\nFor every friend who signs up using your referral and completes their first eligible transaction, you both receive 200 bonus points each — that's a win for everyone!\n\nShare your referral code from your Member Card screen. There's no limit to how many friends you can refer, so start sharing today!",
                'terms_and_conditions' => "Referral bonus of 200 points is credited to both referrer and referee after the referee completes their first eligible transaction. Referral code must be used at the time of registration. Self-referrals are not permitted. Programme rules apply.",
                'starts_at'          => now()->subDays(15),
                'ends_at'            => null,
                'is_highlighted'     => false,
                'status'             => 'published',
            ],
        ];

        foreach ($announcements as $data) {
            Announcement::updateOrCreate(
                ['title' => $data['title']],
                $data
            );
        }

        $this->command->info('Announcements seeded successfully!');
    }
}
