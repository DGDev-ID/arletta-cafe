<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MMenuCategory extends Model
{
    protected $fillable = [
        'cafe_id',
        'name',
        'parent_id',
        'description',
    ];

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(MCafe::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MMenuCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MMenuCategory::class, 'parent_id');
    }

    public function menus(): HasMany
    {
        return $this->hasMany(MMenu::class, 'menu_category_id');
    }
}
