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
        'img_url',
        'phone_number'
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(MCafeTable::class, 'cafe_id');
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

    public function cafeAdmins(): HasMany
    {
        return $this->hasMany(CafeAdmin::class, 'cafe_id');
    }

    public function cafeCashiers(): HasMany
    {
        return $this->hasMany(CafeCashier::class, 'cafe_id');
    }
}
