<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MUnit extends Model
{
    protected $fillable = [
        'name',
        'critical_stock',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(MMaterial::class, 'base_unit_id');
    }
}
