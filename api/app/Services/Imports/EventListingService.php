<?php

namespace App\Services\Imports;

class EventListingService
{
    /**
     * Kalendár podujatí ECAV (ecav.sk/public/events) má 10 podujatí na
     * stranu a celý len pár strán. Prechádza sa celý bez ohľadu na globálne
     * `max_pages` (predvolene 1) — inak by sa podujatia z ďalších strán
     * dostali do Eventu až tesne pred konaním. Strop chráni pred cyklom.
     */
    private const ECAV_EVENTS_MAX_PAGES = 10;

    public function __construct(private readonly ImportPageFetcher $pageFetcher) {}

    /**
     * @return array<int, string>
     */
    public function listArticleUrls(string $listingUrl, int $maxPages = 1, ?int $limit = null): array
    {
        $urls = [];
        $pageUrl = $listingUrl;

        if ($this->isEcavEventsUrl($listingUrl)) {
            $maxPages = max($maxPages, self::ECAV_EVENTS_MAX_PAGES);
        }

        for ($page = 1; $page <= max(1, $maxPages); $page++) {
            $html = $this->pageFetcher->fetch($pageUrl);

            foreach ($this->extractArticleUrls($html, $pageUrl, $listingUrl) as $url) {
                if (! in_array($url, $urls, true)) {
                    $urls[] = $url;
                }

                if ($limit !== null && count($urls) >= $limit) {
                    return array_slice($urls, 0, $limit);
                }
            }

            $nextPageUrl = $this->extractNextPageUrl($html, $pageUrl, $listingUrl, $page + 1);
            if ($nextPageUrl === null) {
                break;
            }

            $pageUrl = $nextPageUrl;
        }

        return $limit !== null ? array_slice($urls, 0, $limit) : $urls;
    }

    /**
     * @return array<int, string>
     */
    private function extractArticleUrls(string $html, string $currentUrl, string $listingUrl): array
    {
        if ($this->isTkkbsUrl($listingUrl)) {
            $links = $this->extractLinks($html, $currentUrl);

            return array_values(array_filter($links, fn (string $url) => $this->isTkkbsArticleUrl($url)));
        }

        if ($this->isVyveskaUrl($listingUrl)) {
            return $this->extractVyveskaArticleUrls($html, $currentUrl);
        }

        if ($this->isEcavEventsUrl($listingUrl)) {
            return $this->extractEcavEventUrls($html, $currentUrl);
        }

        $links = $this->extractLinks($html, $currentUrl);

        return array_values(array_filter($links, fn (string $url) => $this->isEcavArticleUrl($url)));
    }

    private function extractNextPageUrl(string $html, string $currentUrl, string $listingUrl, int $pageNumber): ?string
    {
        if ($this->isTkkbsUrl($listingUrl)) {
            return $this->extractNextTkkbsPageUrl($html, $currentUrl, $pageNumber);
        }

        if ($this->isEcavEventsUrl($listingUrl)) {
            return $this->extractNextEcavEventsPageUrl($html, $currentUrl, $pageNumber);
        }

        return null;
    }

    /**
     * Kalendár ECAV ide od dnešného dňa dopredu; ďalšia strana („Novšie")
     * je odkaz `?page=N`. Posledná strana ho nemá.
     */
    private function extractNextEcavEventsPageUrl(string $html, string $currentUrl, int $pageNumber): ?string
    {
        foreach ($this->extractLinks($html, $currentUrl) as $url) {
            if (! $this->isEcavEventsUrl($url)) {
                continue;
            }

            if (preg_match('/[?&]page='.preg_quote((string) $pageNumber, '/').'([&#]|$)/', $url)) {
                return $url;
            }
        }

        return null;
    }

