<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddExpensePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permission
        $perm = Permission::firstOrCreate(['name' => 'management.expense']);

        // Assign to common roles if present
        $roles = ['Super Admin', 'Admin', 'Backoffice'];

        foreach ($roles as $r) {
            $role = Role::where('name', $r)->first();
            if ($role) {
                $role->givePermissionTo($perm);
            }
        }
    }
}
