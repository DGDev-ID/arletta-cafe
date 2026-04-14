<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Http\Controllers\Controller;
use App\Models\CafeAdmin;
use App\Models\CafeCashier;
use App\Models\MCafe;
use App\Models\MCafeTable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CafeTableController extends Controller
{
    public function index(Request $request)
    {
        $data = MCafe::paginate(10);
        return inertia('master/cafe-table/Index', [
            'data' => $data,
        ]);
    }

    public function create()
    {
        return inertia('master/cafe-table/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'address'            => 'required|string|max:500',
            'address_coordinate' => 'nullable|string|max:100',
            'description'        => 'nullable|string',
        ]);

        $validated['unique_id'] = 'cafe_' . strtolower(Str::random(20));

        MCafe::create($validated);

        return redirect()
            ->route('master.cafe.index')
            ->with('success', 'Cafe berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = MCafe::with(['cafeAdmins', 'cafeCashiers', 'tables'])->findOrFail($id);

        $admins = User::role('Admin')->select('id', 'name', 'email')->get();
        $cashiers = User::role('Cashier')->select('id', 'name', 'email')->get();

        return inertia('master/cafe-table/Edit', [
            'data'     => $data,
            'admins'   => $admins,
            'cashiers' => $cashiers,
        ]);
    }

    public function update(Request $request, $id)
    {
        $cafe = MCafe::findOrFail($id);

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'address'            => 'required|string|max:500',
            'address_coordinate' => 'nullable|string|max:100',
            'description'        => 'nullable|string',
            'admin_ids'          => 'nullable|array',
            'admin_ids.*'        => 'exists:users,id',
            'cashier_ids'        => 'nullable|array',
            'cashier_ids.*'      => 'exists:users,id',
        ]);

        $cafe->update([
            'name'               => $validated['name'],
            'address'            => $validated['address'],
            'address_coordinate' => $validated['address_coordinate'],
            'description'        => $validated['description'],
        ]);

        CafeAdmin::where('cafe_id', $cafe->id)->delete();
        foreach ($validated['admin_ids'] ?? [] as $userId) {
            CafeAdmin::create(['cafe_id' => $cafe->id, 'user_id' => $userId]);
        }

        CafeCashier::where('cafe_id', $cafe->id)->delete();
        foreach ($validated['cashier_ids'] ?? [] as $userId) {
            CafeCashier::create(['cafe_id' => $cafe->id, 'user_id' => $userId]);
        }

        return redirect()
            ->route('master.cafe.edit', $cafe->id)
            ->with('success', 'Cafe berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $data = MCafe::findOrFail($id);
        $data->delete();

        return redirect()->route('master.cafe.index')->with('success', 'Cafe berhasil dihapus.');
    }

    public function storeTable(Request $request, $cafeId)
    {
        MCafe::findOrFail($cafeId);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        MCafeTable::create([
            'cafe_id'     => $cafeId,
            'name'        => $validated['name'],
            'description' => $validated['description'],
        ]);

        return redirect()
            ->route('master.cafe.edit', $cafeId)
            ->with('success', 'Meja berhasil ditambahkan.');
    }

    public function destroyTable($cafeId, $tableId)
    {
        $table = MCafeTable::where('cafe_id', $cafeId)->findOrFail($tableId);
        $table->delete();

        return redirect()
            ->route('master.cafe.edit', $cafeId)
            ->with('success', 'Meja berhasil dihapus.');
    }
}
