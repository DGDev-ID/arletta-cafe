<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PromoBanner extends Model
{
    protected $fillable = [
        'title',
        'image_url',
        'start_date',
        'end_date',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: hanya banner aktif & dalam periode yang valid.
     *
     * - is_active = true
     * - start_date <= today (atau null)
     * - end_date >= today (atau null)
     */
    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $today);
            })
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            });
    }

    /**
     * Scope: urutkan berdasarkan sort_order ASC.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }
}
