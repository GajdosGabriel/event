<?php

namespace App\Services\Profiles;

use App\Models\Canal;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Geocoding\NominatimGeocoder;
use App\Support\NationwideCoordinates;
use Illuminate\Support\Str;

class ProfileCoordinates
{
    public function __construct(private NominatimGeocoder $geocoder) {}

    public function needsLookup(Canal|Venue $subject): bool
    {
        // Even a manual pin on a municipality centre belongs to the user.
        if ($subject->coordinates_source === 'manual') {
            return false;
        }

        return NationwideCoordinates::needsLookup($subject->latitude, $subject->longitude)
            || in_array($subject->coordinates_source, ['municipality', 'ai'], true);
    }

    public function resolve(Canal|Venue $subject, array $found): array
    {
        if (! $this->needsLookup($subject)) {
            return [];
        }
        $city = $subject->municipality;
        $cityName = $city && $city->id !== Municipality::nationwideId()
            ? $city->fullname : ($found['city']['value'] ?? null);
        if (! is_string($cityName) || trim($cityName) === '' || Str::slug($cityName) === 'cele-slovensko') {
            return [];
        }
        $street = trim((string) $subject->street) ?: ($found['street']['value'] ?? null);
        $postcode = trim((string) $subject->postcode) ?: ($found['postcode']['value'] ?? null);
        $country = trim((string) $subject->country) ?: ($found['country']['value'] ?? 'Slovensko');
        $hit = [];
        $source = 'address';
        if ($street) {
            $hit = $this->geocoder->lookupAddress($street, $postcode, $cityName, $country);
            // An address result must include the requested street/number, not only a town centroid.
            if ($this->normalize($hit['street'] ?? '') !== $this->normalize($street)) {
                $hit = [];
            }
        }
        if (! $this->valid($hit, $cityName)) {
            // For an organization a name-only POI may be an event venue, not its registered seat.
            if ($subject instanceof Canal) {
                return [];
            }
            $source = 'venue';
            $hit = $this->geocoder->lookup((string) $subject->name, $cityName, $country);
            // Do not contradict an address the owner already entered.
            if ($street && $this->normalize($hit['street'] ?? '') !== $this->normalize($street)) {
                return [];
            }
        }
        if (! $this->valid($hit, $cityName)) {
            return [];
        }
        $lat = (float) $hit['latitude'];
        $lng = (float) $hit['longitude'];
        // An orphaned coordinate is still user data; do not combine unrelated coordinate pairs.
        if ($subject->latitude !== null && $subject->longitude === null && abs((float) $subject->latitude - $lat) > 0.00001
            || $subject->longitude !== null && $subject->latitude === null && abs((float) $subject->longitude - $lng) > 0.00001) {
            return [];
        }

        return [
            'latitude' => $lat, 'longitude' => $lng, 'coordinates_source' => $source,
            'source_url' => 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lng.'#map=18/'.$lat.'/'.$lng,
            'evidence' => 'Nominatim / OpenStreetMap: '.implode(', ', array_filter([$subject->name, $street, $cityName, $country])),
        ];
    }

    private function valid(array $hit, string $city): bool
    {
        return is_numeric($hit['latitude'] ?? null) && is_numeric($hit['longitude'] ?? null)
            && abs((float) $hit['latitude']) <= 90 && abs((float) $hit['longitude']) <= 180
            && ! NationwideCoordinates::isPlaceholder($hit['latitude'], $hit['longitude'])
            && $this->normalize($hit['city'] ?? '') === $this->normalize($city);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value)));
    }
}
