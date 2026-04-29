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

        return DB::transaction(function () use ($cafeId, $data, $price, $fee, $totalPrice, $menus) {
            $transaction = Transaction::create([
                'cafe_id' => $cafeId,
                'table_id' => $data['table_id'],
                'cust_name' => $data['cust_name'] ?? 'Customer',
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

        $transaction->load([
            'details.menu.menuMaterials.material',
            'details.menu.menuSemiFinishedMaterials.semiFinishedMaterial.details.material',
        ]);

        $materialRequirements = [];
        $detailMaterialMap = [];

        foreach ($transaction->details as $detail) {
            // --- Direct MenuMaterial ---
            foreach ($detail->menu->menuMaterials as $menuMaterial) {

                $material = $menuMaterial->material;
                $recipeUnitId = $menuMaterial->unit_id;
                $baseUnitId = $material->base_unit_id;
                $amountNeeded = $menuMaterial->amount * $detail->amount;

                if ($recipeUnitId !== $baseUnitId) {
                    $converter = UnitMaterialConverter::where('material_id', $material->id)
                        ->where(function ($query) use ($recipeUnitId, $baseUnitId) {
                            $query->where([
                                ['from_unit_id', $recipeUnitId],
                                ['to_unit_id', $baseUnitId]
                            ])->orWhere([
                                ['from_unit_id', $baseUnitId],
                                ['to_unit_id', $recipeUnitId]
                            ]);
                        })
                        ->first();

                    if (!$converter) {
                        throw new \Exception("Unit converter not found for material: {$material->name}");
                    }

                    $amountNeeded = ($converter->from_unit_id == $recipeUnitId)
                        ? $amountNeeded * $converter->multiplier
                        : $amountNeeded / $converter->multiplier;
                }

                $materialRequirements[$material->id] =
                    ($materialRequirements[$material->id] ?? 0) + $amountNeeded;

                $detailMaterialMap[] = [
                    'transaction_detail_id' => $detail->id,
                    'material_id' => $material->id,
                    'amount' => $amountNeeded,
                    'base_unit_id' => $baseUnitId,
                ];
            }

            // --- SemiFinishedMaterial (expand ke raw material) ---
            foreach ($detail->menu->menuSemiFinishedMaterials as $menuSfm) {
                $multiplier = (float) $menuSfm->multiplier;

                foreach ($menuSfm->semiFinishedMaterial->details as $sfmDetail) {
                    $material = $sfmDetail->material;
                    $recipeUnitId = $sfmDetail->unit_id;
                    $baseUnitId = $material->base_unit_id;
                    $amountNeeded = $sfmDetail->amount * $multiplier * $detail->amount;

                    if ($recipeUnitId !== $baseUnitId) {
                        $converter = UnitMaterialConverter::where('material_id', $material->id)
                            ->where(function ($query) use ($recipeUnitId, $baseUnitId) {
                                $query->where([
                                    ['from_unit_id', $recipeUnitId],
                                    ['to_unit_id', $baseUnitId]
                                ])->orWhere([
                                    ['from_unit_id', $baseUnitId],
                                    ['to_unit_id', $recipeUnitId]
                                ]);
                            })
                            ->first();

                        if (!$converter) {
                            throw new \Exception("Unit converter not found for material: {$material->name}");
                        }

                        $amountNeeded = ($converter->from_unit_id == $recipeUnitId)
                            ? $amountNeeded * $converter->multiplier
                            : $amountNeeded / $converter->multiplier;
                    }

                    $materialRequirements[$material->id] =
                        ($materialRequirements[$material->id] ?? 0) + $amountNeeded;

                    $detailMaterialMap[] = [
                        'transaction_detail_id' => $detail->id,
                        'material_id' => $material->id,
                        'amount' => $amountNeeded,
                        'base_unit_id' => $baseUnitId,
                    ];
                }
            }
        }

        DB::transaction(function () use ($transaction, $materialRequirements, $detailMaterialMap) {

            $materials = MMaterial::whereIn('id', array_keys($materialRequirements))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($materialRequirements as $materialId => $totalNeeded) {
                $material = $materials[$materialId];

                if ($material->stock < $totalNeeded) {
                    $transaction->update(['status' => 'failed']);

                    throw new \Exception(
                        "Insufficient stock for material: {$material->name}. Required: {$totalNeeded}, Available: {$material->stock}"
                    );
                }
            }

            // foreach ($materialRequirements as $materialId => $totalNeeded) {
            //     $materials[$materialId]->decrement('stock', $totalNeeded);
            // }

            // Calculate profit margin
            $totalMaterialCost = 0;
            foreach ($materialRequirements as $materialId => $totalNeeded) {
                $material = $materials[$materialId];
                $totalMaterialCost += $totalNeeded * (float) $material->avg_buy_price;
            }

            $transaction->profit_margin = (float) $transaction->total_price - $totalMaterialCost;
            $transaction->save();

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

        DB::transaction(function () use ($transaction) {

            // 🔒 lock transaction row
            $transaction = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->first();

            if ($transaction->status !== 'pending') {
                throw new \Exception('Transaction already processed.');
            }

            $transaction->load('details');

            foreach ($transaction->details as $detail) {

                // 🔒 lock outbound rows
                $outbounds = MaterialInboundOutbound::where('type', 'outbound')
                    ->where('transaction_detail_id', $detail->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($outbounds as $outbound) {

                    // ❗ prevent double reversal (optional but recommended)
                    $alreadyReversed = MaterialInboundOutbound::where([
                        'type' => 'inbound',
                        'transaction_detail_id' => $outbound->transaction_detail_id,
                        'material_id' => $outbound->material_id,
                        'amount' => $outbound->amount,
                    ])->exists();

                    if ($alreadyReversed) {
                        continue;
                    }

                    MaterialInboundOutbound::create([
                        'material_id' => $outbound->material_id,
                        'type' => 'inbound',
                        'amount' => $outbound->amount,
                        'base_unit_id' => $outbound->base_unit_id,
                        'transaction_detail_id' => $outbound->transaction_detail_id,
                        'inbound_buy_price' => null,
                    ]);

                    // 🔒 safe increment (atomic)
                    // MMaterial::where('id', $outbound->material_id)
                    //     ->lockForUpdate()
                    //     ->increment('stock', $outbound->amount);
                }
            }

            $transaction->update(['status' => 'failed']);
        });

        return true;
    }

    public static function makeExpense(Transaction $transaction)
    {
        DB::transaction(function () use ($transaction) {
            $trx = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->first();

            if (!$trx) {
                throw new \Exception('Transaksi tidak ditemukan.');
            }

            $currentProfit = (float) $trx->profit_margin;

            $trx->profit_margin = $currentProfit * -1;
            $trx->is_expense = 1;
            $trx->status = 'success';
            $trx->save();
        });

        return true;
    }

    /**
     * Create an expense transaction directly (consumes materials and sets negative profit_margin).
     *
     * Data shape:
     *  - cafe_id: int (MCafe id) or unique_id string
     *  - details: [ { menu_id, amount }, ... ]
     */
    public static function createExpenseTransaction(array $data)
    {
        // Resolve cafe (accept numeric id or unique_id)
        if (isset($data['cafe_id']) && is_numeric($data['cafe_id'])) {
            $cafe = MCafe::find($data['cafe_id']);
        } else {
            $cafe = MCafe::where('unique_id', $data['cafe_id'] ?? null)->first();
        }

        if (!$cafe) {
            throw new \Exception('Cafe tidak ditemukan.');
        }

        $menuIds = collect($data['details'])->pluck('menu_id')->all();
        $menus = \App\Models\MMenu::whereIn('id', $menuIds)->get();

        if ($menus->count() !== count($menuIds)) {
            throw new \Exception('Some menu items not found.');
        }

        // Calculate price
        $price = 0;
        foreach ($data['details'] as $detail) {
            $menu = $menus->where('id', $detail['menu_id'])->first();
            $price += $menu->price * $detail['amount'];
        }

        // Calculate fee
        $ppn = $cafe->ppn_fee > 0 ? ($price * ($cafe->ppn_fee / 100)) : 0;
        $paymentTypeFee = $cafe->qris_fee > 0 && ($data['payment_type'] ?? '') === 'qris' ? ($price * ($cafe->qris_fee / 100)) : 0;

        $fee = $ppn + $paymentTypeFee;
        $totalPrice = $price + $fee;

        return DB::transaction(function () use ($cafe, $data, $price, $fee, $totalPrice, $menus) {
            // Ensure we have a valid table_id (transactions.table_id is NOT NULL)
            $tableId = $data['table_id'] ?? null;
            if (!$tableId) {
                $table = $cafe->tables()->first();
                if (!$table) {
                    throw new \Exception('Cafe tidak memiliki meja. Mohon tambahkan meja terlebih dahulu.');
                }
                $tableId = $table->id;
            }

            $transaction = Transaction::create([
                'cafe_id' => $cafe->id,
                'table_id' => $tableId,
                'cust_name' => $data['cust_name'] ?? 'Pengeluaran',
                'price' => $price,
                'fee' => $fee,
                'total_price' => $totalPrice,
                'payment_type' => $data['payment_type'] ?? 'manual',
                'status' => 'success',
                'is_expense' => 1,
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

            // Build material requirements similar to pendingAction
            $transaction->load([
                'details.menu.menuMaterials.material',
                'details.menu.menuSemiFinishedMaterials.semiFinishedMaterial.details.material',
            ]);

            $materialRequirements = [];
            $detailMaterialMap = [];

            foreach ($transaction->details as $detail) {
                foreach ($detail->menu->menuMaterials as $menuMaterial) {
                    $material = $menuMaterial->material;
                    $recipeUnitId = $menuMaterial->unit_id;
                    $baseUnitId = $material->base_unit_id;
                    $amountNeeded = $menuMaterial->amount * $detail->amount;

                    if ($recipeUnitId !== $baseUnitId) {
                        $converter = UnitMaterialConverter::where('material_id', $material->id)
                            ->where(function ($query) use ($recipeUnitId, $baseUnitId) {
                                $query->where([
                                    ['from_unit_id', $recipeUnitId],
                                    ['to_unit_id', $baseUnitId]
                                ])->orWhere([
                                    ['from_unit_id', $baseUnitId],
                                    ['to_unit_id', $recipeUnitId]
                                ]);
                            })
                            ->first();

                        if (!$converter) {
                            throw new \Exception("Unit converter not found for material: {$material->name}");
                        }

                        $amountNeeded = ($converter->from_unit_id == $recipeUnitId)
                            ? $amountNeeded * $converter->multiplier
                            : $amountNeeded / $converter->multiplier;
                    }

                    $materialRequirements[$material->id] = ($materialRequirements[$material->id] ?? 0) + $amountNeeded;

                    $detailMaterialMap[] = [
                        'transaction_detail_id' => $detail->id,
                        'material_id' => $material->id,
                        'amount' => $amountNeeded,
                        'base_unit_id' => $baseUnitId,
                    ];
                }

                foreach ($detail->menu->menuSemiFinishedMaterials as $menuSfm) {
                    $multiplier = (float) $menuSfm->multiplier;

                    foreach ($menuSfm->semiFinishedMaterial->details as $sfmDetail) {
                        $material = $sfmDetail->material;
                        $recipeUnitId = $sfmDetail->unit_id;
                        $baseUnitId = $material->base_unit_id;
                        $amountNeeded = $sfmDetail->amount * $multiplier * $detail->amount;

                        if ($recipeUnitId !== $baseUnitId) {
                            $converter = UnitMaterialConverter::where('material_id', $material->id)
                                ->where(function ($query) use ($recipeUnitId, $baseUnitId) {
                                    $query->where([
                                        ['from_unit_id', $recipeUnitId],
                                        ['to_unit_id', $baseUnitId]
                                    ])->orWhere([
                                        ['from_unit_id', $baseUnitId],
                                        ['to_unit_id', $recipeUnitId]
                                    ]);
                                })
                                ->first();

                            if (!$converter) {
                                throw new \Exception("Unit converter not found for material: {$material->name}");
                            }

                            $amountNeeded = ($converter->from_unit_id == $recipeUnitId)
                                ? $amountNeeded * $converter->multiplier
                                : $amountNeeded / $converter->multiplier;
                        }

                        $materialRequirements[$material->id] = ($materialRequirements[$material->id] ?? 0) + $amountNeeded;

                        $detailMaterialMap[] = [
                            'transaction_detail_id' => $detail->id,
                            'material_id' => $material->id,
                            'amount' => $amountNeeded,
                            'base_unit_id' => $baseUnitId,
                        ];
                    }
                }
            }

            // Lock materials & check stock
            $materials = MMaterial::whereIn('id', array_keys($materialRequirements))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($materialRequirements as $materialId => $totalNeeded) {
                $material = $materials[$materialId];

                if ($material->stock < $totalNeeded) {
                    $transaction->update(['status' => 'failed']);

                    throw new \Exception("Insufficient stock for material: {$material->name}. Required: {$totalNeeded}, Available: {$material->stock}");
                }
            }

            // Create outbounds
            $totalMaterialCost = 0;
            foreach ($materialRequirements as $materialId => $totalNeeded) {
                $material = $materials[$materialId];
                $totalMaterialCost += $totalNeeded * (float) $material->avg_buy_price;
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

            $transaction->profit_margin = -1 * ((float) $transaction->total_price - $totalMaterialCost);
            $transaction->save();

            return $transaction->fresh('details');
        });
    }
}
