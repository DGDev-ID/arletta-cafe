<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitMaterialConverter extends Model
{
    protected $fillable = [
        'material_id',
        'from_unit_id',
        'to_unit_id',
        'multiplier',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
        ];
    }

    public function getMultiplierAttribute($value)
    {
        $value = (float) $value;

        if (fmod($value, 1) == 0.0) {
            return (int) $value;
        }

        return $value;
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MMaterial::class, 'material_id');
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(MUnit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(MUnit::class, 'to_unit_id');
    }
}
