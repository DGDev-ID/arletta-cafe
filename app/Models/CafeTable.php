<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CafeTable extends Model
{
    protected $table = 'cafe_tables';

    protected $fillable = [
        'cafe_id',
        'name',
        'status',
        'description',
    ];

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'table_id');
    }
}
