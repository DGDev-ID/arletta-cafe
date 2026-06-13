<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuComboGroup extends Model
{
    protected $fillable = [
        'menu_id',
        'label',
        'sort_order',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(MMenu::class, 'menu_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(MenuCombo::class, 'group_id');
    }
}
