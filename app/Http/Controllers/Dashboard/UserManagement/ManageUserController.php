<?php

namespace App\Http\Controllers\Dashboard\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ManageUserController extends Controller
{
    public function index(Request $request)
    {
        $data = User::with('roles')->paginate(10);
        // Add roles as array for each user
        $data->getCollection()->transform(function ($user) {
            $user->roles = $user->getRoleNames();
            return $user;
        });
        return inertia('user-management/user/Index', [
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'User baru berhasil dibuat.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return back()->with('success', 'User berhasil dihapus.');
    }

}
