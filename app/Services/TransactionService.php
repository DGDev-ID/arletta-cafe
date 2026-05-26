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

        // Validate promo code if provided
        $promoId = null;
        $discountAmount = 0;
        $promo = null;
        if (!empty($data['promo_code'])) {
            $promo = \App\Models\CafePromo::where('promo_code', $data['promo_code'])
                ->where('cafe_id', $cafeId)
                ->where('status', true)
                ->first();

            if (!$promo) {
                throw new \Exception('Invalid or inactive promo code.');
            }
            $promoId = $promo->id;
        }

        // Calculate price
        $price = 0;
        foreach ($data['details'] as $detail) {
            $menu = $menus->where('id', $detail['menu_id'])->first();
            $price += $menu->price * $detail['amount'];
        }

        // Calculate discount
        if ($promoId && $promo) {
            if ($promo->type === 'discount_percent') {
                $discountAmount = $price * ($promo->value / 100);
            } else if ($promo->type === 'discount_amount') {
                $discountAmount = $promo->value;
            }
            
            // Ensure discount doesn't exceed price
            if ($discountAmount > $price) {
                $discountAmount = $price;
            }
        }
        $priceAfterDiscount = $price - $discountAmount;

        // Calculate fee
        // $ppn = $price * 0.10;
        // $paymentTypeFee = 0;
        // if ($data['payment_type'] === 'qr') {
        //     $paymentTypeFee = $price * 0.007;
        // }
        $ppn = $cafe->ppn_fee > 0 ? ($priceAfterDiscount * ($cafe->ppn_fee / 100)) : 0;
        $paymentTypeFee = $cafe->qris_fee > 0 && $data['payment_type'] === 'qris' ? ($priceAfterDiscount * ($cafe->qris_fee / 100)) : 0;

        $fee = $ppn + $paymentTypeFee;
        $totalPrice = $priceAfterDiscount + $fee;

        return DB::transaction(function () use ($cafeId, $data, $price, $fee, $totalPrice, $menus, $promoId) {
            $transaction = Transaction::create([
                'cafe_id' => $cafeId,
                'table_id' => $data['table_id'] ?? null,
                'cust_name' => $data['cust_name'] ?? 'Customer',
                'price' => $price,
                'fee' => $fee,
                'total_price' => $totalPrice,
                'payment_type' => $data['payment_type'],
                'status' => 'pending',
                'promo_id' => $promoId,
            ]);

            foreach ($data['details'] as $detail) {
                $menu = $menus->where('id', $detail['menu_id'])->first();
                $transaction->details()->create([
                    'menu_id' => $menu->id,
                    'amount' => $detail['amount'],
                    'price' => $menu->price * $detail['amount'],
                    'description' => $detail['description'] ?? null,
                    'selected_variants' => $detail['selected_variants'] ?? null,
                ]);
            }

            return $transaction->fresh('details');
        });
    }

    // Di method pendingAction, ganti seluruh bagian loop materialRequirements
// dan tambahkan support variant:

