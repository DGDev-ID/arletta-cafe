<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CafeCashier extends Model
{
    protected $fillable = [
        'cafe_id',
        'user_id',
    ];

    public function cafe()
    {
        return $this->belongsTo(MCafe::class, 'cafe_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
