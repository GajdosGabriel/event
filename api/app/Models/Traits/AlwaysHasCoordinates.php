<?php

namespace App\Models\Traits;

use App\Support\NationwideCoordinates;
use Illuminate\Database\Eloquent\Model;

/**
 * Záznam sa nikdy neuloží bez súradníc.
 *
 * Miesta a kanály vznikajú mnohými cestami — formulár, import, AI detekcia,
 * registrácia — a nie každá prejde geokóderom. Kým sa súradnice dopĺňali len
 * v repozitári, importované záznamy ostávali prázdne a na mape chýbali.
 *
 * Keď poloha chýba, dostane záznam stred Slovenska. Presnejšiu polohu dopĺňa
 * repozitár po uložení a `app:backfill-venue-coordinates`; oba zástupný bod
 * považujú za chýbajúci (NationwideCoordinates::needsLookup()).
 */
trait AlwaysHasCoordinates
{
    public static function bootAlwaysHasCoordinates(): void
    {
        static::saving(function (Model $model): void {
            if ($model->getAttribute('latitude') !== null && $model->getAttribute('longitude') !== null) {
                return;
            }

            $model->forceFill([
                'latitude' => NationwideCoordinates::LATITUDE,
                'longitude' => NationwideCoordinates::LONGITUDE,
                'coordinates_source' => NationwideCoordinates::SOURCE,
            ]);
        });
    }
}
