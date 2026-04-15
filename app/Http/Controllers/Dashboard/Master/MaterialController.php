<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\MMaterial;
use App\Models\MUnit;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $allCafe = MCafe::select('id', 'name')->get();
        $cafeId = $request->query('cafe_id');
        $query = MMaterial::with('cafe', 'baseUnit');
        if ($cafeId) {
            $query->where('cafe_id', $cafeId);
        }
        $data = $query->paginate(10)->withQueryString();

        return inertia('master/material/Index', [
            'data'    => $data,
            'allCafe' => $allCafe,
        ]);
    }

    public function create()
    {
        return inertia('master/material/Create', [
            'cafes' => MCafe::select('id', 'name')->get(),
            'units' => MUnit::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cafe_id'        => 'required|exists:m_cafes,id',
            'name'           => 'required|string|max:255',
            'base_unit_id'   => 'required|exists:m_units,id',
            'critical_stock' => 'nullable|numeric|min:0',
        ]);

        MMaterial::create([
            ...$validated,
            'stock'          => 0,
            'avg_buy_price'  => 0,
            'critical_stock' => $validated['critical_stock'] ?? 0,
        ]);

        return redirect()
            ->route('master.material.index')
            ->with('success', 'Material berhasil ditambahkan.');
    }

    public function show($id)
    {
        $material = MMaterial::with('cafe', 'baseUnit')->findOrFail($id);

        $logs = $material->inboundOutbounds()
            ->with('baseUnit', 'transactionDetail.menu')
            ->latest()
            ->paginate(10);

        return inertia('master/material/Show', [
            'material' => $material,
            'logs'     => $logs,
        ]);
    }

    public function edit($id)
    {
        $data = MMaterial::findOrFail($id);

        return inertia('master/material/Edit', [
            'data'  => $data,
            'cafes' => MCafe::select('id', 'name')->get(),
            'units' => MUnit::select('id', 'name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $material = MMaterial::findOrFail($id);

        $validated = $request->validate([
            'cafe_id'        => 'required|exists:m_cafes,id',
            'name'           => 'required|string|max:255',
            'base_unit_id'   => 'required|exists:m_units,id',
            'critical_stock' => 'nullable|numeric|min:0',
        ]);

        $material->update($validated);

        return redirect()
            ->route('master.material.index')
            ->with('success', 'Material berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $material = MMaterial::findOrFail($id);
        $material->delete();

        return redirect()
            ->route('master.material.index')
            ->with('success', 'Material berhasil dihapus.');
    }
}
