<?php

namespace App\Services\Lookups;

use App\Jobs\BackfillPlaceCoordinates;
use App\Models\Canal;
use App\Models\Venue;
use App\Services\Geocoding\PlaceCoordinateBackfill;
use App\Services\Profiles\ProfileEnricher;

/**
 * Čo sa po uložení kanála alebo miesta hľadá — a čo nie.
 *
 * Pri uložení sa nikdy nečaká na sieť ani nevolá AI. Hľadá sa len vtedy,
 * keď pribudol nový podklad:
 *  - poloha: nový záznam alebo zmena obce, adresy (miesto aj názvu) —
 *    z fronty, len cez Nominatim (PlaceCoordinateBackfill),
 *  - profil: zmena názvu, obce, webu alebo adresy otvorí dokončené
 *    dopĺňanie znova (ProfileEnricher::reopenIfInputsChanged).
 *
 * Takmer prázdny záznam (len názov a obec) tak dostane stred obce a čaká
 * na dopĺňanie profilu, ktoré mu nájde web a adresu; poloha z adresy a
 * e-mail z webu prídu až po ňom.
 */
class PlaceLookups
{
    public function __construct(
        private readonly PlaceCoordinateBackfill $coordinates,
        private readonly ProfileEnricher $profiles,
    ) {}

    /** Volať hneď po save(): neskorší save by wasChanged() prepísal. */
    public function afterSave(Canal|Venue $model): void
    {
        if ($this->coordinates->shouldLookupAfterSave($model)) {
            BackfillPlaceCoordinates::dispatch($model::class, (int) $model->getKey());
        }

        $municipality = $model instanceof Canal ? 'municipality_id' : 'village_id';

        if (! $model->wasRecentlyCreated
            && $model->wasChanged(['name', $municipality, 'website', 'street', 'postcode', 'country'])) {
            $this->profiles->reopenIfInputsChanged($model);
        }
    }
}
