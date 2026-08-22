<?php

namespace App\Services\Geocoding;

use App\Models\Municipality;

/**
 * Poloha z rozpísanej adresy vo formulári miesta.
 *
 * Editor miesta má obec z číselníka a ulicu ako voľný text — z toho sa dá
 * dopytovať Nominatim štruktúrovane a mapu posunúť hneď, ako operátor obec
 * vyberie. Rebríček je zámerne krátky: presná adresa, inak stred obce. Nič
 * lepšie z týchto dvoch polí nevytiahneme a odhad AI sem nepatrí — tá beží
 * až v detekcii miesta, ktorá pozná aj názov budovy.
 */
class AddressGeocoder
{
    private const COUNTRY = 'Slovensko';

    public function __construct(
        private readonly NominatimGeocoder $geocoder = new NominatimGeocoder(),
    ) {
    }

    /**
     * @return array{latitude:?float, longitude:?float, source:?string, city:?string, postcode:?string}
     */
    public function resolve(?int $villageId, ?string $street, ?string $postcode, ?string $country = null): array
    {
        $municipality = $villageId !== null ? Municipality::find($villageId) : null;
        $city = $municipality?->fullname;
        $zip = $this->trimmed($postcode) ?? $this->trimmed($municipality?->zip);
        $country = $this->trimmed($country) ?? self::COUNTRY;
        $street = $this->trimmed($street);

        $empty = ['latitude' => null, 'longitude' => null, 'source' => null, 'city' => $city, 'postcode' => $zip];

        if ($city === null) {
            return $empty;
        }

        if ($street !== null) {
            $hit = $this->geocoder->lookupAddress($street, $zip, $city, $country);

            if ($hit['latitude'] !== null && $hit['longitude'] !== null) {
                return [
                    'latitude' => $hit['latitude'],
                    'longitude' => $hit['longitude'],
                    'source' => 'address',
                    'city' => $city,
                    'postcode' => $zip,
                ];
            }
        }

        $hit = $this->geocoder->lookupMunicipality($city, $country);

        if ($hit['latitude'] === null || $hit['longitude'] === null) {
            return $empty;
        }

        return [
            'latitude' => $hit['latitude'],
            'longitude' => $hit['longitude'],
            'source' => 'municipality',
            'city' => $city,
            'postcode' => $zip,
        ];
    }

    private function trimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
