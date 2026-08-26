<?php

namespace App\Services\Imports;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Geocoding\MunicipalityGeocodeResolver;
use App\Services\OpenAI\Detector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ImportedVenueManager
{
    public function __construct(
        private readonly Detector $detector = new Detector(),
        private readonly ImportedProfileDescriber $describer = new ImportedProfileDescriber(),
        private readonly MunicipalityGeocodeResolver $municipalityGeocoder = new MunicipalityGeocodeResolver(),
    ) {}

    public function resolveOrDetect(
        Canal $canal,
        ?string $venueName,
        ?string $venueCity,
        ?string $venueStreet = null,
        ?float $latitude = null,
        ?float $longitude = null,
    ): Venue {
        $hasCoordinates = $latitude !== null && $longitude !== null;
        $venueName = $this->trimmedOrNull($venueName);
        $venueCity = $this->trimmedOrNull($venueCity);

        if ($venueName !== null) {
            // Obec z číselníka (bez siete) ešte pred kontrolou duplicity:
            // hľadanie podľa holého názvu musí byť ohraničené obcou, inak by
            // splynuli rovnomenné miesta z rôznych miest. S ňou sa naopak
            // podchytí „Sanktuárium Božieho Milosrdenstva“ vs to isté miesto
            // s pripísanou obcou („…, Ladce“), čo predtým založilo duplikát.
            $villageHint = $this->municipalityGeocoder->catalogOnly($venueCity);

            $existing = $this->findByName($venueName, $villageHint);
            if ($existing instanceof Venue) {
                return $this->adopt($existing, $canal, $hasCoordinates, $latitude, $longitude);
            }

            if ((bool) config('services.imports.detect_canal_with_ai', false) && $venueCity !== null) {
                try {
                    // Krajina zuzuje Nominatim dotazy na SR; bez nej sa "Skalka"
                    // alebo "Kalvaria" trafi kdekolvek na svete.
                    $detected = $this->detector->detectVenueDetails($venueName, $venueCity, 'Slovensko');
                    if ($detected['can_store_immediately'] ?? false) {
                        $payload = array_merge($detected['venue_store_payload'], [
                            'status' => ModelStatus::Draft->value,
                        ]);
                        // Wikipedia popis je presnejší; keď ho enrichment nenašiel, dopíše ho AI.
                        if (blank($payload['body'] ?? null)) {
                            $payload['body'] = $this->describer->forVenue(
                                is_string($payload['name'] ?? null) && $payload['name'] !== '' ? $payload['name'] : $venueName,
                                $venueCity,
                            );
                        }
                        // A map-pin from the source article beats the geocoder guess.
                        // Znacku do clanku dal clovek, takze je to rovnaka
                        // uroven istoty ako rucne urcena poloha -- a hlavne
                        // nesmie ostat oznacena zdrojom, z ktoreho uz nie je.
                        if ($hasCoordinates) {
                            $payload['latitude'] = $latitude;
                            $payload['longitude'] = $longitude;
                            $payload['coordinates_source'] = 'manual';
                        }
                        // Druhá kontrola duplicity — už na normalizovanom názve.
                        // Detektor prepíše "Evanjelickom kostole v Liptovskom
                        // Mikuláši" na "Evanjelický kostol (Liptovský Mikuláš)" a
                        // pod týmto názvom miesto aj uloží. findByName() vyššie sa
                        // však pýtala na surový názov z článku, takže existujúci
                        // záznam nikdy nenašla a každý beh importu založil ďalší
                        // duplikát. Pýtame sa preto ešte raz na to, čo ideme uložiť.
                        $normalizedName = is_string($payload['name'] ?? null) && $payload['name'] !== ''
                            ? (string) $payload['name']
                            : $venueName;

                        if ($normalizedName !== $venueName) {
                            $existing = $this->findByName(
                                $normalizedName,
                                is_numeric($payload['village_id'] ?? null) ? (int) $payload['village_id'] : null,
                            );
                            if ($existing instanceof Venue) {
                                return $this->adopt($existing, $canal, $hasCoordinates, $latitude, $longitude);
                            }
                        }

                        $venue = Venue::create($payload);
                        // Kto miesto priniesol, ten ho aj vlastní — VenuePolicy
                        // sa pýta výhradne na `ownerCanals()`, takže bez toho
                        // importované miesto v dashboarde neupraví nikto okrem
                        // super-admina. Viď backfill_venue_owner_canal.
                        $venue->assignCanal($canal, isOwner: true);
                        return $venue;
                    }
                } catch (\Throwable) {
                    // venue detection failed, fall through to simple draft
                }
            }
        }

        // Dohľadanie obce beží aj bez názvu miesta: veľa článkov uvedie len
        // mesto ("Poprad", "Gaboltov") a taký event predtým spadol rovno do
        // zberného "Celé Slovensko", hoci obec bola známa. Resolver skúša
        // najprv číselník a až potom geokóder — pútnické miesto ako "Skalka
        // pri Trenčíne" nie je obec, ale OSM ho vie zaradiť pod Trenčín.
        $municipality = $this->municipalityGeocoder->resolve($venueCity, $venueName);

        if ($municipality !== null) {
            $name = $venueName ?? $municipality['city'];

            // Keď názov miesta z článku nevyšiel, venue sa volá po obci —
            // findByName() vyššie taký názov nikdy nehľadalo, takže duplicitu
            // treba overiť tu. Striktne na slugu: voľné "LIKE %Nitra%" by
            // mestský záznam zlúčilo s konkrétnou "Kalvária (Nitra)".
            if ($venueName === null) {
                $existing = $this->findByCityName($name, $municipality['village_id']);
                if ($existing instanceof Venue) {
                    return $this->adopt($existing, $canal, $hasCoordinates, $latitude, $longitude);
                }
            }

            $venue = Venue::create([
                'village_id' => $municipality['village_id'],
                'name'       => Str::limit($name, 250, ''),
                'street'     => $venueStreet ? Str::limit($venueStreet, 250, '') : null,
                'postcode'   => $municipality['postcode'],
                'body'       => $this->describer->forVenue($name, $municipality['city']),
                'category'   => null,
                'status'     => ModelStatus::Draft->value,
                'country'    => 'Slovensko',
                // Pin z článku má prednosť pred súradnicami z geokódera.
                'latitude'   => $hasCoordinates ? $latitude : $municipality['latitude'],
                'longitude'  => $hasCoordinates ? $longitude : $municipality['longitude'],
            ]);
            // Vlastníkom je kanál, ktorý miesto založil — viď vetva vyššie.
            $venue->assignCanal($canal, isOwner: true);
            return $venue;
        }

        return $this->resolveFallbackVenueForCanal($canal);
    }

    public function resolveFallbackVenueForCanal(Canal $canal): Venue
    {
        $venue = $this->resolveFallbackVenue();
        // Zberné „Celé Slovensko" vlastníka nemá a mať nesmie: je spoločné pre
        // všetky importy a prvý náhodný kanál by ho dostal do rúk.
        if (!$venue->activeCanals()->where('canals.id', $canal->id)->exists()) {
            $venue->assignCanal($canal, isOwner: false);
        }
        return $venue;
    }

    public function resolveFallbackVenue(): Venue
    {
        $venue = Venue::query()
            ->where('category', 'fallback')
            ->where('slug', 'cele-slovensko')
            ->first();

        if ($venue instanceof Venue) {
            return $venue;
        }

        return Venue::query()->create([
            'village_id' => 4209,
            'name' => 'Celé Slovensko',
            'street' => null,
            'postcode' => null,
            'body' => null,
            'website' => null,
            'country' => 'Slovensko',
            'latitude' => null,
            'longitude' => null,
            'capacity' => null,
            'opening_hours' => null,
            'category' => 'fallback',
            'status' => ModelStatus::Draft->value,
        ]);
    }

    /**
     * Striktné hľadanie miesta pomenovaného po obci: len presný slug v rámci
     * tej istej obce. findByName() sem nesedí — jeho voľné "LIKE %názov%"
     * by mestský záznam ("Nitra") zlúčilo s konkrétnym miestom v tom meste.
     */
    private function findByCityName(string $name, int $villageId): ?Venue
    {
        return Venue::query()
            ->where(fn ($q) => $q->whereNull('category')->orWhere('category', '!=', 'fallback'))
            ->where('village_id', $villageId)
            ->where('slug', Str::slug($name))
            ->first();
    }

    /**
     * Názov obce pre orezanie koncovky v ImportedNameMatcher. Import prejde
     * v jednom behu desiatky článkov z tej istej obce, tak nech to nie je
     * dotaz na každý z nich.
     */
    private function municipalityName(int $villageId): ?string
    {
        return Cache::remember(
            'imports:municipality_name:' . $villageId,
            now()->addDay(),
            fn (): ?string => Municipality::query()->find($villageId)?->fullname,
        );
    }

    private function trimmedOrNull(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Prevezme už existujúce miesto: pripojí ho ku kanálu a doplní súradnice.
     */
    private function adopt(
        Venue $existing,
        Canal $canal,
        bool $hasCoordinates,
        ?float $latitude,
        ?float $longitude,
    ): Venue {
        // Ensure the venue is linked to this canal so the repository validation passes
        if (!$existing->activeCanals()->where('canals.id', $canal->id)->exists()) {
            $existing->assignCanal($canal, isOwner: false);
        }
        // Backfill coordinates from an event's map pin only when the venue has none.
        if ($hasCoordinates && $existing->latitude === null && $existing->longitude === null) {
            $existing->update([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'coordinates_source' => 'manual',
            ]);
        }

        return $existing;
    }

    /**
     * @param int|null $villageId keď je známa obec, dovolí aj voľnejšiu zhodu
     *                            na holom slugu — bez nej by sa zlúčil
     *                            "Evanjelický kostol (Bratislava)" s
     *                            "Evanjelický kostol (Liptovský Mikuláš)".
     */
    private function findByName(string $name, ?int $villageId = null): ?Venue
    {
        $slug = Str::slug($name);
        // Pozor na NULL: importované miesta majú category = NULL a v SQL
        // sa `NULL != 'fallback'` vyhodnotí ako NULL, nie TRUE — taká
        // podmienka by ich všetky odfiltrovala a import by pri každom
        // behu zakladal nový duplikát namiesto nájdenia existujúceho.
        $notFallback = fn ($q) => $q->whereNull('category')->orWhere('category', '!=', 'fallback');

        $venue = Venue::query()
            ->where($notFallback)
            ->where(function ($q) use ($name, $slug) {
                $q->where('slug', $slug)
                  ->orWhere('name', $name)
                  ->orWhere('name', 'like', '%' . addslashes(Str::limit($name, 100, '')) . '%');
            })
            ->first();

        if ($venue instanceof Venue) {
            return $venue;
        }

        if ($villageId === null) {
            return null;
        }

        // Zhoda na holom slugu v rámci tej istej obce podchytí prípad, keď sa
        // upresňujúca zátvorka alebo koncovka s obcou medzi behmi importu
        // zmenila či pribudla („Sanktuárium Božieho Milosrdenstva“ vs
        // „Sanktuárium Božieho Milosrdenstva, Ladce“).
        return ImportedNameMatcher::firstByBaseName(
            Venue::query()->where($notFallback)->where('village_id', $villageId),
            $name,
            $this->municipalityName($villageId),
        );
    }
}

