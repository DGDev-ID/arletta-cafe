<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuMaterial extends Model
{
    protected $fillable = [
        'menu_id',
        'material_id',
        'amount',
        'unit_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(MMenu::class, 'menu_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MMaterial::class, 'material_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MUnit::class, 'unit_id');
    }
}
