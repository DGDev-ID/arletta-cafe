<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThirdPartyChannelMenu extends Model
{

    protected $fillable = [
        'third_party_channel_id',
        'menu_id',
        'admin_fee',
        'is_manual_price',
        'override_price',
    ];

    protected $casts = [
        'is_manual_price' => 'boolean',
        'override_price'  => 'float',
        'admin_fee'       => 'float',
    ];

    public function thirdPartyChannel()
    {
        return $this->belongsTo(ThirdPartyChannel::class);
    }

    public function menu()
    {
        return $this->belongsTo(MMenu::class, 'menu_id');
    }
}
