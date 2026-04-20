<?php

namespace App\Http\Controllers\Dashboard\UserManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RolePermissionController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        $permissions = Permission::all();
        $rolePermissions = [];
        foreach ($roles as $role) {
            $rolePermissions[$role->id] = $role->permissions->pluck('name')->toArray();
        }
        return Inertia::render('user-management/role-permission/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'rolePermissions' => $rolePermissions,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        DB::transaction(function () use ($data) {
            $role = Role::findOrFail($data['role_id']);
            $role->syncPermissions($data['permissions'] ?? []);
        });

        return back()->with('success', 'Permissions updated!');
    }
}
