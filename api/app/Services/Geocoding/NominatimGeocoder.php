<?php

namespace App\Services\Geocoding;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NominatimGeocoder
{
    /** Cas posledneho dotazu na Nominatim -- drzi povinny odstup medzi volaniami. */
    private static ?float $lastRequestAt = null;

    /**
     * Od akeho skore sa kandidat berie ako zhoda.
     *
     * Je to zaroven bod, kde sa prehladavanie zastavi: varianty su zoradene od
     * najkonkretnejsieho, takze prvy prijatelny je aj najlepsi, aky rozumne
     * dostaneme. Kym sa prechadzali vsetky (az 30 dotazov), trvala detekcia pri
     * povinnom sekundovom odstupe pol minuty a vacsina dotazov skoncila na 429.
     */
    private const MIN_ACCEPTED_SCORE = 5;

    private const VENUE_TYPE_SYNONYMS = [
        'cultural_house' => ['kulturny dom', 'dom kultury', 'cultural house'],
        'center' => ['kulturne centrum', 'komunitne centrum', 'konferencne centrum', 'kongresove centrum', 'community center', 'conference center'],
        'theater' => ['divadlo', 'theatre', 'theater', 'dramaticke studio'],
        'cinema' => ['kino', 'cinema', 'movie theater', 'letne kino'],
        'arena' => ['sportova hala', 'hala', 'arena', 'stadion', 'zimny stadion', 'hall'],
        'amphitheater' => ['amfiteater', 'amphitheatre', 'amphitheater'],
        'church' => ['kostol', 'chram', 'katedrala', 'bazilika', 'kaplnka', 'church', 'cathedral', 'temple', 'basilica'],
        'synagogue' => ['synagoga', 'synagogue'],
        'museum_gallery' => ['muzeum', 'museum', 'galeria', 'gallery', 'vystavna sien'],
        'club' => ['klub', 'club', 'music club', 'bar', 'pub', 'jazz club'],
        'hotel_restaurant' => ['hotel', 'penzion', 'restauracia', 'restaurant', 'hostinec'],
        'school' => ['skola', 'gymnazium', 'aula', 'univerzita', 'university', 'campus'],
        'library' => ['kniznica', 'library'],
    ];

    private const VENUE_TYPE_PATTERNS = [
        'cultural_house' => ['/\\bkult\\w*(?:\\s+\\w+)?\\s+dom\\b/u', '/\\bdom\\s+kult\\w+\\b/u'],
        'center' => ['/\\bcentrum\\b/u', '/\\bcenter\\b/u'],
        'theater' => ['/\\bdivadl\\w*\\b/u', '/\\btheat(re|er)\\b/u'],
        'cinema' => ['/\\bkino\\b/u', '/\\bcinema\\b/u'],
        'arena' => ['/\\bhala\\b/u', '/\\barena\\b/u', '/\\bstadion\\b/u', '/\\bhall\\b/u'],
        'amphitheater' => ['/\\bamfiteat\\w*\\b/u', '/\\bamphitheat\\w*\\b/u'],
        'church' => ['/\\bkostol\\b/u', '/\\bchram\\b/u', '/\\bkatedr\\w*\\b/u', '/\\bchurch\\b/u', '/\\bcathedral\\b/u'],
        'synagogue' => ['/\\bsynagog\\w*\\b/u'],
        'museum_gallery' => ['/\\bmuze\\w*\\b/u', '/\\bgaler\\w*\\b/u', '/\\bgallery\\b/u', '/\\bmuseum\\b/u'],
        'club' => ['/\\bklub\\b/u', '/\\bclub\\b/u', '/\\bpub\\b/u', '/\\bbar\\b/u'],
        'hotel_restaurant' => ['/\\bhotel\\b/u', '/\\bpenzion\\b/u', '/\\brestaur\\w*\\b/u', '/\\brestaurant\\b/u'],
        'school' => ['/\\bskol\\w*\\b/u', '/\\bgymnaz\\w*\\b/u', '/\\baula\\b/u', '/\\buniverzit\\w*\\b/u', '/\\buniversity\\b/u', '/\\bcampus\\b/u'],
        'library' => ['/\\bkniznic\\w*\\b/u', '/\\blibrary\\b/u'],
    ];

