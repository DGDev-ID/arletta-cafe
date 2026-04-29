<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CafePromo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cafe_id',
        'promo_code',
        'type',
        'value',
        'status',
    ];

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(MCafe::class, 'cafe_id');
    }
}
