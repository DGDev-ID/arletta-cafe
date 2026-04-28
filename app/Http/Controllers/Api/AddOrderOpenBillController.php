<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\MCafe;
use App\Models\MCafeTable;
use App\Models\MaterialInboundOutbound;
use App\Models\MMaterial;
use App\Models\MMenu;
use App\Models\Transaction;
use App\Services\MenuAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddOrderOpenBillController extends ApiBaseController
{
    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'cafe_table_id' => ['required', 'integer', 'exists:m_cafe_tables,id'],
                'orders' => ['required', 'array', 'min:1'],
                'orders.*.menu_id' => ['required', 'integer', 'exists:m_menus,id'],
                'orders.*.amount' => ['required', 'integer', 'min:1'],
            ]);

            $table = MCafeTable::find($validated['cafe_table_id']);

            if (!$table) {
                return $this->clientError('Table not found');
            }

            if ((int)$table->is_open_bill !== 1) {
                return $this->clientError('Meja ini tidak mendukung open bill');
            }

            $transaction = Transaction::where('table_id', $table->id)
                ->where('status', 'pending')
                ->where('is_open_bill', 1)
                ->orderBy('id', 'desc')
                ->first();

            if (!$transaction) {
                return $this->clientError('Tidak ada open bill pending di meja ini');
            }

            $orders = $validated['orders'];

            // Aggregate orders per menu_id
            $aggregated = [];
            foreach ($orders as $o) {
                $aggregated[$o['menu_id']] = ($aggregated[$o['menu_id']] ?? 0) + (int)$o['amount'];
            }

            $menuIds = array_keys($aggregated);

            $menus = MMenu::with(['menuMaterials.material', 'menuSemiFinishedMaterials.semiFinishedMaterial.details.material'])
                ->whereIn('id', $menuIds)
                ->get()
                ->keyBy('id');

            if ($menus->isEmpty()) {
                return $this->clientError('Menu tidak ditemukan');
            }

            // Ensure menus belong to the same cafe as transaction
            if ($menus->pluck('cafe_id')->unique()->count() !== 1 || $menus->first()->cafe_id != $transaction->cafe_id) {
                return $this->clientError('Semua menu harus berasal dari cafe yang sama dengan transaksi');
            }

            // Check availability only for the new orders (current stock already accounts for existing outbounds)
            $service = new MenuAvailabilityService();

            $items = [];
            foreach ($aggregated as $mid => $qty) {
                $items[] = ['menu' => $menus[$mid], 'quantity' => $qty];
            }

            $unavailable = $service->checkAvailableMenus($items);
            if (!empty($unavailable)) {
                return $this->clientError('Beberapa menu tidak tersedia: ' . implode(', ', $unavailable));
            }

            // All good — create details and material outbounds inside DB transaction
            $resultTransaction = DB::transaction(function () use ($transaction, $orders, $menus, $table) {
                $priceAdd = 0;
                $newDetails = [];

                foreach ($orders as $o) {
                    $menu = $menus[$o['menu_id']];
                    $detail = $transaction->details()->create([
                        'menu_id' => $menu->id,
                        'amount' => $o['amount'],
                        'price' => $menu->price * $o['amount'],
                        'description' => null,
                    ]);
                    $newDetails[] = $detail;
                    $priceAdd += $menu->price * $o['amount'];
                }

                // Build material requirements for newly added details
                $materialRequirements = [];
                $detailMaterialMap = [];

                foreach ($newDetails as $detail) {
                    $menu = $menus[$detail->menu_id];

                    // Direct menu materials
                    foreach ($menu->menuMaterials as $menuMaterial) {
                        $material = $menuMaterial->material;
                        $recipeUnitId = $menuMaterial->unit_id;
                        $baseUnitId = $material->base_unit_id;
                        $amountNeeded = $menuMaterial->amount * $detail->amount;

                        if ($recipeUnitId !== $baseUnitId) {
                            $converter = \App\Models\UnitMaterialConverter::where('material_id', $material->id)
                                ->where(function ($query) use ($recipeUnitId, $baseUnitId) {
                                    $query->where([
                                        ['from_unit_id', $recipeUnitId],
                                        ['to_unit_id', $baseUnitId]
                                    ])
                                    ->orWhere([
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

                    // Semi finished materials
                    foreach ($menu->menuSemiFinishedMaterials as $menuSfm) {
                        $multiplier = (float) $menuSfm->multiplier;

                        foreach ($menuSfm->semiFinishedMaterial->details as $sfmDetail) {
                            $material = $sfmDetail->material;
                            $recipeUnitId = $sfmDetail->unit_id;
                            $baseUnitId = $material->base_unit_id;
                            $amountNeeded = $sfmDetail->amount * $multiplier * $detail->amount;

                            if ($recipeUnitId !== $baseUnitId) {
                                $converter = \App\Models\UnitMaterialConverter::where('material_id', $material->id)
                                    ->where(function ($query) use ($recipeUnitId, $baseUnitId) {
                                        $query->where([
                                            ['from_unit_id', $recipeUnitId],
                                            ['to_unit_id', $baseUnitId]
                                        ])
                                        ->orWhere([
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

                // Lock materials and ensure stock is enough for the newly required amounts
                if (!empty($materialRequirements)) {
                    $materials = MMaterial::whereIn('id', array_keys($materialRequirements))
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    foreach ($materialRequirements as $matId => $need) {
                        $mat = $materials[$matId] ?? null;
                        if (!$mat || (float) $mat->stock < $need) {
                            throw new \Exception("Insufficient stock for material id: {$matId}");
                        }
                    }
                }

                // Create outbound records (this will update stock in MaterialInboundOutbound::booted)
                foreach ($detailMaterialMap as $rec) {
                    MaterialInboundOutbound::create([
                        'material_id' => $rec['material_id'],
                        'type' => 'outbound',
                        'amount' => $rec['amount'],
                        'base_unit_id' => $rec['base_unit_id'],
                        'transaction_detail_id' => $rec['transaction_detail_id'],
                        'inbound_buy_price' => null,
                    ]);
                }

                // Update transaction price/fee/total
                $cafe = MCafe::find($transaction->cafe_id);
                $newPrice = (float) $transaction->price + $priceAdd;
                $ppn = $cafe->ppn_fee > 0 ? ($newPrice * ($cafe->ppn_fee / 100)) : 0;
                $paymentTypeFee = $cafe->qris_fee > 0 && $transaction->payment_type === 'qris' ? ($newPrice * ($cafe->qris_fee / 100)) : 0;
                $newFee = $ppn + $paymentTypeFee;
                $newTotal = $newPrice + $newFee;

                $transaction->price = $newPrice;
                $transaction->fee = $newFee;
                $transaction->total_price = $newTotal;
                $transaction->save();

                // Recompute profit margin based on all outbound records of this transaction
                $detailIds = $transaction->details()->pluck('id')->all();
                $outbounds = MaterialInboundOutbound::where('type', 'outbound')
                    ->whereIn('transaction_detail_id', $detailIds)
                    ->with('material')
                    ->get();

                $totalMaterialCost = 0;
                foreach ($outbounds as $o) {
                    $totalMaterialCost += (float) $o->amount * (float) $o->material->avg_buy_price;
                }

                $transaction->profit_margin = (float) $transaction->total_price - $totalMaterialCost;
                $transaction->save();

                return $transaction->fresh(['details.menu']);
            });

            return $this->success($resultTransaction, 'Orders added to open bill');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return $this->clientError('Validation failed', $ve->errors());
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
