<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(FraudRuleSeeder::class);
        $this->call(FraudAlertSeeder::class);
        $this->call(PlatformBenefitSeeder::class);

        // Admin accounts are created only when credentials are supplied locally.
        $seedAdmins = [
            [
                'email' => env('SEED_SUPER_ADMIN_EMAIL'),
                'password' => env('SEED_SUPER_ADMIN_PASSWORD'),
                'name' => 'Super Admin',
                'role' => 'Super Admin',
            ],
            [
                'email' => env('SEED_OPERATIONS_ADMIN_EMAIL'),
                'password' => env('SEED_OPERATIONS_ADMIN_PASSWORD'),
                'name' => 'Operations Admin',
                'role' => 'Operations Admin',
            ],
        ];

        foreach ($seedAdmins as $admin) {
            if (! empty($admin['email']) && ! empty($admin['password'])) {
                User::updateOrCreate(
                    ['email' => $admin['email']],
                    ['name' => $admin['name'], 'password' => bcrypt($admin['password'])]
                )->assignRole($admin['role']);
            }
        }

        $this->call(DemoDataSeeder::class);
        $this->call(MerchantCategorySeeder::class);
        $this->call(FundraisingCampaignSeeder::class);
        $this->call(AnnouncementSeeder::class);
        $this->call(TestUsersSeeder::class);
        $this->call(MultilangBackfillSeeder::class);
    }
}
