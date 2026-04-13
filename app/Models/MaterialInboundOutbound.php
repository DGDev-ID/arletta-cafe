<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialInboundOutbound extends Model
{
    protected $table = 'material_inbound_outbounds';

    protected $fillable = [
        'material_id',
        'type',
        'amount',
        'base_unit_id',
        'transaction_detail_id',
        'inbound_buy_price',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'inbound_buy_price' => 'decimal:2',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MMaterial::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(MUnit::class, 'base_unit_id');
    }

    public function transactionDetail(): BelongsTo
    {
        return $this->belongsTo(TransactionDetail::class, 'transaction_detail_id');
    }
}
