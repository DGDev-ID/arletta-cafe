<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $fillable = [
        'cafe_id',
        'name',
        'base_unit_id',
        'stock',
        'avg_buy_price',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:2',
            'avg_buy_price' => 'decimal:2',
        ];
    }

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function converters(): HasMany
    {
        return $this->hasMany(UnitMaterialConverter::class);
    }

    public function menuMaterials(): HasMany
    {
        return $this->hasMany(MenuMaterial::class);
    }

    public function inboundOutbounds(): HasMany
    {
        return $this->hasMany(MaterialInboundOutbound::class);
    }
}