    public function lookup(string $name, string $city, ?string $country = null): array
    {
        return $this->lookupDetailed($name, $city, $country)['result'];
    }

    /**
     * Vysledok sa cachuje len vtedy, ked ho geokoder naozaj dal.
     *
     * Verejny Nominatim pri prekroceni limitu vracia 429. Kym sa cachoval aj
     * taky "vysledok", jedno docasne odmietnutie znamenalo, ze miesto ostalo
     * bez suradnic na cely den -- a vyzeralo to, akoby geokoder nic nenasiel.
     */
    public function lookupDetailed(string $name, string $city, ?string $country = null): array
    {
        $queries = $this->buildQueries($name, $city, $country);
        if ($queries === []) {
            return [
                'result' => $this->emptyResult(),
                'debug' => [
                    'queries' => [],
                    'best_score' => null,
                    'matched' => false,
                    'selected_query' => null,
                    'selected_candidate' => null,
                    'candidates' => [],
                    'reason' => 'no_queries',
                ],
            ];
        }

        $cacheKey = $this->cacheKey($queries);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $outcome = $this->runQueries($queries, $name, $city, $country);

        if ($outcome['cacheable']) {
            Cache::put($cacheKey, $outcome['payload'], now()->addSeconds($this->cacheTtl()));
        }

        return $outcome['payload'];
    }

