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
    ];

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
        return $this->hasOne(MenuPromo::class);
    }

    public function menuMaterials(): HasMany
    {
        return $this->hasMany(MenuMaterial::class);
    }

    public function transactionDetails(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }
}