    private function extractNextTkkbsPageUrl(string $html, string $currentUrl, int $pageNumber): ?string
    {
        foreach ($this->extractLinks($html, $currentUrl) as $url) {
            if (! str_contains($url, 'search.php')) {
                continue;
            }

            if (preg_match('/[?&]rskolikata='.preg_quote((string) $pageNumber, '/').'([&#]|$)/', $url)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractLinks(string $html, string $baseUrl): array
    {
        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//a[@href]');
        $urls = [];

        foreach ($nodes ?: [] as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            if ($href === '') {
                continue;
            }

            $absoluteUrl = $this->absoluteUrl($baseUrl, $href);
            if ($absoluteUrl === null) {
                continue;
            }

            $urls[] = $absoluteUrl;
        }

        return array_values(array_unique($urls));
    }

    private function isEcavArticleUrl(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return str_starts_with($url, 'https://www.ecav.sk/aktuality/pozvanky/')
            && $path !== '/aktuality/pozvanky'
            && $path !== '/aktuality/pozvanky/';
    }

    /**
     * Kalendár podujatí, ktoré do ecav.sk zadávajú cirkevné zbory a
     * organizácie ECAV — iný zdroj než redakčné „Pozvánky" v aktualitách.
     */
    private function isEcavEventsUrl(string $url): bool
    {
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return $this->isEcavHost($url) && $path === '/public/events';
    }

    private function isEcavHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'ecav.sk' || $host === 'www.ecav.sk';
    }

    /**
     * Detail podujatia v kalendári ECAV je jednosegmentový slug na koreni
     * webu (ecav.sk/zvolanie-2026). Odkazy sa berú len z nadpisov kariet
     * podujatí — druhý odkaz v nadpise je web organizátora (target=_blank).
     *
     * @return array<int, string>
     */
    private function extractEcavEventUrls(string $html, string $baseUrl): array
    {
        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " event-card ")]//h5/a[@href][not(@target)]');
        $urls = [];

        foreach ($nodes ?: [] as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $absoluteUrl = $this->absoluteUrl($baseUrl, $href);
            if ($absoluteUrl === null || ! $this->isEcavHost($absoluteUrl)) {
                continue;
            }

            $path = trim((string) parse_url($absoluteUrl, PHP_URL_PATH), '/');
            if ($path !== '' && ! str_contains($path, '/')) {
                $urls[] = $absoluteUrl;
            }
        }

        return array_values(array_unique($urls));
    }

    private function isTkkbsUrl(string $url): bool
    {
        return str_contains((string) parse_url($url, PHP_URL_HOST), 'tkkbs.sk');
    }

    private function isVyveskaUrl(string $url): bool
    {
        return str_contains((string) parse_url($url, PHP_URL_HOST), 'vyveska.sk');
    }

    private function isTkkbsArticleUrl(string $url): bool
    {
        return str_contains($url, 'tkkbs.sk/view.php') && preg_match('/[?&]cisloclanku=\d+/', $url) === 1;
    }

    private function isVyveskaArticleUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! str_contains($host, 'vyveska.sk')) {
            return false;
        }

        // Event detail slugs are a single path segment — either the legacy
        // "slug.html" or the new (2026) trailing-slash "slug/" form. Section
        // pages ("zoznam-podujati/…", "kategoria/…") span multiple segments and
        // the listing root is empty, so both are rejected.
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path !== '' && ! str_contains($path, '/');
    }

    /**
     * @return array<int, string>
     */
    private function extractVyveskaArticleUrls(string $html, string $baseUrl): array
    {
        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($document);
        // The 2026 redesign renders each listed event as an <article class="…
        // border-b …"> whose title link sits in an <h4>. Navigation/footer links
        // live outside these cards, so scoping to them keeps the list clean.
        $nodes = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " border-b ")]//h4/a[@href]');
        $urls = [];

        foreach ($nodes ?: [] as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $absoluteUrl = $this->absoluteUrl($baseUrl, $href);

            if ($absoluteUrl === null || ! $this->isVyveskaArticleUrl($absoluteUrl)) {
                continue;
            }

            $urls[] = $absoluteUrl;
        }

        return array_values(array_unique($urls));
    }

    private function absoluteUrl(string $baseUrl, string $url): ?string
    {
        if ($url === '' || str_starts_with($url, '#') || str_starts_with(strtolower($url), 'javascript:')) {
            return null;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        $base = parse_url($baseUrl);
        if (! is_array($base) || empty($base['host'])) {
            return null;
        }

        $scheme = (string) ($base['scheme'] ?? 'https');
        $host = (string) $base['host'];

        if (str_starts_with($url, '//')) {
            return $scheme.':'.$url;
        }

        if (str_starts_with($url, '/')) {
            return $scheme.'://'.$host.$url;
        }

        $basePath = (string) ($base['path'] ?? '/');

        // Odkaz len s query („?page=2") patrí k tej istej ceste, nie k jej
        // adresáru — inak by stránkovanie ecav.sk/public/events viedlo na /public/.
        if (str_starts_with($url, '?')) {
            return $scheme.'://'.$host.($basePath !== '' ? $basePath : '/').$url;
        }
        $directory = preg_replace('#/[^/]*$#', '/', $basePath) ?? '/';

        return $scheme.'://'.$host.$directory.$url;
    }
}
