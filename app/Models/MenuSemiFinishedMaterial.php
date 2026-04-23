<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuSemiFinishedMaterial extends Model
{
    protected $fillable = [
        'menu_id',
        'semi_finished_material_id',
        'multiplier',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:2',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(MMenu::class, 'menu_id');
    }

    public function semiFinishedMaterial(): BelongsTo
    {
        return $this->belongsTo(SemiFinishedMaterial::class);
    }
}
