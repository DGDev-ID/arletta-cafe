<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Transaction extends Model
{
    protected $fillable = [
        'unique_code',
        'cafe_id',
        'table_id',
        'cust_name',
        'price',
        'fee',
        'total_price',
        'payment_type',
        'status',
        'snap_token',
        'midtrans_transaction_id',
        'profit_margin',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'fee' => 'decimal:2',
            'total_price' => 'decimal:2',
            'profit_margin' => 'decimal:2',
        ];
    }

    protected static function booted()
    {
        static::creating(function ($transaction) {
            $transaction->unique_code = self::generateUniqueCode();
        });
    }

    public static function generateUniqueCode()
    {
        do {
            $code = 'TRX' . strtoupper(Str::random(10) . time());
        } while (self::where('unique_code', $code)->exists());

        return $code;
    }

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(MCafe::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(MCafeTable::class, 'table_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }
}