public static function pendingAction(Transaction $transaction)
{
    if ($transaction->status !== 'pending') {
        throw new \Exception('Only pending transactions can be processed.');
    }

    $transaction->load([
        'details.menu.menuMaterials.material.variants',
        'details.menu.menuSemiFinishedMaterials.semiFinishedMaterial.details.material',
    ]);

    // selected_variants dari payload disimpan di transaction_details
    // Pastikan kolom selected_variants ada di transaction_details (lihat catatan migration di bawah)

    $materialRequirements = [];
    $variantRequirements  = []; // variant_id -> total amount
    $detailMaterialMap    = [];

    foreach ($transaction->details as $detail) {
        // Parse selected_variants dari detail
        $selectedVariants = collect($detail->selected_variants ?? [])
            ->keyBy('material_id'); // material_id -> variant_id

        foreach ($detail->menu->menuMaterials as $menuMaterial) {
            $material     = $menuMaterial->material;
            $recipeUnitId = $menuMaterial->unit_id;
            $baseUnitId   = $material->base_unit_id;
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
                    })->first();

                if (!$converter) {
                    throw new \Exception("Unit converter not found for material: {$material->name}");
                }

                $amountNeeded = ($converter->from_unit_id == $recipeUnitId)
                    ? $amountNeeded * $converter->multiplier
                    : $amountNeeded / $converter->multiplier;
            }

            if ($material->type === 'selectable') {
                // Kurangi stok variant, bukan parent
                $variantData = $selectedVariants->get($material->id);
                if (!$variantData) {
                    throw new \Exception("Variant belum dipilih untuk material: {$material->name}");
                }
                $variantId = $variantData['variant_id'];
                $variantRequirements[$variantId] = ($variantRequirements[$variantId] ?? 0) + $amountNeeded;

                $detailMaterialMap[] = [
                    'transaction_detail_id' => $detail->id,
                    'material_id'           => $material->id,
                    'variant_id'            => $variantId,
                    'amount'                => $amountNeeded,
                    'base_unit_id'          => $baseUnitId,
                    'is_variant'            => true,
                ];
            } else {
                $materialRequirements[$material->id] =
                    ($materialRequirements[$material->id] ?? 0) + $amountNeeded;

                $detailMaterialMap[] = [
                    'transaction_detail_id' => $detail->id,
                    'material_id'           => $material->id,
                    'variant_id'            => null,
                    'amount'                => $amountNeeded,
                    'base_unit_id'          => $baseUnitId,
                    'is_variant'            => false,
                ];
            }
        }

        // SemiFinishedMaterial — asumsi tidak selectable (bisa dikembangkan)
        foreach ($detail->menu->menuSemiFinishedMaterials as $menuSfm) {
            $multiplier = (float) $menuSfm->multiplier;
            foreach ($menuSfm->semiFinishedMaterial->details as $sfmDetail) {
                $material     = $sfmDetail->material;
                $recipeUnitId = $sfmDetail->unit_id;
                $baseUnitId   = $material->base_unit_id;
                $amountNeeded = $sfmDetail->amount * $multiplier * $detail->amount;

                if ($recipeUnitId !== $baseUnitId) {
                    $converter = UnitMaterialConverter::where('material_id', $material->id)
                        ->where(function ($query) use ($recipeUnitId, $baseUnitId) {
                            $query->where([['from_unit_id', $recipeUnitId], ['to_unit_id', $baseUnitId]])
                                  ->orWhere([['from_unit_id', $baseUnitId], ['to_unit_id', $recipeUnitId]]);
                        })->first();

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
                    'material_id'           => $material->id,
                    'variant_id'            => null,
                    'amount'                => $amountNeeded,
                    'base_unit_id'          => $baseUnitId,
                    'is_variant'            => false,
                ];
            }
        }
    }

    DB::transaction(function () use ($transaction, $materialRequirements, $variantRequirements, $detailMaterialMap) {

        // Validasi stok material normal
        $materials = MMaterial::whereIn('id', array_keys($materialRequirements))
            ->lockForUpdate()->get()->keyBy('id');

        foreach ($materialRequirements as $materialId => $totalNeeded) {
            $material = $materials[$materialId];
            if ($material->stock < $totalNeeded) {
                $transaction->update(['status' => 'failed']);
                throw new \Exception(
                    "Insufficient stock for material: {$material->name}. Required: {$totalNeeded}, Available: {$material->stock}"
                );
            }
        }

        // Validasi stok variant
        $variants = \App\Models\MaterialVariant::whereIn('id', array_keys($variantRequirements))
            ->lockForUpdate()->get()->keyBy('id');

        foreach ($variantRequirements as $variantId => $totalNeeded) {
            $variant = $variants[$variantId];
            if ($variant->stock < $totalNeeded) {
                $transaction->update(['status' => 'failed']);
                throw new \Exception(
                    "Insufficient stock for variant: {$variant->name}. Required: {$totalNeeded}, Available: {$variant->stock}"
                );
            }
        }

        // Hitung profit margin
        $totalMaterialCost = 0;
        foreach ($materialRequirements as $materialId => $totalNeeded) {
            $material = $materials[$materialId];
            $totalMaterialCost += $totalNeeded * (float) $material->avg_buy_price;
        }
        // Untuk variant, gunakan avg_buy_price parent
        foreach ($variantRequirements as $variantId => $totalNeeded) {
            $variant  = $variants[$variantId];
            $material = $materials[$variant->material_id] ?? MMaterial::find($variant->material_id);
            if ($material) {
                $totalMaterialCost += $totalNeeded * (float) $material->avg_buy_price;
            }
        }

        $transaction->profit_margin = (float) $transaction->total_price - $totalMaterialCost;
        $transaction->save();

        // Catat outbound; MaterialInboundOutbound boot handler akan mengupdate stok
        foreach ($detailMaterialMap as $record) {
            MaterialInboundOutbound::create([
                'material_id'           => $record['material_id'],
                'type'                  => 'outbound',
                'amount'                => $record['amount'],
                'base_unit_id'          => $record['base_unit_id'],
                'transaction_detail_id' => $record['transaction_detail_id'],
                'inbound_buy_price'     => null,
                'variant_id'            => $record['variant_id'] ?? null,
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
        // if ($transaction->status !== 'pending') {
        //     throw new \Exception('Only pending transactions can be marked as failed.');
        // }

        DB::transaction(function () use ($transaction) {

            // 🔒 lock transaction row
            $transaction = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->first();

            // if ($transaction->status !== 'pending') {
            //     throw new \Exception('Transaction already processed.');
            // }

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
}
