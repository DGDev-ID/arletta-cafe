<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuCombo extends Model
{
    protected $fillable = [
        'menu_id',
        'group_id',
        'combo_menu_id',
        'amount',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(MMenu::class, 'menu_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuComboGroup::class, 'group_id');
    }

    public function childMenu(): BelongsTo
    {
        return $this->belongsTo(MMenu::class, 'combo_menu_id');
    }
}
