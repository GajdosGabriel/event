<?php

namespace App\Services\Geocoding;

use App\Services\OpenAI\ChatGPT;

/**
 * Fail-safe doplnenie GPS suradnic miesta alebo kanalu pri ukladani.
 *
 * Samotny rebrik zdrojov (budova -> adresa -> AI -> stred obce) drzi
 * VenueCoordinateResolver; tato trieda je len tenka vrstva pre volajucich,
 * ktori maju po ruke model, nie vysledok detekcie.
 *
 * Nikdy nevyhadzuje vynimku -- pri chybe vrati null suradnice, aby ulozenie
 * miesta/kanalu nikdy nezlyhalo kvoli geokodovaniu.
 */
class PlaceCoordinateResolver
{
    private readonly VenueCoordinateResolver $venueCoordinateResolver;

    public function __construct(
        NominatimGeocoder $nominatimGeocoder = new NominatimGeocoder(),
        ChatGPT $chatGPT = new ChatGPT(),
        ?VenueCoordinateResolver $venueCoordinateResolver = null,
    ) {
        $this->venueCoordinateResolver = $venueCoordinateResolver
            ?? new VenueCoordinateResolver($nominatimGeocoder, $chatGPT);
    }

    /**
     * @return array{latitude: float|null, longitude: float|null, source: string|null}
     */
    public function resolve(
        ?string $name,
        ?string $city,
        ?string $country = null,
        ?string $street = null,
        ?string $postcode = null,
    ): array {
        $name = $this->clean($name);
        $city = $this->clean($city);
        $country = $this->clean($country);

        if ($name === null && $city === null) {
            return ['latitude' => null, 'longitude' => null, 'source' => null];
        }

        // Zhoda budovy je prvy stupen rebrika. Kontrolu nazvu, ktoru robi
        // detekcia, tu neaplikujeme -- nazov v DB uz je ten spravny a
        // porovnavat ho nie je s cim.
        $venue = $this->lookupVenue($name ?? $city, $city ?? '', $country);

        return $this->venueCoordinateResolver->resolve(
            venueLat: $venue['latitude'],
            venueLng: $venue['longitude'],
            name: $name ?? $city,
            street: $this->clean($street),
            postcode: $this->clean($postcode),
            city: $city,
            country: $country,
            askAi: true,
        );
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    private function lookupVenue(string $name, string $city, ?string $country): array
    {
        try {
            $result = $this->venueCoordinateResolver->lookupVenue($name, $city, $country);
        } catch (\Throwable) {
            return ['latitude' => null, 'longitude' => null];
        }

        return $result;
    }

    private function clean(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
