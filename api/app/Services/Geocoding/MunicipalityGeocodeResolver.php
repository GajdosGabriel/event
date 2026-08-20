<?php

namespace App\Services\Geocoding;

use App\Models\Municipality;
use App\Support\VenueKeywords;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Prelozi volny text miesta z importovaneho clanku na obec z ciselnika.
 *
 * Poradie zdrojov:
 *   1) MunicipalityResolver - presna zhoda v ciselniku, bez siete
 *   2) Orezanie slovenskej koncovky mesta ("v Bratislave" -> Bratislava),
 *      tiez bez siete a deterministicky
 *   3) Nominatim nad samotnym mestom - putnicke miesta, osady a mestske casti
 *      maju vlastny nazov, ale patria pod inu obec ("Skalka pri Trencine" nie
 *      je obec, OSM ju radi pod Trencin)
 *   4) Nominatim nad nazvom miesta - ked z clanku nevysvitlo ziadne mesto
 *
 * Prve dva kroky su zamerne offline: verejny Nominatim ma limit ~1 dotaz/s a
 * pri davkovom importe vracia 429. Ked by na nom zaviselo aj bezne mesto,
 * podujatia by pri throttlingu ticho koncili v zbernom "Cele Slovensko".
 *
 * Nikdy nevyhadzuje vynimku: pri chybe vrati null a volajuci pouzije zberne
 * "Cele Slovensko". Cachovanie riesia obe zavislosti samy (24 h).
 */
class MunicipalityGeocodeResolver
{
    private const COUNTRY = 'Slovensko';

    public function __construct(
        private readonly NominatimGeocoder $nominatimGeocoder = new NominatimGeocoder(),
        private readonly MunicipalityResolver $municipalityResolver = new MunicipalityResolver(),
    ) {}

