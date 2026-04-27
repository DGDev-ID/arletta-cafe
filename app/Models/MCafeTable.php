<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MCafeTable extends Model
{
    protected $table = 'm_cafe_tables';

    protected $fillable = [
        'cafe_id',
        'name',
        'status',
        'description',
        'is_open_bill',
    ];

    protected function casts(): array
    {
        return [
            'is_open_bill' => 'integer',
        ];
    }

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(MCafe::class, 'cafe_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'table_id');
    }
}
