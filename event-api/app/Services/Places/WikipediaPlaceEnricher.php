<?php

namespace App\Services\Places;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WikipediaPlaceEnricher
{
    public function enrich(string $name, string $city, ?string $country = null): array
    {
        $query = $this->buildQuery($name, $city, $country);
        if ($query === '') {
            return $this->emptyResult();
        }

        return Cache::remember(
            $this->cacheKey($query),
            now()->addSeconds($this->cacheTtl()),
            function () use ($query) {
                $summaryContext = $this->fetchSummaryForLanguage('sk', $query)
                    ?? $this->fetchSummaryForLanguage('en', $query);

                if ($summaryContext === null) {
                    return $this->emptyResult();
                }

                $summary = $summaryContext['summary'];
                $lang = $summaryContext['lang'];
                $title = $summaryContext['title'];

                $description = $this->normalizeDescription($summary['extract'] ?? null);
                $longDescription = $this->getNormalizedLongDescription($summary['extract'] ?? null);
                $referenceUrl = $this->stringOrNull($summary['content_urls']['desktop']['page'] ?? null);
                $wikidataId = $this->stringOrNull($summary['wikibase_item'] ?? null);

                $officialWebsite = $this->websiteFromWikidata($wikidataId)
                    ?? $this->websiteFromWikipediaExternalLinks($lang, $title);

                // Get primary image and additional images
                $primaryImage = $this->stringOrNull($summary['thumbnail']['source'] ?? null);
                $additionalImages = $this->getImagesFromWikimedia($title, $lang);

                // Build image_urls array (primary first, then additional)
                $imageUrls = [];
                if ($primaryImage !== null) {
                    $imageUrls[] = $primaryImage;
                }
                $imageUrls = array_merge($imageUrls, $additionalImages);
                $imageUrls = array_values(array_unique($imageUrls)); // Deduplicate and reindex

                // Get logo and additional Wikidata info
                $wikidataInfo = $this->getLogoAndContactFromWikidata($wikidataId);

                return [
                    'official_name' => $this->stringOrNull($summary['title'] ?? null),
                    'object_description' => $description,
                    'long_description' => $longDescription,
                    'image_url' => $primaryImage,
                    'image_urls' => $imageUrls,
                    'logo_url' => $wikidataInfo['logo_url'],
                    'email' => $wikidataInfo['email'],
                    'phone' => $wikidataInfo['phone'],
                    'website' => $this->stringOrNull($officialWebsite),
                    'reference_url' => $referenceUrl,
                    'enrichment_source' => 'wikipedia',
                ];
            }
        );
    }

    private function fetchSummaryForLanguage(string $lang, string $query): ?array
    {
        $title = $this->searchTitle($lang, $query);
        if ($title === null) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get(sprintf('https://%s.wikipedia.org/api/rest_v1/page/summary/%s', $lang, rawurlencode($title)));

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                return null;
            }

            return [
                'lang' => $lang,
                'title' => $title,
                'summary' => $payload,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function searchTitle(string $lang, string $query): ?string
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get(sprintf('https://%s.wikipedia.org/w/api.php', $lang), [
                    'action' => 'query',
                    'list' => 'search',
                    'srsearch' => $query,
                    'format' => 'json',
                    'srlimit' => 1,
                ]);

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return null;
            }

