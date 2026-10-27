<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Balance extends Model
{
    protected $fillable = ['balance'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function topups(): HasMany
    {
        return $this->hasMany(BalanceTopup::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BalanceLog::class);
    }
}
