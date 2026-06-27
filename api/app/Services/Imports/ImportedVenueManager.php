<?php

namespace App\Services\Imports;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Venue;
use App\Services\OpenAI\Detector;
use Illuminate\Support\Str;

class ImportedVenueManager
{
    public function __construct(
        private readonly Detector $detector = new Detector(),
    ) {}

    public function resolveOrDetect(Canal $canal, ?string $venueName, ?string $venueCity): Venue
    {
        if (is_string($venueName) && $venueName !== '') {
            $existing = $this->findByName($venueName);
            if ($existing instanceof Venue) {
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
                    // venue detection failed, fall through to fallback
                }
            }
        }

        return $this->resolveFallbackVenue($canal);
    }

    public function resolveFallbackVenue(Canal $canal): Venue
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
