<?php

namespace Database\Seeders;

use App\Models\PlatformBenefit;
use Illuminate\Database\Seeder;

class PlatformBenefitSeeder extends Seeder
{
    public function run(): void
    {
        $benefits = [
            [
                'title' => 'Reward Every Transaction',
                'content' => 'Members earn points from eligible purchases and can redeem vouchers, gifts, and special campaign rewards directly in the app.',
                'audience' => 'member',
                'is_active' => true,
            ],
            [
                'title' => 'Business Growth for Merchants',
                'content' => 'Merchants can increase repeat visits, run targeted campaigns, and track customer engagement through a points-driven ecosystem.',
                'audience' => 'merchant',
                'is_active' => true,
            ],
            [
                'title' => 'Secure and Seamless Experience',
                'content' => 'The platform supports secure login, managed notifications, and transparent activity records for a reliable loyalty experience.',
                'audience' => 'both',
                'is_active' => true,
            ],
        ];

        foreach ($benefits as $benefit) {
            PlatformBenefit::updateOrCreate(
                ['title' => $benefit['title'], 'audience' => $benefit['audience']],
                $benefit
            );
        }
    }
}

