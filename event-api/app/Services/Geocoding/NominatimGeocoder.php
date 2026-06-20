<?php

namespace App\Services\Geocoding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NominatimGeocoder
{
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

        return Cache::remember(
            $this->cacheKey($queries),
            now()->addSeconds($this->cacheTtl()),
            function () use ($queries, $name, $city, $country) {
                try {
                    $bestResult = null;
                    $bestScore = PHP_INT_MIN;
                    $selectedQuery = null;
                    $candidateDebug = [];

                    foreach ($queries as $query) {
                        $response = Http::timeout(10)
                            ->acceptJson()
                            ->withHeaders([
                                'User-Agent' => $this->userAgent(),
                            ])
                            ->get($this->baseUrl() . '/search', [
                                'q' => $query,
                                'format' => 'jsonv2',
                                'limit' => 5,
                                'addressdetails' => 1,
                                'namedetails' => 1,
                            ]);

                        if (! $response->ok()) {
                            $candidateDebug[] = [
                                'query' => $query,
                                'http_ok' => false,
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

                        if ($bestScore >= 13) {
                            break;
                        }
                    }

                    if (! is_array($bestResult) || $bestScore < 5) {
                        return [
                            'result' => $this->emptyResult(),
                            'debug' => [
                                'queries' => $queries,
                                'best_score' => $bestScore === PHP_INT_MIN ? null : $bestScore,
                                'matched' => false,
                                'selected_query' => $selectedQuery,
                                'selected_candidate' => null,
                                'candidates' => $candidateDebug,
                                'reason' => 'score_below_threshold',
                            ],
                        ];
                    }

                    return [
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
                    ];
                } catch (\Throwable) {
                    return [
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
                    ];
                }
            }
        );
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

        foreach ($this->buildNameVariants($name) as $nameVariant) {
            $queries[] = $this->implodeQueryParts([$nameVariant, $city, $country]);
            $queries[] = $this->implodeQueryParts([$nameVariant, $city]);
            $queries[] = $this->implodeQueryParts([$nameVariant, $country]);
            $queries[] = $this->implodeQueryParts([$nameVariant]);
        }

        return array_values(array_unique(array_filter($queries)));
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
                $score += 10;
            } elseif (
                str_contains($normalizedRequestedName, $normalizedResultName)
                || str_contains($normalizedResultName, $normalizedRequestedName)
            ) {
                $score += 8;
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
        if ($this->normalizeText($city) !== null && $this->normalizeText($city) === $this->normalizeText($resultCity)) {
            $score += 3;
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
        $variants = [trim($name)];
        $normalized = $this->normalizeText($name) ?? '';
        $asciiName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $asciiName = is_string($asciiName) ? trim($asciiName) : '';

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

        if ($asciiName !== '') {
            $variants[] = $asciiName;
        }

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

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
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
