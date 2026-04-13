<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cafe extends Model
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
        return $this->hasMany(CafeTable::class);
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
