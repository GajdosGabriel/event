<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Výraz, ktorý by AI bola použila ako štítok, keby nebola obmedzená číselníkom.
 * Slúži na to, aby sa číselník rozširoval podľa reálnych podujatí a nie odhadom.
 */
class TagSuggestion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'occurrences' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    /** Ešte nikto nerozhodol, či sa má výraz doplniť do číselníka. */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolution');
    }
}
