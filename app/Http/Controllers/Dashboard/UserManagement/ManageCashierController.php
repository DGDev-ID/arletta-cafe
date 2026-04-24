<?php

namespace App\Http\Controllers\Dashboard\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ManageCashierController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = User::role('Cashier')->with('cafeCashiers.cafe');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $data = $query->paginate(10)->withQueryString();

        return inertia('user-management/cashier/Index', [
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
                $query->where('name', 'ilike', "%{$q}%")
                      ->orWhere('email', 'ilike', "%{$q}%");
            })
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'Cashier'))
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
        $user->assignRole('Cashier');

        return redirect()
            ->route('user-management.cashier.index')
            ->with('success', 'User berhasil ditambahkan sebagai Cashier.');
    }

    public function revoke($id)
    {
        $user = User::findOrFail($id);
        $user->removeRole('Cashier');

        return redirect()
            ->route('user-management.cashier.index')
            ->with('success', 'Akses Cashier berhasil dicabut.');
    }
}
