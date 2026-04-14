<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['Super Admin', 'Admin', 'Cashier', 'Backoffice'];
        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::create(['name' => $role]);
        }

        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@arletta.com',
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Admin',
                'email' => 'admin@arletta.com',
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Cashier',
                'email' => 'cashier@arletta.com',
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Backoffice',
                'email' => 'backoffice@arletta.com',
                'password' => bcrypt('password'),
            ],

        ];

        foreach ($users as $user) {
            $newUser = \App\Models\User::create($user);
            $newUser->assignRole($user['name']);
        }
    }
}
