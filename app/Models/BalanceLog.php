<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceLog extends Model
{
    protected $fillable = [
        'balance_id',
        'type',
        'amount',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(Balance::class);
    }
}
