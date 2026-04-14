<?php

namespace App\Http\Controllers\Dashboard\Management;

use App\Http\Controllers\Controller;
use App\Models\MMaterial;
use App\Models\MUnit;
use App\Models\UnitMaterialConverter;
use Illuminate\Http\Request;

class UnitMaterialConverterController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = MMaterial::with('cafe', 'baseUnit')
            ->withCount('converters');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('cafe', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $data = $query->paginate(10)->withQueryString();

        return inertia('management/unit-material-converter/Index', [
            'data'   => $data,
            'search' => $search ?? '',
        ]);
    }

    public function show($id)
    {
        $material = MMaterial::with('cafe', 'baseUnit')->findOrFail($id);

        $converters = UnitMaterialConverter::where('material_id', $id)
            ->with('fromUnit', 'toUnit')
            ->get();

        $units = MUnit::select('id', 'name')->get();

        return inertia('management/unit-material-converter/Show', [
            'material'   => $material,
            'converters' => $converters,
            'units'      => $units,
        ]);
    }

    public function store(Request $request, $materialId)
    {
        $material = MMaterial::findOrFail($materialId);

        $validated = $request->validate([
            'from_unit_id' => 'required|exists:m_units,id',
            'to_unit_id'   => 'required|exists:m_units,id|different:from_unit_id',
            'multiplier'   => 'required|numeric|min:0.0001',
        ]);

        UnitMaterialConverter::create([
            'material_id'  => $material->id,
            'from_unit_id' => $validated['from_unit_id'],
            'to_unit_id'   => $validated['to_unit_id'],
            'multiplier'   => $validated['multiplier'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Data konversi berhasil ditambahkan.');
    }

    public function update(Request $request, $materialId, $converterId)
    {
        $converter = UnitMaterialConverter::where('material_id', $materialId)
            ->findOrFail($converterId);

        $validated = $request->validate([
            'multiplier' => 'required|numeric|min:0.0001',
        ]);

        $converter->update($validated);

        return redirect()
            ->back()
            ->with('success', 'Data konversi berhasil diperbarui.');
    }

    public function destroy($materialId, $converterId)
    {
        $converter = UnitMaterialConverter::where('material_id', $materialId)
            ->findOrFail($converterId);

        $converter->delete();

        return redirect()
            ->back()
            ->with('success', 'Data konversi berhasil dihapus.');
    }
}
