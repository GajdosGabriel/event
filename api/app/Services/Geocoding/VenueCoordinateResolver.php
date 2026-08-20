<?php

namespace App\Services\Geocoding;

use App\Services\OpenAI\ChatGPT;

/**
 * Rebrik zdrojov GPS suradnic miesta, od najpresnejsieho po najhrubsi.
 *
 *   1) `venue`        - zhoda budovy z Nominatimu (uz najdena volajucim)
 *   2) `address`      - strukturovany dotaz na adresu (ulica + PSC + obec)
 *   3) `ai`           - odhad modelu pre vseobecne znamy objekt
 *   4) `municipality` - stred obce
 *
 * Doteraz existoval len prvy stupen, a ked geokoder budovu netrafil (alebo jeho
 * nazov nesedel na zadany), miesto ostalo bez suradnic a na detaile chybala
 * mapa. Prazdna mapa je pritom horsia nez znacka v strede spravneho mesta,
 * ktoru operator dotiahne mysou -- preto rebrik konci az na obci.
 *
 * Zdroj sa vracia spolu so suradnicami, aby sa presnost nestratila: UI podla
 * neho oznaci priblizne polohy a operator vie, ktore este treba upresnit.
 *
 * Nikdy nevyhadzuje vynimku -- pri chybe vrati null suradnice, aby ulozenie
 * miesta nikdy nezlyhalo kvoli geokodovaniu.
 */
class VenueCoordinateResolver
{
    /**
     * Ako daleko od stredu obce smie lezat odhad modelu.
     *
     * Model obcas trafi budovu rovnakeho mena v inom okrese -- presne to, comu
     * pri geokoderi brani kontrola nazvu. Suradnice bez obce sa overit nedaju,
     * tak sa overia voci obci, ktoru uz pozname. 25 km pokryje aj rozlahle
     * mesta aj obce s odlahlymi castami, ale susedny okres uz nie.
     */
    private const AI_MAX_DISTANCE_KM = 25.0;

    public function __construct(
        private readonly NominatimGeocoder $nominatimGeocoder = new NominatimGeocoder(),
        private readonly ChatGPT $chatGPT = new ChatGPT(),
    ) {}

    /**
     * @param  ?float  $venueLat  suradnice uz najdenej zhody budovy (1. stupen)
     * @param  ?float  $aiLat  suradnice z uz hotoveho AI payloadu; ked chybaju
     *                         a je povolene `$askAi`, model sa oslovi tu
     * @return array{latitude:?float, longitude:?float, source:?string}
     */
    public function resolve(
        ?float $venueLat = null,
        ?float $venueLng = null,
        ?string $name = null,
        ?string $street = null,
        ?string $postcode = null,
        ?string $city = null,
        ?string $country = null,
        ?float $aiLat = null,
        ?float $aiLng = null,
        bool $askAi = false,
    ): array {
        if ($venueLat !== null && $venueLng !== null) {
            return $this->result($venueLat, $venueLng, 'venue');
        }

        $address = $this->safely(fn (): array => $this->nominatimGeocoder->lookupAddress($street, $postcode, $city, $country));
        if ($this->hasCoordinates($address)) {
            return $this->result((float) $address['latitude'], (float) $address['longitude'], 'address');
        }

        // Stred obce treba aj tak: bez neho sa AI odhad nema o co oprieť.
        $municipality = $this->safely(fn (): array => $this->nominatimGeocoder->lookupMunicipality($city, $country));

        if ($askAi && ($aiLat === null || $aiLng === null)) {
            [$aiLat, $aiLng] = $this->fromAi($name ?? $city, $city, $country);
        }

        if ($aiLat !== null && $aiLng !== null && $this->aiCoordinatesArePlausible($aiLat, $aiLng, $municipality)) {
            return $this->result($aiLat, $aiLng, 'ai');
        }

        if ($this->hasCoordinates($municipality)) {
            return $this->result((float) $municipality['latitude'], (float) $municipality['longitude'], 'municipality');
        }

        return $this->result(null, null, null);
    }

    /**
     * Prvy stupen rebrika samostatne: suradnice budovy z geokodera.
     *
     * Volajuci, ktory ma po ruke len model (ukladanie miesta), nema odkial
     * vziat vysledok detekcie -- najde si ho sam cez ten isty geokoder.
     *
     * @return array{latitude:?float, longitude:?float}
     */
    public function lookupVenue(string $name, string $city, ?string $country = null): array
    {
        $result = $this->safely(fn (): array => $this->nominatimGeocoder->lookup($name, $city, $country));

        return [
            'latitude' => $this->floatOrNull($result['latitude'] ?? null),
            'longitude' => $this->floatOrNull($result['longitude'] ?? null),
        ];
    }

    /**
     * Odhad modelu bez obce overit nevieme -- taky sa zahadzuje.
     *
     * Prijat neovereny odhad by znamenalo tichu zamenu presnosti za pokrytie:
     * miesto by dostalo suradnice, ktore vyzeraju presne, ale mozu ukazovat na
     * rovnomenny objekt o dva okresy dalej. Stred obce je v takom pripade
     * horsi udaj, ale poctivy.
     */
    private function aiCoordinatesArePlausible(float $latitude, float $longitude, array $municipality): bool
    {
        if (! $this->hasCoordinates($municipality)) {
            return false;
        }

        return $this->distanceKm(
            $latitude,
            $longitude,
            (float) $municipality['latitude'],
            (float) $municipality['longitude'],
        ) <= self::AI_MAX_DISTANCE_KM;
    }

    /**
     * @return array{0:?float, 1:?float}
     */
    private function fromAi(?string $name, ?string $city, ?string $country): array
    {
        $name = $this->clean($name);
        if ($name === null) {
            return [null, null];
        }

        try {
            $payload = $this->chatGPT->extractVenueDetails([
                'name' => $name,
                'city' => $city,
                'country' => $country,
            ]);
        } catch (\Throwable) {
            return [null, null];
        }

        return [
            $this->floatOrNull($payload['latitude'] ?? null),
            $this->floatOrNull($payload['longitude'] ?? null),
        ];
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  callable():array  $lookup
     */
    private function safely(callable $lookup): array
    {
        try {
            return $lookup();
        } catch (\Throwable) {
            return [];
        }
    }

    private function hasCoordinates(array $result): bool
    {
        return $this->floatOrNull($result['latitude'] ?? null) !== null
            && $this->floatOrNull($result['longitude'] ?? null) !== null;
    }

    /**
     * @return array{latitude:?float, longitude:?float, source:?string}
     */
    private function result(?float $latitude, ?float $longitude, ?string $source): array
    {
        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'source' => $source,
        ];
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
