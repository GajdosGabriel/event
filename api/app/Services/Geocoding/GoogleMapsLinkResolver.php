<?php

namespace App\Services\Geocoding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extracts GPS coordinates from a Google Maps link found in event text.
 *
 * Many imported articles carry a "presne tu: https://maps.app.goo.gl/…" link that
 * pins the exact venue location. Nominatim/AI geocoding only work from a name+city
 * guess; this resolver reads the coordinates straight from the link, which is the
 * most reliable source when it is present.
 *
 * Postup:
 *   1) nájde v texte/URL zoznam google-maps odkazov
 *   2) short-linky (maps.app.goo.gl, goo.gl, g.co) rozbalí cez HTTP redirect
 *   3) z výslednej URL vyparsuje súradnice (@lat,lng / !3d!4d / q=lat,lng / consent continue=…)
 *
 * Nikdy nevyhadzuje výnimku — pri chybe vráti null súradnice, aby import nikdy
 * nezlyhal kvôli mapovému odkazu.
 */
class GoogleMapsLinkResolver
{
    private const SHORT_LINK_HOSTS = [
        'maps.app.goo.gl',
        'goo.gl',
        'g.co',
        'maps.google.com',
    ];

    private const MAX_REDIRECTS = 5;

    /**
     * Finds the first Google Maps link inside free text and resolves its coordinates.
     *
     * @return array{latitude: float|null, longitude: float|null}
     */
    public function fromText(?string $text): array
    {
        if (! is_string($text) || trim($text) === '') {
            return $this->empty();
        }

        return $this->fromUrls($this->extractMapUrls($text));
    }

    /**
     * Resolves coordinates from the first candidate URL that yields a valid pin.
     *
     * @param  array<int, string>  $urls
     * @return array{latitude: float|null, longitude: float|null}
     */
    public function fromUrls(array $urls): array
    {
        foreach ($urls as $url) {
            if (! is_string($url) || ! $this->isGoogleMapsUrl($url)) {
                continue;
            }

            $coords = $this->resolveUrl($url);
            if ($coords['latitude'] !== null && $coords['longitude'] !== null) {
                return $coords;
            }
        }

        return $this->empty();
    }

    /**
     * Resolves a single Google Maps URL: parses inline coordinates, following
     * short-link redirects (and consent pages) up to a small hop limit.
     *
     * @return array{latitude: float|null, longitude: float|null}
     */
    public function resolveUrl(string $url): array
    {
        $url = trim($url);
        if ($url === '' || ! $this->isGoogleMapsUrl($url)) {
            return $this->empty();
        }

        return Cache::remember(
            'maps_link:' . sha1($url),
            now()->addSeconds($this->cacheTtl()),
            fn () => $this->resolveUrlUncached($url)
        );
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    private function resolveUrlUncached(string $url): array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $coords = $this->parseCoordsFromUrl($current);
            if ($coords['latitude'] !== null && $coords['longitude'] !== null) {
                return $coords;
            }

            $next = $this->followRedirect($current);
            if ($next === null || $next === $current) {
                break;
            }

            $current = $next;
        }

        return $this->empty();
    }

    /**
     * Follows a single HTTP redirect hop without downloading the whole page,
     * returning the Location target (or a nested URL carried in a consent page).
     */
    private function followRedirect(string $url): ?string
    {
        try {
            $response = Http::withOptions(['allow_redirects' => false])
                ->timeout(10)
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('GoogleMapsLinkResolver: redirect fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        $location = $response->header('Location');
        if (is_string($location) && $location !== '') {
            return $this->absolutizeLocation($url, trim($location));
        }

        return null;
    }

    /**
     * Google consent redirects use a relative or same-host Location with the real
     * target URL-encoded in a "continue"/"q" query param; make it absolute so the
     * next hop (or the coordinate parser) can read it.
     */
    private function absolutizeLocation(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $scheme = (string) (parse_url($base, PHP_URL_SCHEME) ?: 'https');
        $host = (string) parse_url($base, PHP_URL_HOST);

        if ($host === '') {
            return $location;
        }

        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $location;
        }

        return $scheme . '://' . $host . '/' . $location;
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    private function parseCoordsFromUrl(string $url): array
    {
        // Decode once so nested/encoded URLs (consent "continue=…") and encoded
        // "@lat,lng" fragments become visible to the coordinate patterns below.
        $haystack = rawurldecode($url);

        $patterns = [
            '/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/',
            '/!3d(-?\d{1,3}\.\d+)!4d(-?\d{1,3}\.\d+)/',
            // /maps/search/48.29,+18.09  ·  /maps/place/48.29,18.09  ·  /maps/dir/…
            '#/maps/(?:search|place|dir)/(-?\d{1,3}\.\d+),\s*\+?\s*(-?\d{1,3}\.\d+)#',
            '/[?&](?:q|query|ll|sll|center|destination|daddr|saddr)=(-?\d{1,3}\.\d+),\s*\+?\s*(-?\d{1,3}\.\d+)/',
            '/[?&]q=loc:(-?\d{1,3}\.\d+),\s*\+?\s*(-?\d{1,3}\.\d+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack, $m) !== 1) {
                continue;
            }

            $lat = (float) $m[1];
            $lng = (float) $m[2];

            if ($this->isValidCoordinate($lat, $lng)) {
                return ['latitude' => $lat, 'longitude' => $lng];
            }
        }

        return $this->empty();
    }

    /**
     * @return array<int, string>
     */
    private function extractMapUrls(string $text): array
    {
        if (! preg_match_all('#https?://[^\s"\'<>)\]]+#iu', $text, $matches) || empty($matches[0])) {
            return [];
        }

        $urls = [];
        foreach ($matches[0] as $candidate) {
            $candidate = rtrim($candidate, '.,);]');
            if ($this->isGoogleMapsUrl($candidate)) {
                $urls[] = $candidate;
            }
        }

        return array_values(array_unique($urls));
    }

    private function isGoogleMapsUrl(string $url): bool
    {
        $host = mb_strtolower((string) parse_url(trim($url), PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if (in_array($host, self::SHORT_LINK_HOSTS, true)) {
            return true;
        }

        // google.com/maps, google.sk/maps, maps.google.*, …
        return (bool) preg_match('#(?:^|\.)google\.[a-z.]+$#', $host)
            && (str_contains(mb_strtolower($url), '/maps') || str_starts_with($host, 'maps.'));
    }

    private function isValidCoordinate(float $lat, float $lng): bool
    {
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return false;
        }

        // A 0,0 pin is the Null Island default and never a real Slovak venue.
        return ! ($lat === 0.0 && $lng === 0.0);
    }

    private function userAgent(): string
    {
        $configured = trim((string) config('services.imports.user_agent', ''));

        return $configured !== '' ? $configured : (trim((string) config('app.name', 'Event API')) . ' importer');
    }

    private function cacheTtl(): int
    {
        return max(0, (int) config('services.nominatim.cache_ttl', 86400));
    }

    /**
     * @return array{latitude: null, longitude: null}
     */
    private function empty(): array
    {
        return ['latitude' => null, 'longitude' => null];
    }
}
