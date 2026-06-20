<?php

namespace App\Services\Geocoding;

use App\Models\Municipality;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MunicipalityResolver
{
    public function resolve(?string $city, ?string $postcode = null): array
    {
        $normalizedCity = $this->normalizeText($city);
        if ($normalizedCity === null) {
            return $this->emptyResult();
        }

        return Cache::remember(
            $this->cacheKey($normalizedCity, $postcode),
            now()->addSeconds($this->cacheTtl()),
            function () use ($normalizedCity, $postcode) {
                $municipalities = Municipality::query()
                    ->where('use', true)
                    ->get(['id', 'fullname', 'shortname', 'zip']);

                $match = $this->matchByCityAndPostcode($municipalities, $normalizedCity, $postcode);
                $matchSource = $match !== null ? 'city_and_postcode' : null;

                if ($match === null) {
                    $match = $this->matchByCity($municipalities, $normalizedCity);
                    $matchSource = $match !== null ? 'city' : null;
                }

                if ($match === null) {
                    return $this->emptyResult();
                }

                return [
                    'village_id' => (int) $match->id,
                    'matched_municipality' => [
                        'id' => (int) $match->id,
                        'fullname' => (string) $match->fullname,
                        'shortname' => (string) $match->shortname,
                        'zip' => (string) $match->zip,
                    ],
                    'municipality_match' => [
                        'confidence' => $matchSource === 'city_and_postcode' ? 'high' : 'medium',
                        'match_source' => $matchSource,
                    ],
                ];
            }
        );
    }

    private function cacheKey(string $normalizedCity, ?string $postcode): string
    {
        return 'venue_detection:municipality:' . sha1($normalizedCity . '|' . ($this->normalizePostcode($postcode) ?? ''));
    }

    private function cacheTtl(): int
    {
        return max(0, (int) config('services.municipality_resolver.cache_ttl', 86400));
    }

    private function matchByCityAndPostcode(Collection $municipalities, string $normalizedCity, ?string $postcode): ?Municipality
    {
        $normalizedPostcode = $this->normalizePostcode($postcode);
        if ($normalizedPostcode === null) {
            return null;
        }

        return $municipalities->first(function (Municipality $municipality) use ($normalizedCity, $normalizedPostcode) {
            return $this->matchesCity($municipality, $normalizedCity)
                && $this->normalizePostcode((string) $municipality->zip) === $normalizedPostcode;
        });
    }

    private function matchByCity(Collection $municipalities, string $normalizedCity): ?Municipality
    {
        return $municipalities->first(fn (Municipality $municipality) => $this->matchesCity($municipality, $normalizedCity));
    }

    private function matchesCity(Municipality $municipality, string $normalizedCity): bool
    {
        return $this->normalizeText((string) $municipality->fullname) === $normalizedCity
            || $this->normalizeText((string) $municipality->shortname) === $normalizedCity;
    }

    private function normalizeText(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $ascii = Str::of($trimmed)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        return $ascii === '' ? null : $ascii;
    }

    private function normalizePostcode(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }

    private function emptyResult(): array
    {
        return [
            'village_id' => null,
            'matched_municipality' => null,
            'municipality_match' => [
                'confidence' => 'none',
                'match_source' => null,
            ],
        ];
    }
}
