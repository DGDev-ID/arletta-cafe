<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\MMaterial;
use App\Models\MUnit;
use App\Models\SemiFinishedMaterial;
use App\Models\SemiFinishedMaterialDetail;
use App\Models\UnitMaterialConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SemiFinishedMaterialController extends Controller
{
    public function index(Request $request)
    {
        $allCafe = MCafe::select('id', 'name')->get();
        $cafeId = $request->query('cafe_id');
        $search = $request->query('search');

        $query = SemiFinishedMaterial::with(['cafe', 'unit'])
            ->withCount('details');

        if ($cafeId) {
            $query->where('cafe_id', $cafeId);
        }
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $data = $query->paginate(10)->withQueryString();

        return inertia('master/semi-finished-material/Index', [
            'data'    => $data,
            'allCafe' => $allCafe,
            'search'  => $search ?? '',
        ]);
    }

    public function create()
    {
        return inertia('master/semi-finished-material/Create', [
            'cafes'      => MCafe::select('id', 'name')->get(),
            'materials'  => MMaterial::select('id', 'cafe_id', 'name', 'base_unit_id')->with('baseUnit:id,name')->get(),
            'units'      => MUnit::select('id', 'name')->get(),
            'converters' => UnitMaterialConverter::select('material_id', 'from_unit_id', 'to_unit_id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cafe_id'                 => 'required|exists:m_cafes,id',
            'name'                    => 'required|string|max:255',
            'base_unit_id'            => 'required|exists:m_units,id',
            'details'                 => 'required|array|min:1',
            'details.*.material_id'   => 'required|exists:m_materials,id',
            'details.*.amount'        => 'required|numeric|min:0.01',
            'details.*.unit_id'       => 'required|exists:m_units,id',
        ]);

        DB::transaction(function () use ($validated) {
            $sfm = SemiFinishedMaterial::create([
                'cafe_id'      => $validated['cafe_id'],
                'name'         => $validated['name'],
                'base_unit_id' => $validated['base_unit_id'],
            ]);

            foreach ($validated['details'] as $detail) {
                SemiFinishedMaterialDetail::create([
                    'semi_finished_material_id' => $sfm->id,
                    'material_id'               => $detail['material_id'],
                    'amount'                    => $detail['amount'],
                    'unit_id'                   => $detail['unit_id'],
                ]);
            }
        });

        return redirect()
            ->route('master.semi-finished-material.index')
            ->with('success', 'Semi-finished material berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = SemiFinishedMaterial::with('details')->findOrFail($id);

        return inertia('master/semi-finished-material/Edit', [
            'data'       => $data,
            'cafes'      => MCafe::select('id', 'name')->get(),
            'materials'  => MMaterial::select('id', 'cafe_id', 'name', 'base_unit_id')->with('baseUnit:id,name')->get(),
            'units'      => MUnit::select('id', 'name')->get(),
            'converters' => UnitMaterialConverter::select('material_id', 'from_unit_id', 'to_unit_id')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $sfm = SemiFinishedMaterial::findOrFail($id);

        $validated = $request->validate([
            'cafe_id'                 => 'required|exists:m_cafes,id',
            'name'                    => 'required|string|max:255',
            'base_unit_id'            => 'required|exists:m_units,id',
            'details'                 => 'required|array|min:1',
            'details.*.material_id'   => 'required|exists:m_materials,id',
            'details.*.amount'        => 'required|numeric|min:0.01',
            'details.*.unit_id'       => 'required|exists:m_units,id',
        ]);

        DB::transaction(function () use ($sfm, $validated) {
            $sfm->update([
                'cafe_id'      => $validated['cafe_id'],
                'name'         => $validated['name'],
                'base_unit_id' => $validated['base_unit_id'],
            ]);

            $sfm->details()->delete();

            foreach ($validated['details'] as $detail) {
                SemiFinishedMaterialDetail::create([
                    'semi_finished_material_id' => $sfm->id,
                    'material_id'               => $detail['material_id'],
                    'amount'                    => $detail['amount'],
                    'unit_id'                   => $detail['unit_id'],
                ]);
            }
        });

        return redirect()
            ->route('master.semi-finished-material.index')
            ->with('success', 'Semi-finished material berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $sfm = SemiFinishedMaterial::findOrFail($id);
        $sfm->delete();

        return redirect()
            ->route('master.semi-finished-material.index')
            ->with('success', 'Semi-finished material berhasil dihapus.');
    }
}
