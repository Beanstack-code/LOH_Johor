<?php

namespace Database\Seeders;

use App\Models\FundraisingCampaign;
use Illuminate\Database\Seeder;

class FundraisingCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = [
            [
                'campaign_name'            => 'Education for Every Child',
                'organization_name'        => 'UNICEF Malaysia',
                'description'              => 'Help provide school supplies, books and learning materials to underprivileged children across rural Malaysia.',
                'sub_description'          => 'Every ringgit reaches a child who truly needs it.',
                'impact'                   => 'RM10 funds school supplies for 1 child',
                'how_it_helps'             => [
                    'Provides stationery kits for rural students',
                    'Distributes reference books aligned to national curriculum',
                    'Supports students at risk of dropping out',
                    'Covers schoolbags and basic learning tools',
                ],
                'target_amount'            => 50000.00,
                'current_collected_amount' => 18400.00,
                'starts_at'               => now()->subDays(30),
                'ends_at'                 => now()->addDays(60),
                'status'                  => 'active',
            ],
            [
                'campaign_name'            => 'Feed Families in Need',
                'organization_name'        => 'Pertubuhan Kebajikan Food Aid',
                'description'              => 'Distribute food baskets and daily meals to low-income families and single mothers struggling to make ends meet.',
                'sub_description'          => 'We distribute food with dignity, ensuring recipients feel supported, not stigmatised.',
                'impact'                   => 'RM20 provides 5 meals to a family',
                'how_it_helps'             => [
                    'Monthly food basket delivery to B40 households',
                    'Fresh produce and dry goods included',
                    'Reaches single-mother and elderly households first',
                    'Distribution via trusted community partners',
                ],
                'target_amount'            => 30000.00,
                'current_collected_amount' => 12750.00,
                'starts_at'               => now()->subDays(15),
                'ends_at'                 => now()->addDays(75),
                'status'                  => 'active',
            ],
            [
                'campaign_name'            => 'Healthcare Support Fund',
                'organization_name'        => 'Hospital Funding Initiative',
                'description'              => 'Cover medical costs and supply essential medicines to patients who cannot afford treatment at public hospitals.',
                'sub_description'          => 'Bridging the gap for patients who need it most.',
                'impact'                   => 'RM50 covers basic medicine for 1 week',
                'how_it_helps'             => [
                    'Reimburses essential medication costs',
                    'Funds mobility aids and medical devices',
                    'Covers transport for outpatient appointments',
                    'Supports long-term chronic illness patients',
                ],
                'target_amount'            => 80000.00,
                'current_collected_amount' => 34200.00,
                'starts_at'               => now()->subDays(45),
                'ends_at'                 => now()->addDays(45),
                'status'                  => 'active',
            ],
            [
                'campaign_name'            => 'Plant Trees, Save Rivers',
                'organization_name'        => 'WWF Malaysia',
                'description'              => "Restore Malaysia's deforested lands and clean polluted rivers through tree planting and river rehabilitation programs.",
                'sub_description'          => 'Helping nature heal — one tree, one river at a time.',
                'impact'                   => 'RM30 plants 3 trees in your name',
                'how_it_helps'             => [
                    'Native tree species planted in deforested areas',
                    'River clean-up along major catchment zones',
                    'Protects habitat for endangered wildlife',
                    'Community education on sustainable practices',
                ],
                'target_amount'            => 60000.00,
                'current_collected_amount' => 27900.00,
                'starts_at'               => now()->subDays(20),
                'ends_at'                 => now()->addDays(100),
                'status'                  => 'active',
            ],
            [
                'campaign_name'            => 'Elderly Care & Dignity',
                'organization_name'        => 'Rumah Seri Kenangan',
                'description'              => 'Support senior citizens living in care homes by funding their daily necessities, medical care and recreational activities.',
                'sub_description'          => 'Improving the quality of their golden years.',
                'impact'                   => 'RM25 funds 1 day of elderly care',
                'how_it_helps'             => [
                    'Daily nutritious meals for residents',
                    'Monthly medical check-ups and physiotherapy',
                    'Recreational and mental wellness activities',
                    'Personal care items — clothing and toiletries',
                ],
                'target_amount'            => 40000.00,
                'current_collected_amount' => 15600.00,
                'starts_at'               => now()->subDays(10),
                'ends_at'                 => now()->addDays(80),
                'status'                  => 'active',
            ],
        ];

        foreach ($campaigns as $data) {
            FundraisingCampaign::updateOrCreate(
                ['campaign_name' => $data['campaign_name']],
                $data
            );
        }

        $this->command->info('Fundraising campaigns seeded successfully!');
    }
}
