<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThirdPartyChannel extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'third_party_channel_id');
    }

    public function channelMenus(): HasMany
    {
        return $this->hasMany(ThirdPartyChannelMenu::class, 'third_party_channel_id');
    }
}
