<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Skupina opakovaných termínov jedného podujatia.
 *
 * Nedrží žiadny obsah — ten je na podujatiach. Séria existuje preto, aby sa
 * dalo povedať „toto sú tie isté divadelné predstavenia, len v iné dni":
 * spoločný popis sa mení na jednom mieste a verejný výpis ukáže program raz,
 * nie osemkrát.
 *
 * Séria s jediným termínom je platný stav — vzniká v momente, keď organizátor
 * pridá druhý termín, a zostane aj keď ostatné zmaže. Prázdnu sériu upratuje
 * `EventSeriesManager` pri odobratí posledného termínu.
 */
class EventSeries extends Model
{
    protected $table = 'event_series';

    protected $guarded = [];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class);
    }

    /** Termíny série od najbližšieho; bez termínu na koniec. */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'series_id')
            ->orderByRaw('start_at IS NULL')
            ->orderBy('start_at');
    }
}
