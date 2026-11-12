<?php

namespace App\Jobs;

use App\Models\Canal;
use App\Models\Venue;
use App\Services\Geocoding\PlaceCoordinateBackfill;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Doplní súradnice kanálu alebo miestu mimo requestu.
 *
 * Uloženie formulára na geokóder nečaká (viď PlaceCoordinateBackfill) —
 * mapa sa objaví po najbližšom behu fronty, na produkcii do minúty.
 */
class BackfillPlaceCoordinates implements ShouldQueue
{
    use Queueable;

    /**
     * Geokóder chyby nevyhadzuje, vráti len prázdny výsledok. Opakovanie by
     * znova čakalo na Nominatim a nedalo by iný výsledok — ďalší pokus príde
     * s novými podkladmi (LookupInputs).
     */
    public int $tries = 1;

    /**
     * @param  class-string<Canal|Venue>  $modelClass
     * @param  bool  $force  ručné spustenie z adminu — ignoruje odtlačok podkladov
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly int $modelId,
        public readonly bool $force = false,
    ) {
        // Job nesmie predbehnúť commit transakcie, v ktorej sa záznam uložil.
        $this->afterCommit();
    }

    public function handle(PlaceCoordinateBackfill $backfill): void
    {
        // Čerstvý stav: kým job čakal vo fronte, mohol niekto polohu zadať ručne.
        $model = $this->modelClass::query()->withTrashed()->find($this->modelId);

        if ($model !== null) {
            $backfill->backfill($model, $this->force);
        }
    }
}
