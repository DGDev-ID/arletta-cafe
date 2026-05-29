<?php
// app/Models/MaterialVariant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialVariant extends Model
{
    protected $fillable = [
        'material_id',
        'name',
        'stock',
        'minimum_stock',
    ];

    protected function casts(): array
    {
        return [
            'stock'         => 'decimal:2',
            'minimum_stock' => 'decimal:2',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MMaterial::class, 'material_id');
    }
}