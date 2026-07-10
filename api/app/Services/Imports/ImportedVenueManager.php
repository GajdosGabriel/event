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
    ) {}

    public function resolveOrDetect(Canal $canal, ?string $venueName, ?string $venueCity, ?string $venueStreet = null): Venue
    {
        if (is_string($venueName) && $venueName !== '') {
            $existing = $this->findByName($venueName);
            if ($existing instanceof Venue) {
                // Ensure the venue is linked to this canal so the repository validation passes
                if (!$existing->activeCanals()->where('canals.id', $canal->id)->exists()) {
                    $existing->assignCanal($canal, isOwner: false);
                }
                return $existing;
            }

            if ((bool) config('services.imports.detect_canal_with_ai', false)
                && is_string($venueCity) && $venueCity !== '') {
                try {
                    $detected = $this->detector->detectVenueDetails($venueName, $venueCity);
                    if ($detected['can_store_immediately'] ?? false) {
                        $payload = array_merge($detected['venue_store_payload'], [
                            'status' => ModelStatus::Draft->value,
                        ]);
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
                        'category'   => null,
                        'status'     => ModelStatus::Draft->value,
                        'country'    => 'Slovensko',
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

    private function findByName(string $name): ?Venue
    {
        $slug = Str::slug($name);
        return Venue::query()
            ->where('category', '!=', 'fallback')
            ->where(function ($q) use ($name, $slug) {
                $q->where('slug', $slug)
                  ->orWhere('name', $name)
                  ->orWhere('name', 'like', '%' . addslashes(Str::limit($name, 100, '')) . '%');
            })
            ->first();
    }
}

