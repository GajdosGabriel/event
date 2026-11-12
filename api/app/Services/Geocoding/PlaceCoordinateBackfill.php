<?php

namespace App\Services\Geocoding;

use App\Models\Canal;
use App\Models\Municipality;
use App\Models\Venue;
use App\Support\NationwideCoordinates;
use Illuminate\Database\Eloquent\Model;

/**
 * Doplnenie GPS súradníc kanálu alebo miestu, ktorému chýba poloha.
 *
 * Volá sa z fronty (BackfillPlaceCoordinates), nie pri uložení formulára:
 * rebrík zdrojov (adresa -> budova v OSM -> AI -> stred obce) čaká na
 * Nominatim s povinným odstupom a na OpenAI a jedno uloženie kanála tak na
 * produkcii trvalo 32 s. Chyba geokódovania nie je fatálna — záznam si
 * zástupný stred Slovenska drží ďalej (AlwaysHasCoordinates).
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
     * nanovo — vrátil by to isté a zaplatilo by sa za AI.
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

    public function backfill(Canal|Venue $model): void
    {
        if (! $this->needsLookup($model)) {
            return;
        }

        $model instanceof Canal ? $this->backfillCanal($model) : $this->backfillVenue($model);
    }

    private function backfillCanal(Canal $canal): void
    {
        $city = $this->municipalityName($canal->municipality_id);
        $country = trim((string) $canal->country) !== '' ? $canal->country : 'Slovensko';

        // Rozpísaná adresa má prednosť pred holým názvom kanála — odkedy ju
        // editor zbiera, je to najpresnejší vstup, aký geokóder dostane.
        $coords = $this->resolver->resolve($canal->name, $city, $country, $canal->street, $canal->postcode);

        // Kanál je často organizátor (nie fyzické miesto) — ak sa podľa názvu
        // nič nenájde, použi aspoň stred obce, aby sa mapa dala zobraziť.
        if (($coords['latitude'] === null || $coords['longitude'] === null) && $city !== null) {
            $coords = $this->resolver->resolve(null, $city, $country);
        }

        $this->apply($canal, $coords);
    }

    private function backfillVenue(Venue $venue): void
    {
        $coords = $this->resolver->resolve(
            $venue->name,
            $this->municipalityName($venue->village_id),
            $venue->country,
            $venue->street,
            $venue->postcode,
        );

        $this->apply($venue, $coords);
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
