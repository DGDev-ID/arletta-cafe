<?php

namespace App\Http\Controllers\Dashboard\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ManageBackofficeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = User::role('Backoffice');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $data = $query->paginate(10)->withQueryString();

        return inertia('user-management/backoffice/Index', [
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
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'Backoffice'))
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
        $user->assignRole('Backoffice');

        return redirect()
            ->route('user-management.backoffice.index')
            ->with('success', 'User berhasil ditambahkan sebagai Backoffice.');
    }

    public function revoke($id)
    {
        $user = User::findOrFail($id);
        $user->removeRole('Backoffice');

        return redirect()
            ->route('user-management.backoffice.index')
            ->with('success', 'Akses Backoffice berhasil dicabut.');
    }
}