            return $this->stringOrNull($payload['query']['search'][0]['title'] ?? null);
        } catch (\Throwable) {
            return null;
        }
    }

    private function websiteFromWikidata(?string $wikidataId): ?string
    {
        if (! is_string($wikidataId) || trim($wikidataId) === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get(sprintf('https://www.wikidata.org/wiki/Special:EntityData/%s.json', rawurlencode(trim($wikidataId))));

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return null;
            }

            $claim = $payload['entities'][$wikidataId]['claims']['P856'][0]['mainsnak']['datavalue']['value'] ?? null;

            return $this->stringOrNull(is_string($claim) ? $claim : null);
        } catch (\Throwable) {
            return null;
        }
    }

    private function websiteFromWikipediaExternalLinks(string $lang, string $title): ?string
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get(sprintf('https://%s.wikipedia.org/w/api.php', $lang), [
                    'action' => 'query',
                    'prop' => 'extlinks',
                    'titles' => $title,
                    'ellimit' => 20,
                    'format' => 'json',
                ]);

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return null;
            }

            $pages = $payload['query']['pages'] ?? null;
            if (! is_array($pages)) {
                return null;
            }

            foreach ($pages as $page) {
                if (! is_array($page) || ! is_array($page['extlinks'] ?? null)) {
                    continue;
                }

                foreach ($page['extlinks'] as $link) {
                    $url = $this->stringOrNull($link['*'] ?? null);
                    if ($url === null) {
                        continue;
                    }

                    $normalized = strtolower($url);
                    if (! str_starts_with($normalized, 'http://') && ! str_starts_with($normalized, 'https://')) {
                        continue;
                    }

                    if (str_contains($normalized, 'wikipedia.org') || str_contains($normalized, 'wikidata.org')) {
                        continue;
                    }

                    return $url;
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildQuery(string $name, string $city, ?string $country = null): string
    {
        return trim(implode(' ', array_filter([
            trim($name),
            trim($city),
            trim((string) $country),
        ], fn (string $value) => $value !== '')));
    }

    private function normalizeDescription(mixed $value): ?string
    {
        $text = $this->stringOrNull($value);
        if ($text === null) {
            return null;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $sentences = array_values(array_filter(array_map('trim', $sentences), fn (string $sentence) => $sentence !== ''));

        if ($sentences === []) {
            return null;
        }

        return implode(' ', array_slice($sentences, 0, 5));
    }

    private function cacheKey(string $query): string
    {
        return 'venue_detection:wikipedia:' . sha1($query);
    }

    private function cacheTtl(): int
    {
        return max(0, (int) config('services.wikipedia.cache_ttl', 86400));
    }

    private function userAgent(): string
    {
        $configuredUserAgent = trim((string) config('services.wikipedia.user_agent', ''));
        if ($configuredUserAgent !== '') {
            return $configuredUserAgent;
        }

        $appName = trim((string) config('app.name', 'Event API'));

        return $appName . ' wikipedia-enricher';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function emptyResult(): array
    {
        return [
            'official_name' => null,
            'object_description' => null,
            'long_description' => null,
            'image_url' => null,
            'image_urls' => [],
            'logo_url' => null,
            'email' => null,
            'phone' => null,
            'website' => null,
            'reference_url' => null,
            'enrichment_source' => null,
        ];
    }

    /**
     * Get additional images from Wikimedia Commons/gallery
     * Returns URLs of images related to the place
     */
    private function getImagesFromWikimedia(string $title, string $lang): array
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get(sprintf('https://%s.wikipedia.org/w/api.php', $lang), [
                    'action' => 'query',
                    'titles' => $title,
                    'prop' => 'images',
                    'imlimit' => 10,
                    'format' => 'json',
                ]);

            if (!$response->ok()) {
                return [];
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return [];
            }

            $pages = $payload['query']['pages'] ?? null;
            if (!is_array($pages)) {
                return [];
            }

            $images = [];
            foreach ($pages as $page) {
                if (!is_array($page) || !is_array($page['images'] ?? null)) {
                    continue;
                }

                foreach ($page['images'] as $imageInfo) {
                    $imageName = $imageInfo['title'] ?? null;
                    if (!is_string($imageName)) {
                        continue;
                    }

                    // Skip non-image formats and logos/icons
                    if (preg_match('/\.(svg|gif)$/i', $imageName)) {
                        continue;
                    }

                    $imageUrl = $this->getImageUrlFromTitle($imageName, $lang);
                    if ($imageUrl !== null) {
                        $images[] = $imageUrl;
                    }
                }
            }

            return $images;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve image file URL from Wikipedia image title
     */
    private function getImageUrlFromTitle(string $imageTitle, string $lang): ?string
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get('https://commons.wikimedia.org/w/api.php', [
                    'action' => 'query',
                    'titles' => $imageTitle,
                    'prop' => 'imageinfo',
                    'iiprop' => 'url',
                    'format' => 'json',
                ]);

            if (!$response->ok()) {
                return null;
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return null;
            }

            $pages = $payload['query']['pages'] ?? null;
            if (!is_array($pages)) {
                return null;
            }

            foreach ($pages as $page) {
                $imageinfo = $page['imageinfo'][0]['url'] ?? null;
                if (is_string($imageinfo) && $imageinfo !== '') {
                    return $imageinfo;
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get logo and contact information from Wikidata
     * P154: logo image
     * P585: email address
     * P1329: phone number
     */
    private function getLogoAndContactFromWikidata(?string $wikidataId): array
    {
        $result = [
            'logo_url' => null,
            'email' => null,
            'phone' => null,
        ];

        if (!is_string($wikidataId) || trim($wikidataId) === '') {
            return $result;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get(sprintf('https://www.wikidata.org/wiki/Special:EntityData/%s.json', rawurlencode(trim($wikidataId))));

            if (!$response->ok()) {
                return $result;
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return $result;
            }

            $entity = $payload['entities'][$wikidataId] ?? null;
            if (!is_array($entity)) {
                return $result;
            }

            $claims = $entity['claims'] ?? [];
            if (!is_array($claims)) {
                return $result;
            }

            // P154: logo image
            if (isset($claims['P154']) && is_array($claims['P154'])) {
                $logoFile = $claims['P154'][0]['mainsnak']['datavalue']['value'] ?? null;
                if (is_string($logoFile)) {
                    $logoUrl = $this->getWikimediaFileUrl($logoFile);
                    if ($logoUrl !== null) {
                        $result['logo_url'] = $logoUrl;
                    }
                }
            }

            // P585: email address (official email)
            if (isset($claims['P585']) && is_array($claims['P585'])) {
                $email = $claims['P585'][0]['mainsnak']['datavalue']['value'] ?? null;
                if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $result['email'] = $email;
                }
            }

            // P1329: phone number
            if (isset($claims['P1329']) && is_array($claims['P1329'])) {
                $phone = $claims['P1329'][0]['mainsnak']['datavalue']['value'] ?? null;
                if (is_string($phone) && trim($phone) !== '') {
                    $result['phone'] = trim($phone);
                }
            }

            return $result;
        } catch (\Throwable) {
            return $result;
        }
    }

    /**
     * Get Wikimedia file URL from file name
     */
    private function getWikimediaFileUrl(string $fileName): ?string
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get('https://commons.wikimedia.org/w/api.php', [
                    'action' => 'query',
                    'titles' => 'File:' . $fileName,
                    'prop' => 'imageinfo',
                    'iiprop' => 'url',
                    'format' => 'json',
                ]);

            if (!$response->ok()) {
                return null;
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return null;
            }

            $pages = $payload['query']['pages'] ?? null;
            if (!is_array($pages)) {
                return null;
            }

            foreach ($pages as $page) {
                $imageinfo = $page['imageinfo'][0]['url'] ?? null;
                if (is_string($imageinfo) && str_starts_with($imageinfo, 'http')) {
                    return $imageinfo;
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get longer description (more sentences than normalizeDescription)
     * Returns first 10-15 sentences or up to 500 chars
     */
    private function getNormalizedLongDescription(mixed $value): ?string
    {
        $text = $this->stringOrNull($value);
        if ($text === null) {
            return null;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $sentences = array_values(array_filter(array_map('trim', $sentences), fn (string $sentence) => $sentence !== ''));

        if ($sentences === []) {
            return null;
        }

        // Take up to 12 sentences or until 500 chars
        $longDesc = '';
        $sentenceCount = 0;
        foreach ($sentences as $sentence) {
            if ($sentenceCount >= 12 || strlen($longDesc) + strlen($sentence) > 500) {
                break;
            }
            $longDesc .= ($longDesc ? ' ' : '') . $sentence;
            $sentenceCount++;
        }

        return !empty($longDesc) ? $longDesc : null;
    }
}
