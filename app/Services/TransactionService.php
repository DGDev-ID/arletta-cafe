<?php

namespace App\Services;

use App\Models\MaterialInboundOutbound;
use App\Models\MCafe;
use App\Models\MMaterial;
use App\Models\Transaction;
use App\Models\UnitMaterialConverter;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public static function makeTransaction(array $data)
    {
        // Validate all menu_id belong to the same cafe_id
        $menuIds = collect($data['details'])->pluck('menu_id')->all();
        $menus = \App\Models\MMenu::whereIn('id', $menuIds)->get();
        $cafe = MCafe::where('unique_id', $data['cafe_id'])->first();
        $cafeId = $cafe->id;
        if ($menus->count() !== count($menuIds)) {
            throw new \Exception('Some menu items not found.');
        }
        if ($menus->pluck('cafe_id')->unique()->count() !== 1 || $menus->first()->cafe_id != $cafeId) {
            throw new \Exception('All menu items must belong to the same cafe.');
        }

        // Calculate price
        $price = 0;
        foreach ($data['details'] as $detail) {
            $menu = $menus->where('id', $detail['menu_id'])->first();
            $price += $menu->price * $detail['amount'];
        }

        // Calculate fee
        // $ppn = $price * 0.10;
        // $paymentTypeFee = 0;
        // if ($data['payment_type'] === 'qr') {
        //     $paymentTypeFee = $price * 0.007;
        // }
        $ppn = $cafe->ppn_fee > 0 ? ($price * ($cafe->ppn_fee / 100)) : 0;
        $paymentTypeFee = $cafe->qris_fee > 0 && $data['payment_type'] === 'qris' ? ($price * ($cafe->qris_fee / 100)) : 0;

        $fee = $ppn + $paymentTypeFee;
        $totalPrice = $price + $fee;

        return DB::transaction(function () use ($data, $price, $fee, $totalPrice, $menus) {
            $transaction = Transaction::create([
                'cafe_id' => $data['cafe_id'],
                'table_id' => $data['table_id'],
                'cust_name' => $data['cust_name'] ?? null,
                'price' => $price,
                'fee' => $fee,
                'total_price' => $totalPrice,
                'payment_type' => $data['payment_type'],
                'status' => 'pending',
            ]);

            foreach ($data['details'] as $detail) {
                $menu = $menus->where('id', $detail['menu_id'])->first();
                $transaction->details()->create([
                    'menu_id' => $menu->id,
                    'amount' => $detail['amount'],
                    'price' => $menu->price * $detail['amount'],
                    'description' => $detail['description'] ?? null,
                ]);
            }

            return $transaction->fresh('details');
        });
    }
    
    public static function pendingAction(Transaction $transaction)
    {
        if ($transaction->status !== 'pending') {
            throw new \Exception('Only pending transactions can be processed.');
        }

        $transaction->load('details.menu.menuMaterials.material');

        $materialRequirements = [];
        $detailMaterialMap = [];

        foreach ($transaction->details as $detail) {
            $menu = $detail->menu;

            foreach ($menu->menuMaterials as $menuMaterial) {
                $material = $menuMaterial->material;
                $recipeUnitId = $menuMaterial->unit_id;
                $baseUnitId = $material->base_unit_id;
                $amountNeeded = $menuMaterial->amount * $detail->amount;

                if ($recipeUnitId !== $baseUnitId) {
                    $converter = UnitMaterialConverter::where('material_id', $material->id)
                        ->where(function ($query) use ($recipeUnitId, $baseUnitId) {
                            $query->where(function ($q) use ($recipeUnitId, $baseUnitId) {
                                $q->where('from_unit_id', $recipeUnitId)
                                  ->where('to_unit_id', $baseUnitId);
                            })->orWhere(function ($q) use ($recipeUnitId, $baseUnitId) {
                                $q->where('from_unit_id', $baseUnitId)
                                  ->where('to_unit_id', $recipeUnitId);
                            });
                        })
                        ->first();

                    if (!$converter) {
                        $transaction->update(['status' => 'failed']);
                        throw new \Exception("Unit converter not found for material: {$material->name}");
                    }

                    if ($converter->from_unit_id == $recipeUnitId && $converter->to_unit_id == $baseUnitId) {
                        $amountNeeded = $amountNeeded * $converter->multiplier;
                    } else {
                        $amountNeeded = $amountNeeded / $converter->multiplier;
                    }
                }

                if (!isset($materialRequirements[$material->id])) {
                    $materialRequirements[$material->id] = 0;
                }
                $materialRequirements[$material->id] += $amountNeeded;

                $detailMaterialMap[] = [
                    'transaction_detail_id' => $detail->id,
                    'material_id' => $material->id,
                    'amount' => $amountNeeded,
                    'base_unit_id' => $baseUnitId,
                ];
            }
        }

        foreach ($materialRequirements as $materialId => $totalNeeded) {
            $material = MMaterial::find($materialId);
            if ($material->stock < $totalNeeded) {
                $transaction->update(['status' => 'failed']);
                throw new \Exception("Insufficient stock for material: {$material->name}. Required: {$totalNeeded}, Available: {$material->stock}");
            }
        }

        DB::transaction(function () use ($materialRequirements, $detailMaterialMap) {
            foreach ($materialRequirements as $materialId => $totalNeeded) {
                MMaterial::where('id', $materialId)->decrement('stock', $totalNeeded);
            }

            foreach ($detailMaterialMap as $record) {
                MaterialInboundOutbound::create([
                    'material_id' => $record['material_id'],
                    'type' => 'outbound',
                    'amount' => $record['amount'],
                    'base_unit_id' => $record['base_unit_id'],
                    'transaction_detail_id' => $record['transaction_detail_id'],
                    'inbound_buy_price' => null,
                ]);
            }
        });

        return true;
    }

    public static function makeSuccess(Transaction $transaction)
    {
        if ($transaction->status !== 'pending') {
            throw new \Exception('Only pending transactions can be marked as success.');
        }

        $transaction->update(['status' => 'in_order']);

        return true;
    }

    public static function makeFailed(Transaction $transaction)
    {
        if ($transaction->status !== 'pending') {
            throw new \Exception('Only pending transactions can be marked as failed.');
        }

        $transaction->load('details');

        DB::transaction(function () use ($transaction) {
            foreach ($transaction->details as $detail) {
                $outbounds = MaterialInboundOutbound::where('type', 'outbound')
                    ->where('transaction_detail_id', $detail->id)
                    ->get();

                foreach ($outbounds as $outbound) {
                    MaterialInboundOutbound::create([
                        'material_id' => $outbound->material_id,
                        'type' => 'inbound',
                        'amount' => $outbound->amount,
                        'base_unit_id' => $outbound->base_unit_id,
                        'transaction_detail_id' => $outbound->transaction_detail_id,
                        'inbound_buy_price' => null,
                    ]);

                    MMaterial::where('id', $outbound->material_id)
                        ->increment('stock', $outbound->amount);
                }
            }

            $transaction->update(['status' => 'failed']);
        });

        return true;
    }
}