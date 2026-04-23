<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemiFinishedMaterialDetail extends Model
{
    protected $fillable = [
        'semi_finished_material_id',
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

    public function semiFinishedMaterial(): BelongsTo
    {
        return $this->belongsTo(SemiFinishedMaterial::class);
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
