<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions
        $permissions = [
            // Master Data
            'master.cafe.view', 'master.cafe.create', 'master.cafe.update', 'master.cafe.delete',
            'master.unit.view', 'master.unit.create', 'master.unit.update', 'master.unit.delete',
            'master.material.view', 'master.material.create', 'master.material.update', 'master.material.delete',
            'master.menu-category.view', 'master.menu-category.create', 'master.menu-category.update', 'master.menu-category.delete',
            'master.menu.view', 'master.menu.create', 'master.menu.update', 'master.menu.delete',
            'master.gallery.view', 'master.gallery.create', 'master.gallery.update', 'master.gallery.delete',

            // User Management
            'user-management.admin',
            'user-management.cashier',
            'user-management.backoffice',

            // Management
            'management.unit-material-converter',
            'management.inbound-outbound-material',

            // Transaction
            'transaction.history',
            'transaction.cashier',

            // Shortcut
            'shortcut.public-link-generator',

            // Settings
            'settings',

            // Laporan
            'report',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Roles
        $superadmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $cashier = Role::firstOrCreate(['name' => 'Cashier']);
        $backoffice = Role::firstOrCreate(['name' => 'Backoffice']);

        // Assign permissions to roles
        $superadmin->givePermissionTo(Permission::all());
        $admin->givePermissionTo(Permission::all());

        $cashier->givePermissionTo([
            'master.cafe.view',
            'master.unit.view',
            'master.material.view',
            'master.menu-category.view',
            'master.menu.view',
            'master.gallery.view',
            'transaction.cashier',
            'transaction.history',
            'report',
        ]);

        $backoffice->givePermissionTo([
            'master.cafe.view', 'master.cafe.create', 'master.cafe.update',
            'master.unit.view', 'master.unit.create', 'master.unit.update',
            'master.material.view', 'master.material.create', 'master.material.update',
            'master.menu-category.view', 'master.menu-category.create', 'master.menu-category.update',
            'master.menu.view', 'master.menu.create', 'master.menu.update',
            'master.gallery.view', 'master.gallery.create', 'master.gallery.update',
            'management.unit-material-converter',
            'management.inbound-outbound-material',
            'transaction.history',
            'report',
        ]);
    }
}
