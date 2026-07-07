<?php

namespace App\Services\Geocoding;

use App\Services\OpenAI\ChatGPT;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight, fail-safe resolver for GPS coordinates of a place (venue/canal).
 *
 * Zdroje suradnic (v poradi):
 *   1) Nominatim (OpenStreetMap) - spolahlivy geokoder
 *   2) AI (ChatGPT) - fallback, ak Nominatim nic nenajde
 *
 * Nikdy nevyhadzuje vynimku - pri chybe vrati null suradnice, aby ulozenie
 * miesta/kanalu nikdy nezlyhalo kvoli geokodovaniu.
 */
class PlaceCoordinateResolver
{
    public function __construct(
        private readonly NominatimGeocoder $nominatimGeocoder = new NominatimGeocoder(),
        private readonly ChatGPT $chatGPT = new ChatGPT(),
    ) {}

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    public function resolve(?string $name, ?string $city, ?string $country = null): array
    {
        $name = $this->clean($name);
        $city = $this->clean($city);
        $country = $this->clean($country);

        if ($name === null && $city === null) {
            return ['latitude' => null, 'longitude' => null];
        }

        // Nominatim potrebuje aspon nazov (pouzije aj mesto ako nazov, ak nazov chyba).
        $lookupName = $name ?? $city;
        $lookupCity = $city ?? '';

        $coords = $this->fromNominatim($lookupName, $lookupCity, $country);
        if ($coords['latitude'] !== null && $coords['longitude'] !== null) {
            return $coords;
        }

        $coords = $this->fromAi($lookupName, $lookupCity, $country);
        if ($coords['latitude'] !== null && $coords['longitude'] !== null) {
            return $coords;
        }

        return ['latitude' => null, 'longitude' => null];
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    private function fromNominatim(string $name, string $city, ?string $country): array
    {
        try {
            $result = $this->nominatimGeocoder->lookup($name, $city, $country);

            return [
                'latitude' => $this->floatOrNull($result['latitude'] ?? null),
                'longitude' => $this->floatOrNull($result['longitude'] ?? null),
            ];
        } catch (\Throwable $e) {
            Log::warning('PlaceCoordinateResolver: Nominatim lookup failed', ['error' => $e->getMessage()]);

            return ['latitude' => null, 'longitude' => null];
        }
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    private function fromAi(string $name, string $city, ?string $country): array
    {
        try {
            $payload = $this->chatGPT->extractVenueDetails([
                'name' => $name,
                'city' => $city,
                'country' => $country,
            ]);

            return [
                'latitude' => $this->floatOrNull($payload['latitude'] ?? null),
                'longitude' => $this->floatOrNull($payload['longitude'] ?? null),
            ];
        } catch (\Throwable $e) {
            Log::warning('PlaceCoordinateResolver: AI lookup failed', ['error' => $e->getMessage()]);

            return ['latitude' => null, 'longitude' => null];
        }
    }

    private function clean(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
