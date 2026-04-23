<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialInboundOutbound extends Model
{
    protected $table = 'material_inbound_outbounds';

    protected $fillable = [
        'material_id',
        'type',
        'opening_stock',
        'amount',
        'closing_stock',
        'base_unit_id',
        'transaction_detail_id',
        'inbound_buy_price',
        'description'
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'inbound_buy_price' => 'decimal:2',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MMaterial::class, 'material_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(MUnit::class, 'base_unit_id');
    }

    public function transactionDetail(): BelongsTo
    {
        return $this->belongsTo(TransactionDetail::class, 'transaction_detail_id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            // Lock material row for update
            $material = \App\Models\MMaterial::where('id', $model->material_id)->lockForUpdate()->first();
            if (!$material) {
                throw new \Exception('Material tidak ditemukan');
            }

            $openingStock = (float) $material->stock;
            $model->opening_stock = $openingStock;

            $materialBaseUnitId = $material->base_unit_id;
            $selfBaseUnitId = $model->base_unit_id;
            $amount = (float) $model->amount;
            $type = $model->type;

            // Konversi satuan jika perlu
            if ($selfBaseUnitId != $materialBaseUnitId) {
                $converter = \App\Models\UnitMaterialConverter::where('material_id', $model->material_id)
                    ->where('from_unit_id', $selfBaseUnitId)
                    ->where('to_unit_id', $materialBaseUnitId)
                    ->first();
                if ($converter) {
                    $amount = $amount * $converter->multiplier;
                } else {
                    $reverseConverter = \App\Models\UnitMaterialConverter::where('material_id', $model->material_id)
                        ->where('from_unit_id', $materialBaseUnitId)
                        ->where('to_unit_id', $selfBaseUnitId)
                        ->first();
                    if ($reverseConverter) {
                        $amount = $amount / $reverseConverter->multiplier;
                    } else {
                        throw new \Exception('Konversi satuan tidak ditemukan');
                    }
                }
            }

            // Hitung closing stock
            if ($type === 'inbound') {
                $model->closing_stock = $openingStock + $amount;
                $material->stock = $openingStock + $amount;
            } else {
                $model->closing_stock = $openingStock - $amount;
                $material->stock = $openingStock - $amount;
            }

            $material->save();
        });
    }
}