    /**
     * @return array{payload: array{result: array, debug: array}, cacheable: bool}
     */
    private function runQueries(array $queries, string $name, string $city, ?string $country): array
    {
        try {
            $bestResult = null;
            $bestScore = PHP_INT_MIN;
            $selectedQuery = null;
            $candidateDebug = [];
            $transportFailed = false;
            $budgetExhausted = false;
            $startedAt = microtime(true);
            $budget = (float) config('services.nominatim.max_seconds', 15);

            foreach ($queries as $query) {
                // Nazvove varianty su radene od najkonkretnejsieho a kazdy stoji
                // sekundu odstupu. Pri mieste, ktore v OSM nie je, by sa vsetkych
                // 30 prehladalo takmer minutu -- a vysledok by aj tak bol prazdny.
                // Po vycerpani rozpoctu prevezme miesto rebrik (adresa, obec).
                if ($budget > 0 && microtime(true) - $startedAt >= $budget) {
                    $budgetExhausted = true;
                    break;
                }

                $response = $this->politeGet([
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 5,
                    'addressdetails' => 1,
                    'namedetails' => 1,
                ]);

                if ($response === null || ! $response->ok()) {
                    $transportFailed = true;
                    $candidateDebug[] = [
                        'query' => $query,
                        'http_ok' => false,
                        'status' => $response?->status(),
                        'candidates' => [],
                    ];
                    continue;
                }

                $payload = $response->json();
                if (! is_array($payload)) {
                    $candidateDebug[] = [
                        'query' => $query,
                        'http_ok' => true,
                        'candidates' => [],
                    ];
                    continue;
                }

                $queryCandidates = [];
                foreach ($payload as $candidate) {
                    if (! is_array($candidate)) {
                        continue;
                    }

                    $score = $this->scoreResult($candidate, $name, $city, $country);
                    $queryCandidates[] = [
                        'name' => $this->resolveName($candidate),
                        'city' => $this->resolveCity(is_array($candidate['address'] ?? null) ? $candidate['address'] : []),
                        'street' => $this->buildStreet(is_array($candidate['address'] ?? null) ? $candidate['address'] : []),
                        'score' => $score,
                        'display_name' => $this->stringOrNull($candidate['display_name'] ?? null),
                    ];
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestResult = $candidate;
                        $selectedQuery = $query;
                    }
                }

                $candidateDebug[] = [
                    'query' => $query,
                    'http_ok' => true,
                    'candidates' => $queryCandidates,
                ];

                if ($bestScore >= self::MIN_ACCEPTED_SCORE) {
                    break;
                }
            }

            if (! is_array($bestResult) || $bestScore < self::MIN_ACCEPTED_SCORE) {
                return [
                    'payload' => [
                        'result' => $this->emptyResult(),
                        'debug' => [
                            'queries' => $queries,
                            'best_score' => $bestScore === PHP_INT_MIN ? null : $bestScore,
                            'matched' => false,
                            'selected_query' => $selectedQuery,
                            'selected_candidate' => null,
                            'candidates' => $candidateDebug,
                            'reason' => match (true) {
                                $transportFailed => 'geocoder_unavailable',
                                $budgetExhausted => 'budget_exhausted',
                                default => 'score_below_threshold',
                            },
                        ],
                    ],
                    // Prazdny vysledok z odmietnuteho dotazu nie je odpoved
                    // geokodera, len jeho ticho -- cachovat ho na den by
                    // zablokovalo aj pokus o pol minuty neskor.
                    'cacheable' => ! $transportFailed,
                ];
            }

            return [
                'payload' => [
                    'result' => $this->mapResult($bestResult),
                    'debug' => [
                        'queries' => $queries,
                        'best_score' => $bestScore,
                        'matched' => true,
                        'selected_query' => $selectedQuery,
                        'selected_candidate' => [
                            'name' => $this->resolveName($bestResult),
                            'city' => $this->resolveCity(is_array($bestResult['address'] ?? null) ? $bestResult['address'] : []),
                            'street' => $this->buildStreet(is_array($bestResult['address'] ?? null) ? $bestResult['address'] : []),
                            'display_name' => $this->stringOrNull($bestResult['display_name'] ?? null),
                            'score' => $bestScore,
                        ],
                        'candidates' => $candidateDebug,
                        'reason' => 'matched',
                    ],
                ],
                'cacheable' => true,
            ];
        } catch (\Throwable) {
            return [
                'payload' => [
                    'result' => $this->emptyResult(),
                    'debug' => [
                        'queries' => $queries,
                        'best_score' => null,
                        'matched' => false,
                        'selected_query' => null,
                        'selected_candidate' => null,
                        'candidates' => [],
                        'reason' => 'exception',
                    ],
                ],
                'cacheable' => false,
            ];
        }
    }

    /**
     * Jeden dotaz na Nominatim s dodrzanym odstupom medzi volaniami.
     *
     * Verejna instancia povoluje ~1 dotaz za sekundu. Detekcia ich pritom
     * posiela davku (nazvove varianty, adresa, obec), takze bez odstupu
     * skoncila vacsina z nich na 429 -- a namiesto vysledku prislo prazdno.
     * Odstup drzi staticky cas posledneho volania, co pokryva prave tu davku
     * v ramci jednej poziadavky.
     */
    private function politeGet(array $params): ?Response
    {
        $minInterval = (float) config('services.nominatim.min_interval', 1.0);

        if ($minInterval > 0 && self::$lastRequestAt !== null) {
            $wait = $minInterval - (microtime(true) - self::$lastRequestAt);
            if ($wait > 0) {
                usleep((int) round($wait * 1000000));
            }
        }

        self::$lastRequestAt = microtime(true);

        try {
            return Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get($this->baseUrl() . '/search', $params);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Suradnice adresy, nie budovy.
     *
     * Ked sa nepodari najst samotny objekt ("Kulturny dom" je v OSM stovky ráz),
     * adresa z AI stale staci na presnost domu alebo ulice. Ide to cez
     * strukturovany dotaz Nominatimu (street/city/postalcode/country), takze sa
     * obchadza cely aparat nazvovych variantov a skorovania -- ten riesi budovy.
     *
     * Jedina akceptacna podmienka je obec: vysledok v inej obci je bezcenny
     * rovnako ako zly objekt, lebo rovnaka ulica existuje v desiatkach miest.
     *
     * @return array{name:?string, street:?string, postcode:?string, city:?string, country:?string, latitude:?float, longitude:?float}
     */
    public function lookupAddress(?string $street, ?string $postcode, ?string $city, ?string $country = null): array
    {
        $street = $this->stringOrNull($street);
        $city = $this->stringOrNull($city);

        if ($street === null || $city === null) {
            return $this->emptyResult();
        }

        $params = array_filter([
            'street' => $street,
            'city' => $city,
            'postalcode' => $this->stringOrNull($postcode),
            'country' => $this->stringOrNull($country),
        ], fn (?string $value): bool => $value !== null);

        return $this->structuredLookup('address', $params, function (array $candidate) use ($city): bool {
            $address = is_array($candidate['address'] ?? null) ? $candidate['address'] : [];

            return $this->normalizeText($this->resolveCity($address)) === $this->normalizeText($city);
        });
    }

    /**
     * Stred obce -- posledna zachranna siet, ked o objekte nevieme nic presne.
     *
     * Znacka v strede mesta je pre operatora pouzitelnejsia nez prazdna mapa:
     * vidi spravne mesto a znacku dotiahne mysou. Vysledok sa cachuje podla
     * mena obce, takze je zdielany vsetkymi miestami v tej obci.
     *
     * @return array{name:?string, street:?string, postcode:?string, city:?string, country:?string, latitude:?float, longitude:?float}
     */
    public function lookupMunicipality(?string $city, ?string $country = null): array
    {
        $city = $this->stringOrNull($city);

        if ($city === null) {
            return $this->emptyResult();
        }

        $params = array_filter([
            'city' => $city,
            'country' => $this->stringOrNull($country),
        ], fn (?string $value): bool => $value !== null);

        return $this->structuredLookup('municipality', $params, function (array $candidate) use ($city): bool {
            $normalizedCity = $this->normalizeText($city);
            $address = is_array($candidate['address'] ?? null) ? $candidate['address'] : [];

            return $this->normalizeText($this->resolveName($candidate)) === $normalizedCity
                || $this->normalizeText($this->resolveCity($address)) === $normalizedCity;
        });
    }

    /**
     * Strukturovany dotaz na Nominatim s vlastnou akceptacnou podmienkou.
     *
     * Nikdy nevyhadzuje vynimku: pri chybe siete vrati prazdny vysledok, aby
     * detekcia ani ulozenie miesta nepadli kvoli geokoderu.
     *
     * @param  callable(array):bool  $accepts
     * @return array{name:?string, street:?string, postcode:?string, city:?string, country:?string, latitude:?float, longitude:?float}
     */
    private function structuredLookup(string $kind, array $params, callable $accepts): array
    {
        $cacheKey = 'venue_detection:nominatim_' . $kind . ':' . sha1(json_encode($params) ?: implode('|', $params));

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->politeGet($params + [
            'format' => 'jsonv2',
            'limit' => 3,
            'addressdetails' => 1,
            'namedetails' => 1,
        ]);

        // Odmietnuty dotaz nie je odpoved geokodera -- necachovat, inak by
        // jedno 429 drzalo miesto bez suradnic cely den.
        if ($response === null || ! $response->ok()) {
            return $this->emptyResult();
        }

        $result = $this->emptyResult();
        $payload = $response->json();

        if (is_array($payload)) {
            foreach ($payload as $candidate) {
                if (is_array($candidate) && $accepts($candidate)) {
                    $result = $this->mapResult($candidate);
                    break;
                }
            }
        }

        Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTtl()));

        return $result;
    }

    private function mapResult(array $result): array
    {
        $address = is_array($result['address'] ?? null) ? $result['address'] : [];

        return [
            'name' => $this->resolveName($result),
            'street' => $this->buildStreet($address),
            'postcode' => $this->stringOrNull($address['postcode'] ?? null),
            'city' => $this->resolveCity($address),
            'country' => $this->stringOrNull($address['country'] ?? null),
            'latitude' => $this->floatOrNull($result['lat'] ?? null),
            'longitude' => $this->floatOrNull($result['lon'] ?? null),
        ];
    }

    private function resolveCity(array $address): ?string
    {
        foreach (['city', 'town', 'village', 'municipality', 'hamlet'] as $field) {
            $value = $this->stringOrNull($address[$field] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function buildStreet(array $address): ?string
    {
        $streetName = $this->stringOrNull(
            $address['road']
            ?? $address['pedestrian']
            ?? $address['footway']
            ?? $address['street']
            ?? null
        );

        $houseNumber = $this->stringOrNull($address['house_number'] ?? null);

        if ($streetName === null && $houseNumber === null) {
            return null;
        }

        return trim(implode(' ', array_filter([$streetName, $houseNumber], fn ($value) => $value !== null && $value !== '')));
    }

    private function buildQueries(string $name, string $city, ?string $country = null): array
    {
        $queries = [];
        $hasCity = trim($city) !== '';

        foreach ($this->buildNameVariants($name) as $nameVariant) {
            // Druhový variant („kostol“, „amfiteáter“, „katedrála“) je len
            // typ budovy — sám o sebe neoznačuje nič konkrétne. Bez obce
            // v dotaze trafí ľubovoľnú stavbu toho druhu na Slovensku, a
            // keďže názov aj typ „sedia“, prejde aj cez skóre.
            //
            // Presne takto vznikli v produkcii chybné miesta: „Amfiteáter
            // Košice“ dostal obec Námestovo (dotaz „amfiteater, Slovensko“
            // → Amfiteáter Námestovo) a päť rôznych kostolov skončilo
            // s rovnakými súradnicami v Pečeniciach.
            //
            // Druhový variant preto smie ísť na Nominatim len s obcou.
            $isGeneric = $this->isGenericNameVariant($nameVariant);

            if ($hasCity) {
                $queries[] = $this->implodeQueryParts([$nameVariant, $city, $country]);
                $queries[] = $this->implodeQueryParts([$nameVariant, $city]);
            }

            if (! $isGeneric) {
                $queries[] = $this->implodeQueryParts([$nameVariant, $country]);
                $queries[] = $this->implodeQueryParts([$nameVariant]);
            }
        }

        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * Je variant iba holým druhovým označením stavby?
     *
     * Porovnáva sa proti synonymám z VENUE_TYPE_SYNONYMS — variant, ktorý sa
     * rovná niektorému z nich, nenesie žiadny rozlišujúci údaj. Variant, kde
     * je synonymum len časťou dlhšieho reťazca („kostol sv. Michala“,
     * „amfiteater namestovo“), generický nie je.
     */
    private function isGenericNameVariant(string $variant): bool
    {
        $normalized = $this->normalizeText($variant);
        if ($normalized === null) {
            return false;
        }

        foreach (self::VENUE_TYPE_SYNONYMS as $synonyms) {
            foreach ($synonyms as $synonym) {
                if ($this->normalizeText($synonym) === $normalized) {
                    return true;
                }
            }
        }

        return false;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.nominatim.base_url', 'https://nominatim.openstreetmap.org'), '/');
    }

    private function userAgent(): string
    {
        $configuredUserAgent = trim((string) config('services.nominatim.user_agent', ''));

        if ($configuredUserAgent !== '') {
            return $configuredUserAgent;
        }

        $appName = trim((string) config('app.name', 'Event API'));

        return $appName . ' geocoder';
    }

    private function cacheKey(array $queries): string
    {
        return 'venue_detection:nominatim:' . sha1(implode('|', $queries));
    }

    private function cacheTtl(): int
    {
        return max(0, (int) config('services.nominatim.cache_ttl', 86400));
    }

    private function stringOrNull(mixed $value): ?string
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

    private function emptyResult(): array
    {
        return [
            'name' => null,
            'street' => null,
            'postcode' => null,
            'city' => null,
            'country' => null,
            'latitude' => null,
            'longitude' => null,
        ];
    }

    private function resolveName(array $result): ?string
    {
        $name = $this->stringOrNull($result['name'] ?? null);
        if ($name !== null) {
            return $name;
        }

        $namedetails = is_array($result['namedetails'] ?? null) ? $result['namedetails'] : [];
        foreach (['name', 'official_name', 'short_name'] as $field) {
            $value = $this->stringOrNull($namedetails[$field] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        $displayName = $this->stringOrNull($result['display_name'] ?? null);
        if ($displayName === null) {
            return null;
        }

        $parts = preg_split('/\s*,\s*/u', $displayName) ?: [];
        $firstPart = trim((string) ($parts[0] ?? ''));

        return $firstPart !== '' ? $firstPart : null;
    }

    private function scoreResult(array $result, string $name, string $city, ?string $country = null): int
    {
        $score = 0;

        $resultName = $this->resolveName($result);
        $normalizedRequestedName = $this->normalizeText($name);
        $normalizedResultName = $this->normalizeText($resultName);

        if ($normalizedRequestedName !== null && $normalizedResultName !== null) {
            if ($normalizedRequestedName === $normalizedResultName) {
                // Rovnaky nazov nie je identita, ked je to holy druh stavby:
                // "Kulturny dom" sa presne rovna "Kulturnemu domu" v kazdej
                // druhej obci. Kym sa aj tu davalo 10 bodov, taky zasah prebil
                // aj pokutu za nespravnu obec a Sabinov dostal suradnice
                // kulturneho domu v Cervenici. Odlisujuci udaj nesie len typ a
                // obec -- tie sa bodujú nizsie.
                $score += $this->isGenericNameVariant($normalizedRequestedName) ? 0 : 10;
            } elseif (
                str_contains($normalizedRequestedName, $normalizedResultName)
                || str_contains($normalizedResultName, $normalizedRequestedName)
            ) {
                // Prekryv, ktorý je len druhovým slovom, nie je zhoda mena:
                // „Amfiteáter“ je podreťazcom „Amfiteáter Košice“ rovnako ako
                // podreťazcom každého iného amfiteátra na Slovensku. Typ
                // stavby odmeňuje samostatný bonus nižšie — počítať ho aj tu
                // znamenalo, že hociktorý kostol dostal 8 bodov za to, že je
                // kostol, a chybný výsledok prešiel cez prah.
                $shorter = mb_strlen($normalizedRequestedName) <= mb_strlen($normalizedResultName)
                    ? $normalizedRequestedName
                    : $normalizedResultName;

                $score += $this->isGenericNameVariant($shorter) ? 0 : 8;
            } else {
                $ignoredTokens = array_unique(array_filter([
                    ...$this->tokenize($city),
                    ...$this->tokenize($country),
                    'mesto',
                    'obec',
                    'ulica',
                    'namestie',
                ]));

                $sharedTokens = array_intersect(
                    array_values(array_diff($this->tokenize($name), $ignoredTokens)),
                    array_values(array_diff($this->tokenize($resultName), $ignoredTokens))
                );

                $score += count($sharedTokens) * 3;
            }
        }

        $requestedTypeGroups = $this->detectVenueTypeGroups($name);
        $resultTypeGroups = $this->detectVenueTypeGroups($resultName);

        if ($requestedTypeGroups !== [] && $resultTypeGroups !== []) {
            if (array_intersect($requestedTypeGroups, $resultTypeGroups) !== []) {
                $score += 4;
            } else {
                $score -= 5;
            }
        }

        $resultCity = $this->resolveCity(is_array($result['address'] ?? null) ? $result['address'] : []);
        $normalizedCity = $this->normalizeText($city);
        $normalizedResultCity = $this->normalizeText($resultCity);

        if ($normalizedCity !== null && $normalizedCity === $normalizedResultCity) {
            $score += 3;
        } elseif ($normalizedCity !== null && $normalizedResultCity !== null) {
            // Pýtali sme sa na miesto v Košiciach a dostali sme Námestovo —
            // to je dôkaz proti kandidátovi, nie neutrálny stav. Kým tu bola
            // len absencia bonusu, stačil druhový zásah v inom okrese na to,
            // aby prešiel. Pokuta je nižšia než bonus za presné meno, takže
            // mestskú časť („Bratislava“ vs „Ružinov“) stále prijmeme.
            $score -= 6;
        }

        $resultCountry = $this->stringOrNull($result['address']['country'] ?? null);
        if (
            $country !== null
            && $this->normalizeText($country) !== null
            && $this->normalizeText($country) === $this->normalizeText($resultCountry)
        ) {
            $score += 1;
        }

        if ($this->buildStreet(is_array($result['address'] ?? null) ? $result['address'] : []) !== null) {
            $score += 1;
        }

        return $score;
    }

    private function buildNameVariants(string $name): array
    {
        $normalized = $this->normalizeText($name) ?? '';
        // Tento variant ide priamo do dotazu na Nominatim, takže apostrofy
        // z iconv //TRANSLIT by sme posielali na OSM („Katedr'ala“).
        $asciiName = trim(Str::ascii($name));

        // Bezdiakritikovy tvar hned za povodnym: OSM ma slovenske objekty casto
        // zapisane bez diakritiky a prave on byva jedina zhoda ("Kulturny dom,
        // Sabinov"). Kym bol az na konci zoznamu, hladanie sa k nemu dostalo az
        // po dvadsiatich zbytocnych dotazoch.
        $variants = [trim($name), $asciiName];

        foreach (self::VENUE_TYPE_SYNONYMS as $group => $synonyms) {
            $group = $this->findMatchingVenueTypeGroup($normalized, $synonyms, $group);
            if ($group === null) {
                continue;
            }

            foreach ($synonyms as $synonym) {
                $variants[] = $synonym;

                if ($asciiName !== '') {
                    $variants[] = preg_replace('/\s+/u', ' ', trim($synonym . ' ' . $this->extractLikelyLocalitySuffix($asciiName))) ?: $synonym;
                }
            }
        }

        $nameWithoutLocality = $this->removeTrailingLocalityFromName($name);
        if ($nameWithoutLocality !== null) {
            $variants[] = $nameWithoutLocality;
        }

        $variants = array_merge($variants, $this->buildSplitLocationVariants($name));

        return array_values(array_unique(array_filter(array_map('trim', $variants))));
    }

    private function implodeQueryParts(array $parts): string
    {
        $filtered = array_values(array_filter(array_map(
            fn (mixed $part) => is_string($part) ? trim($part) : '',
            $parts
        ), fn (string $value) => $value !== ''));

        return implode(', ', $filtered);
    }

    private function normalizeText(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = $this->sanitizeUtf8(trim($value));
        if ($value === '') {
            return null;
        }

        // Str::ascii, nie iconv //TRANSLIT: ten na tomto builde prepisuje
        // dĺžne na apostrof s písmenom („Trenčín“ → „Trenc'in“) a po
        // nahradení nealfanumerických znakov z toho vzniknú fiktívne tokeny
        // („trenc in“), ktoré skresľujú skóre aj počet spoločných slov.
        $ascii = Str::ascii($value);
        if ($ascii !== '') {
            $value = $ascii;
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return $value !== '' ? $value : null;
    }

    private function sanitizeUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('//u', $value) === 1) {
            return trim($value);
        }

        $converted = mb_convert_encoding(
            $value,
            'UTF-8',
            'UTF-8, Windows-1250, ISO-8859-2, ISO-8859-1, Windows-1252'
        );

        if (! is_string($converted)) {
            $converted = $value;
        }

        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $converted);
        if ($clean !== false) {
            return trim($clean);
        }

        return trim($converted);
    }

    private function tokenize(?string $value): array
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return [];
        }

        return array_values(array_filter(
            explode(' ', $normalized),
            static fn (string $token): bool => $token !== '' && strlen($token) >= 3
        ));
    }

    private function detectVenueTypeGroups(?string $value): array
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return [];
        }

        $groups = [];
        foreach (self::VENUE_TYPE_SYNONYMS as $group => $synonyms) {
            if ($this->findMatchingVenueTypeGroup($normalized, $synonyms, $group) !== null) {
                $groups[] = $group;
            }
        }

        return array_values(array_unique($groups));
    }

    private function findMatchingVenueTypeGroup(string $normalized, array $synonyms, ?string $group = null): ?string
    {
        foreach ($synonyms as $synonym) {
            $normalizedSynonym = $this->normalizeText($synonym);
            if ($normalizedSynonym !== null && str_contains($normalized, $normalizedSynonym)) {
                return $group ?? 'matched';
            }
        }

        $patterns = self::VENUE_TYPE_PATTERNS[$group ?? ''] ?? [];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return $group ?? 'matched';
            }
        }

        return null;
    }

    private function extractLikelyLocalitySuffix(string $value): string
    {
        $parts = preg_split('/\s+/u', trim($value)) ?: [];
        if (count($parts) <= 2) {
            return $value;
        }

        return implode(' ', array_slice($parts, -2));
    }

    private function removeTrailingLocalityFromName(string $name): ?string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        foreach ([
            '/\s+[Vv]\s+[A-ZÁÄČĎÉÍĹĽŇÓÔŔŠŤÚÝŽ][\p{L}\-]+(?:\s+[A-ZÁÄČĎÉÍĹĽŇÓÔŔŠŤÚÝŽ][\p{L}\-]+)*$/u',
            '/\s+[Vv]o\s+[A-ZÁÄČĎÉÍĹĽŇÓÔŔŠŤÚÝŽ][\p{L}\-]+(?:\s+[A-ZÁÄČĎÉÍĹĽŇÓÔŔŠŤÚÝŽ][\p{L}\-]+)*$/u',
            '/\s+[Vv]e\s+[A-ZÁÄČĎÉÍĹĽŇÓÔŔŠŤÚÝŽ][\p{L}\-]+(?:\s+[A-ZÁÄČĎÉÍĹĽŇÓÔŔŠŤÚÝŽ][\p{L}\-]+)*$/u',
        ] as $pattern) {
            $candidate = preg_replace($pattern, '', $trimmed);
            if (is_string($candidate) && trim($candidate) !== '' && trim($candidate) !== $trimmed) {
                return trim($candidate);
            }
        }

        return null;
    }

    private function buildSplitLocationVariants(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));

        if (count($parts) < 2) {
            return [];
        }

        $variants = [];

        $lastToken = array_pop($parts);
        $baseName = trim(implode(' ', $parts));
        if ($baseName !== '' && is_string($lastToken) && $lastToken !== '') {
            $variants[] = $baseName . ', ' . $lastToken;
        }

        if (count($parts) >= 2) {
            $lastTwo = array_slice(array_merge($parts, [$lastToken]), -2);
            $prefix = array_slice(array_merge($parts, [$lastToken]), 0, -2);
            $prefixName = trim(implode(' ', $prefix));
            $suffixName = trim(implode(' ', $lastTwo));

            if ($prefixName !== '' && $suffixName !== '') {
                $variants[] = $prefixName . ', ' . $suffixName;
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }
}
