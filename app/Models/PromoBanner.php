<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
     * Relasi many-to-many ke MCafe melalui tabel pivot promo_banner_cafes.
     * Banner hanya tampil di cafe yang terdaftar di relasi ini.
     */
    public function cafes(): BelongsToMany
    {
        return $this->belongsToMany(MCafe::class, 'promo_banner_cafes', 'promo_banner_id', 'cafe_id');
    }

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

    /**
     * Scope: filter banner yang ditampilkan di cafe tertentu.
     */
    public function scopeForCafe(Builder $query, int $cafeId): Builder
    {
        return $query->whereHas('cafes', fn(Builder $q) => $q->where('m_cafes.id', $cafeId));
    }
}
