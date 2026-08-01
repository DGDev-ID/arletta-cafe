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
        'admin_fee',
        'total_price',
        'payment_type',
        'status',
        'snap_token',
        'midtrans_transaction_id',
        'is_open_bill',
        'is_expense',
        'expense_date',
        'profit_margin',
        'promo_id',
        'third_party_channel_id',
        'third_party_reference',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'fee' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'total_price' => 'decimal:2',
            'profit_margin' => 'decimal:2',
            'is_open_bill' => 'integer',
            'is_expense' => 'integer',
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

    public function promo(): BelongsTo
    {
        return $this->belongsTo(CafePromo::class, 'promo_id');
    }

    public function thirdPartyChannel(): BelongsTo
    {
        return $this->belongsTo(ThirdPartyChannel::class, 'third_party_channel_id');
    }

    public function feedback(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CustomerFeedback::class);
    }
}
