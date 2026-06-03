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
        $search = $request->query('search');
        $stockStatus = $request->query('stock_status');
        
        $query = MMaterial::with('cafe', 'baseUnit');
        if ($cafeId) {
            $query->where('cafe_id', $cafeId);
        }
        if ($search) {
            $query->where('name', 'ilike', "%{$search}%");
        }
        if ($stockStatus === 'empty') {
            $query->where('stock', '<=', 0);
        }
        
        $data = $query->paginate(10)->withQueryString();

        return inertia('master/material/Index', [
            'data'         => $data,
            'allCafe'      => $allCafe,
            'search'       => $search ?? '',
            'cafe_id'      => $cafeId ?? '',
            'stock_status' => $stockStatus ?? '',
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
            'type'           => 'required|in:normal,selectable',
            'base_unit_id'   => 'required|exists:m_units,id',
            'critical_stock' => 'nullable|numeric|min:0',
            'variants'       => 'nullable|array',
            'variants.*.id'  => 'nullable|exists:material_variants,id',
            'variants.*.name'=> 'required_if:type,selectable|string|max:255',
            'variants.*.minimum_stock'=> 'nullable|numeric|min:0',
        ]);

        $material = MMaterial::create([
            'cafe_id'        => $validated['cafe_id'],
            'name'           => $validated['name'],
            'type'           => $validated['type'],
            'base_unit_id'   => $validated['base_unit_id'],
            'stock'          => 0,
            'avg_buy_price'  => 0,
            'critical_stock' => $validated['critical_stock'] ?? 0,
        ]);

        if ($validated['type'] === 'selectable' && !empty($validated['variants'])) {
            foreach ($validated['variants'] as $variantData) {
                $material->variants()->create([
                    'name'          => $variantData['name'],
                    'stock'         => 0, // stok dikelola via inbound/outbound
                    'minimum_stock' => $variantData['minimum_stock'] ?? 0,
                ]);
            }
        }

        return redirect()
            ->route('master.material.index')
            ->with('success', 'Material berhasil ditambahkan.');
    }

    public function show($id)
    {
        $material = MMaterial::with('cafe', 'baseUnit')->findOrFail($id);

        $logs = $material->inboundOutbounds()
            ->with('baseUnit', 'transactionDetail.menu', 'variant')
            ->orderByDesc('id')
            ->paginate(10);

        return inertia('master/material/Show', [
            'material' => $material,
            'logs'     => $logs,
        ]);
    }

    public function edit($id)
    {
        $data = MMaterial::with('variants')->findOrFail($id);

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
            'type'           => 'required|in:normal,selectable',
            'base_unit_id'   => 'required|exists:m_units,id',
            'critical_stock' => 'nullable|numeric|min:0',
            'variants'       => 'nullable|array',
            'variants.*.id'  => 'nullable|exists:material_variants,id',
            'variants.*.name'=> 'required_if:type,selectable|string|max:255',
            'variants.*.minimum_stock'=> 'nullable|numeric|min:0',
        ]);

        $material->update([
            'cafe_id'        => $validated['cafe_id'],
            'name'           => $validated['name'],
            'type'           => $validated['type'],
            'base_unit_id'   => $validated['base_unit_id'],
            'critical_stock' => $validated['critical_stock'] ?? 0,
        ]);

        if ($validated['type'] === 'selectable') {
            $existingVariantIds = [];
            if (!empty($validated['variants'])) {
                foreach ($validated['variants'] as $variantData) {
                    if (!empty($variantData['id'])) {
                        $variant = $material->variants()->find($variantData['id']);
                        if ($variant) {
                            $variant->update([
                                'name'          => $variantData['name'],
                                // stock TIDAK diubah di sini — dikelola via inbound/outbound
                                'minimum_stock' => $variantData['minimum_stock'] ?? 0,
                            ]);
                            $existingVariantIds[] = $variant->id;
                        }
                    } else {
                        $newVariant = $material->variants()->create([
                            'name'          => $variantData['name'],
                            'stock'         => 0, // stok dikelola via inbound/outbound
                            'minimum_stock' => $variantData['minimum_stock'] ?? 0,
                        ]);
                        $existingVariantIds[] = $newVariant->id;
                    }
                }
            }
            // Delete variants that were removed
            $material->variants()->whereNotIn('id', $existingVariantIds)->delete();
        } else {
            // If type changed back to normal, delete all variants
            $material->variants()->delete();
        }

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

    public function outOfStock($id) {
        MMaterial::setOutOfStock($id);

        return back()
            ->with('success', 'Material berhasil diatur sebagai habis.');
    }
}
