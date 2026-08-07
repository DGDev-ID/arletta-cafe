<?php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$god = Role::findByName('GOD');
$god->syncPermissions(Permission::all());
echo 'Permissions synced to GOD role.';
