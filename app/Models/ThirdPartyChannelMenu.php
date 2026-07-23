<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThirdPartyChannelMenu extends Model
{

    protected $fillable = [
        'third_party_channel_id',
        'menu_id',
        'admin_fee',
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
