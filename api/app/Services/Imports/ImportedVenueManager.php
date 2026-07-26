<?php

namespace App\Services\Imports;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\OpenAI\Detector;
use Illuminate\Support\Str;

class ImportedVenueManager
{
    public function __construct(
        private readonly Detector $detector = new Detector(),
        private readonly ImportedProfileDescriber $describer = new ImportedProfileDescriber(),
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

        if (is_string($venueName) && $venueName !== '') {
            $existing = $this->findByName($venueName);
            if ($existing instanceof Venue) {
                return $this->adopt($existing, $canal, $hasCoordinates, $latitude, $longitude);
            }

            if ((bool) config('services.imports.detect_canal_with_ai', false)
                && is_string($venueCity) && $venueCity !== '') {
                try {
                    $detected = $this->detector->detectVenueDetails($venueName, $venueCity);
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
                        if ($hasCoordinates) {
                            $payload['latitude'] = $latitude;
                            $payload['longitude'] = $longitude;
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
                        $venue->assignCanal($canal, isOwner: false);
                        return $venue;
                    }
                } catch (\Throwable) {
                    // venue detection failed, fall through to simple draft
                }
            }

            // Auto-create a draft venue when city can be resolved to a municipality.
            // A pilgrimage site is often named only by its village ("do Klokočova") with no
            // separate city, so fall back to reading the venue name itself as the municipality.
            $cityCandidate = is_string($venueCity) && $venueCity !== '' ? $venueCity : $venueName;

            if ($cityCandidate !== '') {
                $villageId = $this->resolveMunicipalityId($cityCandidate);
                if ($villageId !== null) {
                    $venue = Venue::create([
                        'village_id' => $villageId,
                        'name'       => Str::limit($venueName, 250, ''),
                        'street'     => $venueStreet ? Str::limit($venueStreet, 250, '') : null,
                        'body'       => $this->describer->forVenue($venueName, $venueCity),
                        'category'   => null,
                        'status'     => ModelStatus::Draft->value,
                        'country'    => 'Slovensko',
                        'latitude'   => $hasCoordinates ? $latitude : null,
                        'longitude'  => $hasCoordinates ? $longitude : null,
                    ]);
                    $venue->assignCanal($canal, isOwner: false);
                    return $venue;
                }
            }
        }

        return $this->resolveFallbackVenueForCanal($canal);
    }

    public function resolveFallbackVenueForCanal(Canal $canal): Venue
    {
        $venue = $this->resolveFallbackVenue();
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
     * Resolves a city name (potentially in Slovak locative/genitive case) to a municipality id.
     * Tries exact match first, then prefix-based fuzzy match to handle inflected forms
     * (e.g. "Bratislave" → "Bratislava", "Košiciach" → "Košice").
     */
    private function resolveMunicipalityId(string $city): ?int
    {
        $municipality = Municipality::query()
            ->where('fullname', $city)
            ->orWhere('shortname', $city)
            ->first();

        if ($municipality !== null) {
            return $municipality->id;
        }

        // Fuzzy prefix: try cutting 1–4 trailing characters to de-inflect Slovak locative endings
        $len = mb_strlen($city);
        if ($len < 4) {
            return null;
        }

        for ($cut = 1; $cut <= min(4, $len - 3); $cut++) {
            $prefix = mb_substr($city, 0, $len - $cut);
            $municipality = Municipality::query()
                ->where('fullname', 'like', $prefix . '%')
                ->orWhere('shortname', 'like', $prefix . '%')
                ->first();

            if ($municipality !== null) {
                return $municipality->id;
            }
        }

        return null;
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
            $existing->update(['latitude' => $latitude, 'longitude' => $longitude]);
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
        // upresňujúca zátvorka medzi behmi importu zmenila alebo pribudla.
        return ImportedNameMatcher::firstByBaseName(
            Venue::query()->where($notFallback)->where('village_id', $villageId),
            $name,
        );
    }
}

