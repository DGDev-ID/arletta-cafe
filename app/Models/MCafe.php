<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MCafe extends Model
{
    protected $fillable = [
        'unique_id',
        'name',
        'address',
        'address_coordinate',
        'description',
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(MCafeTable::class);
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(MMenuCategory::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(MMenu::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(MMaterial::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
