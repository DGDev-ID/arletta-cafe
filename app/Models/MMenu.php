<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MMenu extends Model
{
    protected $fillable = [
        'cafe_id',
        'name',
        'description',
        'img_url',
        'price',
        'status',
        'menu_category_id',
    ];

    public function getNameAttribute($value)
    {
        return preg_replace('/\s*\[(FOOD|BEVERAGE)\]$/', '', $value);
    }

    public function getMenuTypeAttribute()
    {
        $originalName = $this->attributes['name'] ?? '';

        if (preg_match('/\[FOOD\]$/', $originalName)) {
            return 'FOOD';
        }
        
        if (preg_match('/\[BEVERAGE\]$/', $originalName)) {
            return 'BEVERAGE';
        }

        return 'UNCATEGORIZED';
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(MCafe::class);
    }

    public function promo(): HasOne
    {
        return $this->hasOne(MenuPromo::class, 'menu_id');
    }

    public function menuMaterials(): HasMany
    {
        return $this->hasMany(MenuMaterial::class, 'menu_id');
    }

    public function transactionDetails(): HasMany
    {
        return $this->hasMany(TransactionDetail::class, 'menu_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MMenuCategory::class, 'menu_category_id');
    }

    public function menuSemiFinishedMaterials(): HasMany
    {
        return $this->hasMany(MenuSemiFinishedMaterial::class, 'menu_id');
    }
}
