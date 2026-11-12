<?php

namespace App\Services\Geocoding;

use App\Models\Canal;
use App\Models\CoordinateLookup;
use App\Models\Municipality;
use App\Models\Venue;
use App\Support\LookupInputs;
use App\Support\NationwideCoordinates;
use Illuminate\Database\Eloquent\Model;

/**
 * Doplnenie GPS súradníc kanálu alebo miestu, ktorému chýba poloha.
 *
 * Volá sa z fronty (BackfillPlaceCoordinates), nie pri uložení formulára:
 * Nominatim vyžaduje odstup medzi dotazmi a jedno uloženie kanála tak na
 * produkcii trvalo 32 s. Používa len Nominatim (adresa -> budova pri mieste
 * -> stred obce), AI nie — z holého názvu by hádala naslepo. Lepšie podklady
 * (adresu, web) dohľadá dopĺňanie profilu a ich zmena hľadanie spustí znova.
 * Chyba geokódovania nie je fatálna — záznam si zástupný stred Slovenska
 * drží ďalej (AlwaysHasCoordinates).
 */
class PlaceCoordinateBackfill
{
    public function __construct(
        private readonly PlaceCoordinateResolver $resolver = new PlaceCoordinateResolver,
    ) {}

    /**
     * Má zmysel záznam geokódovať? Zástupný stred Slovenska je chýbajúca
     * poloha; kanálu či miestu zo zberného „Celé Slovensko" však patrí právom.
     */
    public function needsLookup(Canal|Venue $model): bool
    {
        return NationwideCoordinates::needsLookup($model->latitude, $model->longitude)
            && (int) $this->municipalityIdOf($model) !== Municipality::nationwideId();
    }

    /**
     * Treba po uložení formulára hľadať polohu? Len pri novom zázname alebo
     * keď sa zmenilo niečo, z čoho sa poloha odvodzuje. Uloženie webu či
     * popisu záznamu, ktorému geokóder polohu raz nenašiel, ho nespúšťa
     * nanovo — vrátil by to isté.
     *
     * Volať hneď po save(): neskorší save (napr. zmena e-mailu) by
     * wasChanged() prepísal.
     */
    public function shouldLookupAfterSave(Canal|Venue $model): bool
    {
        $municipality = $model instanceof Canal ? 'municipality_id' : 'village_id';

        return $this->needsLookup($model) && ($model->wasRecentlyCreated || $model->wasChanged([
            'name', $municipality, 'street', 'postcode', 'country', 'latitude', 'longitude',
        ]));
    }

    /**
     * Hľadá len s novými podkladmi: s rovnakou adresou a obcou by Nominatim
     * vrátil to isté. `$force` (ručné spustenie z adminu) odtlačok ignoruje.
     */
    public function backfill(Canal|Venue $model, bool $force = false): void
    {
        if (! $this->needsLookup($model)) {
            return;
        }

        $inputs = LookupInputs::coordinates($model);

        if (! $force && CoordinateLookup::for($model)?->inputs_hash === $inputs) {
            return;
        }

        $coords = $model instanceof Canal ? $this->resolveCanal($model) : $this->resolveVenue($model);
        $this->apply($model, $coords);

        CoordinateLookup::query()->updateOrCreate(
            ['subject_type' => $model->getMorphClass(), 'subject_id' => $model->getKey()],
            ['inputs_hash' => $inputs, 'source' => $coords['latitude'] !== null ? $coords['source'] : null, 'looked_up_at' => now()],
        );
    }

    /** @return array{latitude: float|null, longitude: float|null, source: string|null} */
    private function resolveCanal(Canal $canal): array
    {
        $city = $this->municipalityName($canal->municipality_id);
        $country = trim((string) $canal->country) !== '' ? $canal->country : 'Slovensko';

        // Kanál je organizátor, nie budova: poloha je jeho adresa, a keď ju
        // nepoznáme, stred obce. Adresu mu neskôr nájde dopĺňanie profilu.
        return $this->resolver->resolve(
            $canal->name, $city, $country, $canal->street, $canal->postcode, matchBuilding: false,
        );
    }

    /** @return array{latitude: float|null, longitude: float|null, source: string|null} */
    private function resolveVenue(Venue $venue): array
    {
        return $this->resolver->resolve(
            $venue->name,
            $this->municipalityName($venue->village_id),
            $venue->country,
            $venue->street,
            $venue->postcode,
        );
    }

    /**
     * @param  array{latitude: float|null, longitude: float|null, source: string|null}  $coords
     */
    private function apply(Model $model, array $coords): void
    {
        if ($coords['latitude'] === null || $coords['longitude'] === null) {
            return;
        }

        $model->forceFill([
            'latitude' => $coords['latitude'],
            'longitude' => $coords['longitude'],
            // Bez zdroja by sa približná poloha (stred obce) navonok nelíšila
            // od presnej a nikto by už nevedel, ktoré záznamy treba upresniť.
            'coordinates_source' => $coords['source'],
        ])->save();
    }

    private function municipalityIdOf(Canal|Venue $model): mixed
    {
        return $model instanceof Canal ? $model->municipality_id : $model->village_id;
    }

    private function municipalityName(mixed $municipalityId): ?string
    {
        if ($municipalityId === null || $municipalityId === '') {
            return null;
        }

        $municipality = Municipality::query()->find($municipalityId, ['shortname', 'fullname']);

        $name = trim((string) ($municipality?->shortname ?? ''));
        if ($name !== '') {
            return $name;
        }

        $fullname = trim((string) ($municipality?->fullname ?? ''));

        return $fullname !== '' ? $fullname : null;
    }
}
