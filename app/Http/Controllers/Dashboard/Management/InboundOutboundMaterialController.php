<?php

namespace App\Http\Controllers\Dashboard\Management;

use App\Http\Controllers\Controller;
use App\Models\MaterialInboundOutbound;
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
            ->with('baseUnit')
            ->select('id', 'name', 'base_unit_id')
            ->orderBy('name')
            ->get();

        return response()->json($materials);
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
            'material_id' => 'required|exists:m_materials,id',
            'amount' => 'required|numeric|min:0.01',
            'base_unit_id' => 'required|exists:m_units,id',
            'inbound_buy_price' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {

            $material = MMaterial::where('id', $request->material_id)
                ->lockForUpdate()
                ->firstOrFail();

            $inboundUnitId = (int) $request->base_unit_id;
            $amount = (float) $request->amount;

            $convertedAmount = $this->convertToBaseUnit($material, $inboundUnitId, $amount);

            MaterialInboundOutbound::create([
                'material_id' => $material->id,
                'type' => 'inbound',
                'amount' => $request->amount,
                'base_unit_id' => $inboundUnitId,
                'inbound_buy_price' => $request->inbound_buy_price,
            ]);

            $material->stock = (float) $material->stock + $convertedAmount;

            $material->avg_buy_price = $this->calculateAvgBuyPrice($material);

            $material->save();
        });

        return redirect('/management/inbound-outbound-material')
            ->with('success', 'Data inbound berhasil ditambahkan.');
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
