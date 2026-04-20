<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MMaterial extends Model
{
    protected $fillable = [
        'cafe_id',
        'name',
        'base_unit_id',
        'stock',
        'avg_buy_price',
        'critical_stock'
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:2',
            'avg_buy_price' => 'decimal:2',
        ];
    }

    public static function setOutOfStock($materialId): void
    {
        DB::transaction(function () use ($materialId) {

            $material = self::lockForUpdate()->find($materialId);

            if (! $material) {
                return;
            }

            MaterialInboundOutbound::create([
                'material_id' => $material->id,
                'type' => 'outbound',
                'amount' => $material->stock,
                'base_unit_id' => $material->base_unit_id,
                'transaction_detail_id' => null,
                'inbound_buy_price' => null,
            ]);
        });
    }

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(MCafe::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(MUnit::class, 'base_unit_id');
    }

    public function converters(): HasMany
    {
        return $this->hasMany(UnitMaterialConverter::class, 'material_id');
    }

    public function menuMaterials(): HasMany
    {
        return $this->hasMany(MenuMaterial::class);
    }

    public function inboundOutbounds(): HasMany
    {
        return $this->hasMany(MaterialInboundOutbound::class, 'material_id');
    }
}
