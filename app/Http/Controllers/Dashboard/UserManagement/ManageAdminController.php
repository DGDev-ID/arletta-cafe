<?php

namespace App\Http\Controllers\Dashboard\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ManageAdminController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = User::role('Admin')->with('cafeAdmins.cafe');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $data = $query->paginate(10)->withQueryString();

        return inertia('user-management/admin/Index', [
            'data'   => $data,
            'search' => $search ?? '',
        ]);
    }

    public function searchUsers(Request $request)
    {
        $q = $request->query('q');
        if (!$q || strlen($q) < 2) {
            return response()->json([]);
        }

        $users = User::where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
            })
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'Admin'))
            ->select('id', 'name', 'email')
            ->limit(10)
            ->get();

        return response()->json($users);
    }

    public function assign(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $user->assignRole('Admin');

        return redirect()
            ->route('user-management.admin.index')
            ->with('success', 'User berhasil ditambahkan sebagai Admin.');
    }

    public function revoke($id)
    {
        $user = User::findOrFail($id);
        $user->removeRole('Admin');

        return redirect()
            ->route('user-management.admin.index')
            ->with('success', 'Akses Admin berhasil dicabut.');
    }
}