    /**
     * @return array{village_id:int, city:string, postcode:?string, latitude:?float, longitude:?float}|null
     */
    public function resolve(?string $city, ?string $venueName = null): ?array
    {
        $city = $this->dropCountryWide($this->clean($city));
        $venueName = $this->dropCountryWide($this->clean($venueName));

        if ($city === null && $venueName === null) {
            return null;
        }

        // Lacne pokusy ako prve - vacsina clankov uvadza obec presne tak, ako
        // je v ciselniku, a vtedy netreba siet vobec. Nazov miesta je druhy
        // kandidat zamerne: putnicke miesto byva pomenovane rovno obcou
        // ("do Klokocova") a ziadne samostatne mesto v clanku nie je.
        foreach ([$city, $venueName] as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $villageId = $this->villageIdFor($candidate, null);
            if ($villageId !== null) {
                return [
                    'village_id' => $villageId,
                    'city'       => $candidate,
                    'postcode'   => null,
                    'latitude'   => null,
                    'longitude'  => null,
                ];
            }
        }

        // Slovenský lokál/genitív ("v Bratislave", "v Košiciach") sa dá zbaviť
        // koncovky deterministicky, bez siete. Beží to len nad mestom, nikdy
        // nad názvom miesta: "Kostol" by orezaním trafilo obec "Kostolné".
        if ($city !== null) {
            $villageId = $this->fromInflectedCity($city);
            if ($villageId !== null) {
                return [
                    'village_id' => $villageId,
                    'city'       => $city,
                    'postcode'   => null,
                    'latitude'   => null,
                    'longitude'  => null,
                ];
            }
        }

        foreach ($this->geocodeAttempts($city, $venueName) as [$lookupName, $lookupNear]) {
            $resolved = $this->fromNominatim($lookupName, $lookupNear);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * Obec výlučne z číselníka, nikdy zo siete.
     *
     * Na zúženie kontroly duplicít pred tým, než sa vôbec rozhodne, ako sa
     * miesto uloží: hľadanie podľa holého názvu musí byť ohraničené obcou,
     * inak by „Evanjelický kostol (Bratislava)“ splynul s tým v Liptovskom
     * Mikuláši. Keď mesto v číselníku nie je, vráti null a duplicita sa
     * dohľadá až neskôr, keď je obec známa.
     */
    public function catalogOnly(?string $city): ?int
    {
        $city = $this->dropCountryWide($this->clean($city));

        return $city !== null ? $this->villageIdFor($city, null) : null;
    }

    /**
     * @return list<array{0:string, 1:string}> dvojice [co hladat, v okoli coho]
     */
    private function geocodeAttempts(?string $city, ?string $venueName): array
    {
        $attempts = [];

        if ($city !== null) {
            $attempts[] = [$city, ''];
        }

        // Nazov miesta ma zmysel skusat len vtedy, ked hovori nieco ine ako
        // mesto - inak by sa zopakoval uz neuspesny dotaz.
        if ($venueName !== null && ($city === null || ! $this->isSameText($venueName, $city))) {
            $attempts[] = [$venueName, $city ?? ''];
        }

        return $attempts;
    }

    /**
     * @return array{village_id:int, city:string, postcode:?string, latitude:?float, longitude:?float}|null
     */
    private function fromNominatim(string $name, string $near): ?array
    {
        try {
            $result = $this->nominatimGeocoder->lookup($name, $near, self::COUNTRY);
        } catch (\Throwable $e) {
            Log::warning('MunicipalityGeocodeResolver: Nominatim lookup failed', [
                'name'  => $name,
                'near'  => $near,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        // Geokoder musi vratit konkretnu obec. Ked dotaz trafil len kraj alebo
        // okres, address nema city/town/village a taky vysledok je bezcenny -
        // radsej nechame podujatie spadnut na zberne miesto.
        $geocodedCity = $this->clean($result['city'] ?? null);
        if ($geocodedCity === null) {
            return null;
        }

        $postcode = $this->clean($result['postcode'] ?? null);

        $villageId = $this->villageIdFor($geocodedCity, $postcode);
        if ($villageId === null) {
            return null;
        }

        return [
            'village_id' => $villageId,
            'city'       => $geocodedCity,
            'postcode'   => $postcode,
            'latitude'   => $this->floatOrNull($result['latitude'] ?? null),
            'longitude'  => $this->floatOrNull($result['longitude'] ?? null),
        ];
    }

    /**
     * Zbaví mesto slovenskej koncovky orezaním 1–3 znakov a nájde obec, ktorá
     * takým kmeňom začína. Prísne mantinely držia hádanie na uzde:
     *   - kmeň musí mať aspoň 4 znaky,
     *   - kandidát smie byť nanajvýš o 2 znaky dlhší než kmeň — pri voľnejšom
     *     limite by bežné slovo "Kostol" trafilo obec "Kostolec",
     *   - z viacerých kandidátov vyhráva najkratší, takže výsledok je vždy
     *     rovnaký ("nitr" → Nitra, nie Nitrica).
     * Pôvodná verzia v ImportedVenueManager brala prvý LIKE zásah bez zoradenia
     * a vedela podujatie pripnúť do cudzieho okresu.
     */
    private function fromInflectedCity(string $city): ?int
    {
        // Názov budovy nie je obec. Bez tejto poistky by "Kaplnka" skončila
        // v obci Kaplna a "Kostole" v Kostolci — presné obce s takým menom
        // zachytí už zhoda v číselníku o krok vyššie, sem sa nedostanú.
        if (VenueKeywords::matches($city)) {
            return null;
        }

        $normalized = $this->normalize($city);
        $length = mb_strlen($normalized);

        if ($length < 5) {
            return null;
        }

        // Az styri znaky: koncovka "-iach" v "Kosiciach" je najdlhsia bezna.
        for ($cut = 1; $cut <= 4; $cut++) {
            $stem = mb_substr($normalized, 0, $length - $cut);
            if (mb_strlen($stem) < 4) {
                break;
            }

            $stemLength = mb_strlen($stem);
            $match = $this->catalog()
                ->filter(fn (array $row): bool => str_starts_with($row['normalized'], $stem)
                    && mb_strlen($row['normalized']) - $stemLength <= 2)
                ->sortBy(fn (array $row): int => mb_strlen($row['normalized']))
                ->first();

            if ($match !== null) {
                return $match['id'];
            }
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int, normalized:string}>
     */
    private function catalog(): Collection
    {
        return Cache::remember(
            'venue_detection:municipality_catalog',
            now()->addSeconds(max(0, (int) config('services.municipality_resolver.cache_ttl', 86400))),
            fn (): Collection => Municipality::query()
                ->where('use', true)
                ->get(['id', 'fullname', 'shortname'])
                ->flatMap(fn (Municipality $municipality): array => array_map(
                    fn (string $name): array => [
                        'id' => (int) $municipality->id,
                        'normalized' => $this->normalize($name),
                    ],
                    array_filter([(string) $municipality->fullname, (string) $municipality->shortname]),
                ))
                ->filter(fn (array $row): bool => $row['normalized'] !== '')
                ->values(),
        );
    }

    private function villageIdFor(string $city, ?string $postcode): ?int
    {
        try {
            $resolved = $this->municipalityResolver->resolve($city, $postcode);
        } catch (\Throwable $e) {
            Log::warning('MunicipalityGeocodeResolver: municipality lookup failed', [
                'city'  => $city,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $villageId = $resolved['village_id'] ?? null;

        return is_numeric($villageId) ? (int) $villageId : null;
    }

    private function isSameText(string $first, string $second): bool
    {
        return $this->normalize($first) === $this->normalize($second);
    }

    private function normalize(string $value): string
    {
        // Str::ascii, nie iconv //TRANSLIT: ten na tomto builde prepisuje
        // dĺžne na apostrof s písmenom („Trenčín“ → „Trenc'in“), takže po
        // nahradení nealfanumerických znakov vzniknú fiktívne tokeny
        // („trenc in“) a porovnávanie aj odvodzovanie kmeňa sa rozsype.
        // MunicipalityResolver používa Str::ascii už dnes.
        $ascii = Str::ascii($value);
        $ascii = mb_strtolower($ascii);
        $ascii = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? $ascii;

        return trim($ascii);
    }

    /**
     * Zahodí celoštátny zástupný názov, aby nešiel do geokódera.
     *
     * „Slovensko“ nie je obec — je to priznanie, že miesto nie je známe, a
     * patrí na zberné miesto. Nominatim ho však vždy niekam trafí: v produkcii
     * takto vzniklo miesto „Slovensko“ pripnuté na obec Celulózka, ktoré
     * používa sedem podujatí. Číselník ani VenueKeywords taký reťazec
     * nezachytia, lebo to nie je ani obec, ani názov budovy.
     */
    private function dropCountryWide(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalize($value);

        $countryWide = [
            'slovensko', 'cele slovensko', 'slovenska republika', 'sr', 'sk',
            'online', 'cely svet', 'viacero miest',
        ];

        return in_array($normalized, $countryWide, true) ? null : $value;
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
