<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SemiFinishedMaterial extends Model
{
    protected $fillable = [
        'cafe_id',
        'name',
        'base_unit_id'
    ];

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(MCafe::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(SemiFinishedMaterialDetail::class);
    }

    public function menuSemiFinishedMaterials(): HasMany
    {
        return $this->hasMany(MenuSemiFinishedMaterial::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MUnit::class, 'base_unit_id');
    }   
}
