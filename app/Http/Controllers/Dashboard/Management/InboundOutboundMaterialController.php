<?php

namespace App\Http\Controllers\Dashboard\Management;

use App\Http\Controllers\Controller;
use App\Models\MaterialInboundOutbound;
use App\Models\MaterialVariant;
use App\Models\MCafe;
use App\Models\MMaterial;
use App\Models\MUnit;
use App\Models\UnitMaterialConverter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InboundOutboundMaterialController extends Controller
{
    public function index(Request $request)
    {
        $cafeId = $request->input('cafe_id');
        $materialId = $request->input('material_id');
        $type = $request->input('type');

        $query = MaterialInboundOutbound::with([
            'material.cafe',
            'baseUnit',
            'transactionDetail.menu',
            'variant',
        ]);

        if ($cafeId) {
            $query->whereHas('material', fn($q) => $q->where('cafe_id', $cafeId));
        }

        if ($materialId) {
            $query->where('material_id', $materialId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $data = $query->latest()->paginate(10)->withQueryString();

        $cafes = MCafe::select('id', 'name')->orderBy('name')->get();

        $materials = [];
        if ($cafeId) {
            $materials = MMaterial::where('cafe_id', $cafeId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('management/inbound-outbound-material/Index', [
            'data' => $data,
            'cafes' => $cafes,
            'materials' => $materials,
            'filters' => [
                'cafe_id' => $cafeId,
                'material_id' => $materialId,
                'type' => $type,
            ],
        ]);
    }

    public function create()
    {
        $cafes = MCafe::select('id', 'name')->orderBy('name')->get();
        $units = MUnit::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('management/inbound-outbound-material/Create', [
            'cafes' => $cafes,
            'units' => $units,
        ]);
    }

    public function getMaterialsByCafe(Request $request)
    {
        $materials = MMaterial::where('cafe_id', $request->cafe_id)
            ->with(['baseUnit', 'variants'])
            ->select('id', 'name', 'base_unit_id', 'type')
            ->orderBy('name')
            ->get();

        return response()->json($materials);
    }

    public function getVariantsByMaterial(Request $request)
    {
        $variants = MaterialVariant::where('material_id', $request->material_id)
            ->select('id', 'name', 'stock')
            ->orderBy('name')
            ->get();

        return response()->json($variants);
    }

    public function checkUnitConverter(Request $request)
    {
        $materialId = $request->material_id;
        $unitId = $request->unit_id;

        $converter = UnitMaterialConverter::where('material_id', $materialId)
            ->where(function ($q) use ($unitId) {
                $q->where('from_unit_id', $unitId)
                    ->orWhere('to_unit_id', $unitId);
            })
            ->first();

        return response()->json([
            'exists' => $converter !== null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'material_id'       => 'required|exists:m_materials,id',
            'variant_id'        => 'nullable|exists:material_variants,id',
            'amount'            => 'required|numeric|min:0.01',
            'base_unit_id'      => 'required|exists:m_units,id',
            'inbound_buy_price' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {

            $material = MMaterial::where('id', $request->material_id)
                ->lockForUpdate()
                ->firstOrFail();

            $inboundUnitId = (int) $request->base_unit_id;

            MaterialInboundOutbound::create([
                'material_id'       => $material->id,
                'variant_id'        => $request->variant_id ?: null,
                'type'              => 'inbound',
                'amount'            => $request->amount,
                'base_unit_id'      => $inboundUnitId,
                'inbound_buy_price' => $request->inbound_buy_price,
            ]);

            // avg_buy_price hanya di-update untuk parent material (bukan variant)
            if (!$request->variant_id) {
                $material->avg_buy_price = $this->calculateAvgBuyPrice($material);
                $material->save();
            }
        });

        return redirect('/management/inbound-outbound-material')
            ->with('success', 'Data inbound berhasil ditambahkan.');
    }

    public function createOutbound()
    {
        $cafes = MCafe::select('id', 'name')->orderBy('name')->get();
        $units = MUnit::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('management/inbound-outbound-material/CreateOutbound', [
            'cafes' => $cafes,
            'units' => $units,
        ]);
    }

    public function storeOutbound(Request $request)
    {
        $request->validate([
            'material_id'  => 'required|exists:m_materials,id',
            'variant_id'   => 'nullable|exists:material_variants,id',
            'amount'       => 'required|numeric|min:0.01',
            'base_unit_id' => 'required|exists:m_units,id',
            'description'  => 'required|string',
        ]);

        DB::transaction(function () use ($request) {

            $material = MMaterial::where('id', $request->material_id)
                ->lockForUpdate()
                ->firstOrFail();

            $outboundUnitId = (int) $request->base_unit_id;

            MaterialInboundOutbound::create([
                'material_id'  => $material->id,
                'variant_id'   => $request->variant_id ?: null,
                'type'         => 'outbound',
                'amount'       => $request->amount,
                'base_unit_id' => $outboundUnitId,
                'description'  => 'spoil - ' . $request->description,
            ]);
        });

        return redirect('/management/inbound-outbound-material')
            ->with('success', 'Data outbound berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $inboundOutbound = MaterialInboundOutbound::with('material.cafe', 'variant')->findOrFail($id);

        if ($inboundOutbound->transaction_detail_id !== null) {
            return redirect('/management/inbound-outbound-material')
                ->with('error', 'Data yang dihasilkan secara otomatis tidak dapat diedit.');
        }

        $cafes = MCafe::select('id', 'name')->orderBy('name')->get();
        $units = MUnit::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('management/inbound-outbound-material/Edit', [
            'cafes' => $cafes,
            'units' => $units,
            'inboundOutbound' => $inboundOutbound,
        ]);
    }

    public function update(Request $request, $id)
    {
        $inboundOutbound = MaterialInboundOutbound::findOrFail($id);

        if ($inboundOutbound->transaction_detail_id !== null) {
            return redirect('/management/inbound-outbound-material')
                ->with('error', 'Data yang dihasilkan secara otomatis tidak dapat diedit.');
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'base_unit_id' => 'required|exists:m_units,id',
            'inbound_buy_price' => $inboundOutbound->type === 'inbound' ? 'required|numeric|min:0' : 'nullable',
            'description' => $inboundOutbound->type === 'outbound' ? 'required|string' : 'nullable',
        ]);

        DB::transaction(function () use ($request, $inboundOutbound) {
            $material = MMaterial::where('id', $inboundOutbound->material_id)
                ->lockForUpdate()
                ->firstOrFail();

            $variant = null;
            if ($inboundOutbound->variant_id) {
                $variant = MaterialVariant::where('id', $inboundOutbound->variant_id)->lockForUpdate()->firstOrFail();
            }

            $oldAmountConverted = $this->convertToBaseUnit($material, $inboundOutbound->base_unit_id, $inboundOutbound->amount);
            
            // Revert old stock
            if ($variant) {
                if ($inboundOutbound->type === 'inbound') {
                    $variant->stock = (float)$variant->stock - $oldAmountConverted;
                } else {
                    $variant->stock = (float)$variant->stock + $oldAmountConverted;
                }
            } else {
                if ($inboundOutbound->type === 'inbound') {
                    $material->stock = (float)$material->stock - $oldAmountConverted;
                } else {
                    $material->stock = (float)$material->stock + $oldAmountConverted;
                }
            }

            $newAmountConverted = $this->convertToBaseUnit($material, $request->base_unit_id, $request->amount);

            if ($inboundOutbound->type === 'inbound') {
                $inboundOutbound->closing_stock = $inboundOutbound->opening_stock + $newAmountConverted;
            } else {
                $inboundOutbound->closing_stock = $inboundOutbound->opening_stock - $newAmountConverted;
            }

            $inboundOutbound->amount = $request->amount;
            $inboundOutbound->base_unit_id = $request->base_unit_id;

            if ($inboundOutbound->type === 'inbound') {
                $inboundOutbound->inbound_buy_price = $request->inbound_buy_price;
            } else {
                $inboundOutbound->description = str_starts_with($request->description, 'spoil - ') ? $request->description : "spoil - " . $request->description;
            }

            $inboundOutbound->save();

            // Apply new stock
            if ($variant) {
                if ($inboundOutbound->type === 'inbound') {
                    $variant->stock = (float)$variant->stock + $newAmountConverted;
                } else {
                    $variant->stock = (float)$variant->stock - $newAmountConverted;
                }
                $variant->save();
            } else {
                if ($inboundOutbound->type === 'inbound') {
                    $material->stock = (float)$material->stock + $newAmountConverted;
                } else {
                    $material->stock = (float)$material->stock - $newAmountConverted;
                }
                
                if ($inboundOutbound->type === 'inbound') {
                    $material->avg_buy_price = $this->calculateAvgBuyPrice($material);
                }
                $material->save();
            }
        });

        return redirect('/management/inbound-outbound-material')
            ->with('success', 'Data berhasil diperbarui.');
    }

    private function convertToBaseUnit(MMaterial $material, int $inboundUnitId, float $amount): float
    {
        if ($inboundUnitId === (int) $material->base_unit_id) {
            return $amount;
        }

        // Try to find converter where from_unit_id = inboundUnitId AND to_unit_id = material.base_unit_id
        $converter = UnitMaterialConverter::where('material_id', $material->id)
            ->where('from_unit_id', $inboundUnitId)
            ->where('to_unit_id', $material->base_unit_id)
            ->first();

        if ($converter) {
            return $amount * (float) $converter->multiplier;
        }

        // Try reverse: from_unit_id = material.base_unit_id AND to_unit_id = inboundUnitId
        $converter = UnitMaterialConverter::where('material_id', $material->id)
            ->where('from_unit_id', $material->base_unit_id)
            ->where('to_unit_id', $inboundUnitId)
            ->first();

        if ($converter) {
            return $amount / (float) $converter->multiplier;
        }

        // Fallback: try any converter with the inbound unit
        $converter = UnitMaterialConverter::where('material_id', $material->id)
            ->where('from_unit_id', $inboundUnitId)
            ->first();

        if ($converter) {
            return $amount * (float) $converter->multiplier;
        }

        $converter = UnitMaterialConverter::where('material_id', $material->id)
            ->where('to_unit_id', $inboundUnitId)
            ->first();

        if ($converter) {
            return $amount / (float) $converter->multiplier;
        }

        return $amount;
    }

    private function calculateAvgBuyPrice(MMaterial $material): float
    {
        $inbounds = MaterialInboundOutbound::where('material_id', $material->id)
            ->where('type', 'inbound')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->get();

        if ($inbounds->isEmpty()) {
            return (float) $material->avg_buy_price;
        }

        $totalPrice = 0;
        $count = $inbounds->count();

        foreach ($inbounds as $inbound) {
            $inboundUnitId = (int) $inbound->base_unit_id;
            $buyPrice = (float) $inbound->inbound_buy_price;
            $inboundAmount = (float) $inbound->amount;

            // Calculate per-base-unit price
            // First convert the amount to material's base unit
            $convertedAmount = $this->convertToBaseUnit($material, $inboundUnitId, $inboundAmount);

            // Price per material base unit = total buy price / converted amount
            if ($convertedAmount > 0) {
                $pricePerBaseUnit = $buyPrice / $convertedAmount;
            } else {
                $pricePerBaseUnit = $buyPrice;
            }

            $totalPrice += $pricePerBaseUnit;
        }

        return $totalPrice / $count;
    }
}
