<?php
use App\Models\User;
use Spatie\Permission\Models\Role;

$role = Role::firstOrCreate(['name' => 'GOD']);
$user = User::firstOrCreate(
    ['email' => 'god@arletta.com'],
    [
        'name' => 'GOD Mode',
        'password' => bcrypt('password')
    ]
);
$user->assignRole($role);

echo "\n\n=== AKUN GOD BERHASIL DIBUAT ===\n";
echo "Email: god@arletta.com\n";
echo "Password: password\n";
echo "==============================\n\n";
