<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceTopup extends Model
{
    protected $fillable = [
        'balance_id',
        'topup_amount',
        'unique_amount',
        'total_amount',
        'qris_history_id',
        'status',
        'qris_content'
    ];

    protected function casts(): array
    {
        return [
            'topup_amount'  => 'decimal:2',
            'unique_amount' => 'decimal:2',
            'total_amount'  => 'decimal:2',
        ];
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(Balance::class);
    }
}
