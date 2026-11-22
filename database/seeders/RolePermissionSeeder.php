<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define resources and their actions
        $resources = [
            'members' => ['view', 'create', 'edit', 'delete', 'suspend', 'activate'],
            'merchants' => ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'suspend', 'activate'],
            'subscriptions' => ['view', 'create', 'edit', 'delete', 'activate'],
            'transactions' => ['view'],
            'redemptions' => ['view', 'edit'],
            'fraud_alerts' => ['view', 'edit', 'resolve'],
            'redemption-items' => ['view', 'create', 'edit', 'delete', 'activate'],
            'points-config' => ['view', 'edit'],
            'notifications' => ['view', 'create', 'edit', 'delete'],
            'announcements' => ['view', 'create', 'edit', 'delete'],
            'platform-benefits' => ['view', 'create', 'edit', 'delete'],
            'fundraising' => ['view', 'create', 'edit', 'delete', 'activate'],
            'merchant-categories' => ['view', 'create', 'edit', 'delete', 'activate'],
            'member-qr' => ['view'],
            'merchant-qr' => ['view'],
            'roles' => ['view', 'create', 'edit', 'delete'],
        ];

        // Create all permissions
        foreach ($resources as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$resource}.{$action}", 'web');
            }
        }

        // Super Admin - All permissions
        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        // Operations Admin - Most permissions except role management
        $operationsPermissions = [];
        foreach ($resources as $resource => $actions) {
            if ($resource === 'roles') continue;
            foreach ($actions as $action) {
                $operationsPermissions[] = "{$resource}.{$action}";
            }
        }
        Role::findOrCreate('Operations Admin', 'web')->syncPermissions($operationsPermissions);

        // Marketing Admin - View, create, edit for announcements, notifications, fundraising
        $marketingPermissions = [
            'members.view', 'merchants.view', 'subscriptions.view',
            'fraud_alerts.view',
            'redemption-items.view', 'merchant-categories.view',
            'notifications.view', 'notifications.create', 'notifications.edit', 'notifications.delete',
            'announcements.view', 'announcements.create', 'announcements.edit', 'announcements.delete',
            'platform-benefits.view', 'platform-benefits.create', 'platform-benefits.edit', 'platform-benefits.delete',
            'fundraising.view', 'fundraising.create', 'fundraising.edit', 'fundraising.delete', 'fundraising.activate',
        ];
        Role::findOrCreate('Marketing Admin', 'web')->syncPermissions($marketingPermissions);

        // Finance Admin - View, edit for subscriptions, transactions, points-config
        $financePermissions = [
            'members.view', 'merchants.view',
            'subscriptions.view', 'subscriptions.create', 'subscriptions.edit', 'subscriptions.delete', 'subscriptions.activate',
            'transactions.view', 'redemptions.view', 'redemptions.edit', 'redemption-items.view',
            'fraud_alerts.view', 'fraud_alerts.edit', 'fraud_alerts.resolve',
            'points-config.view', 'points-config.edit',
        ];
        Role::findOrCreate('Finance Admin', 'web')->syncPermissions($financePermissions);

        // Support Admin - View-only access to most pages
        $supportPermissions = [
            'members.view', 'merchants.view', 'subscriptions.view',
            'transactions.view', 'redemptions.view', 'redemption-items.view',
            'fraud_alerts.view',
            'merchant-categories.view',
        ];
        Role::findOrCreate('Support Admin', 'web')->syncPermissions($supportPermissions);
    }
}
