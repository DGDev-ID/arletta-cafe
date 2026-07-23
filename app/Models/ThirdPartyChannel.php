<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThirdPartyChannel extends Model
{
    protected $fillable = [
        'name',
        'admin_fee',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'admin_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'third_party_channel_id');
    }
}
