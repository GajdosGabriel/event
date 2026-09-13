<?php

namespace App\Services\Imports;

use App\Services\OpenAI\TextLinkExtractor;
use App\Support\Url;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Dohľadá skutočný web organizátora z importu.
 *
 * Import dával každému organizátorovi web zdroja, z ktorého článok prišiel —
 * „Misijná škola Karola Wojtylu" mala v kontakte www.vyveska.sk. Ten patrí
 * výlučne zbernému kanálu zdroja. Organizátor dostane buď web, ktorý sa naozaj
 * dá doložiť, alebo žiadny:
 *
 *  1. odkaz v článku, ktorého doména sedí s názvom („www.mskw.sk"),
 *  2. web, ktorý AI vyčítala z textu — ale len keď doména v článku naozaj je,
 *  3. oficiálny web z Wikidata (P856) pre položku s rovnakým názvom,
 *  4. inak null.
 */
class OrganizerWebsiteFinder
{
    /** Zdroje importu a náš vlastný portál — nikdy nie web organizátora z článku. */
    private const SOURCE_HOSTS = ['vyveska.sk', 'tkkbs.sk', 'ecav.sk', 'hlascirkvi.sk'];

    /** Siete, skracovače a mapy — odkaz na ne nie je web organizátora. */
    private const FOREIGN_HOSTS = [
        'facebook.com', 'fb.com', 'fb.me', 'instagram.com', 'youtube.com', 'youtu.be',
        'twitter.com', 'x.com', 'tiktok.com', 'linkedin.com', 'threads.net',
        'google.com', 'goo.gl', 'forms.gle', 'bit.ly', 'wikipedia.org', 'wikidata.org',
    ];

    /** Spojky a predložky sa do skratky názvu nepočítajú („Misijná škola Karola Wojtylu" → mskw). */
    private const CONNECTORS = ['a', 'i', 'v', 'vo', 'na', 'pri', 'pre', 'z', 'zo', 's', 'so', 'sv', 'u', 'do', 'od', 'o'];

    public function __construct(
        private readonly TextLinkExtractor $linkExtractor = new TextLinkExtractor,
    ) {}

    /**
     * @param  array<int, mixed>  $links  odkazy z článku
     */
    public function find(string $organizerName, array $links = [], string $text = '', ?string $aiWebsite = null, ?string $sourceUrl = null): ?string
    {
        $organizerName = trim($organizerName);

        if ($organizerName === '') {
            return null;
        }

        $sourceHost = $this->bareHost($sourceUrl);
        $candidates = [];

        foreach ([...$links, ...$this->linkExtractor->extract($text)] as $link) {
            $origin = $this->origin(is_string($link) ? $link : null);
            $host = $this->bareHost($origin);

            if ($origin === null || $host === null || $host === $sourceHost
                || $this->isListed($host, self::SOURCE_HOSTS) || $this->isListed($host, self::FOREIGN_HOSTS)) {
                continue;
            }

            $candidates[$host] ??= $origin;
        }

        foreach ($candidates as $host => $origin) {
            if ($this->hostMatchesName($host, $organizerName)) {
                return $origin;
            }
        }

        // AI si web vie aj vymyslieť — berie sa len doména, ktorá v článku je.
        $aiHost = $this->bareHost($aiWebsite);
        if ($aiHost !== null && isset($candidates[$aiHost])) {
            return $candidates[$aiHost];
        }

        return $this->fromWikidata($organizerName);
    }

    /**
     * Doména sedí s názvom, keď je skratkou jeho slov („mskw"), alebo keď
     * obsahuje jeho výrazné slová — pri viacslovnom názve aspoň dve, aby
     * „kosice.sk" nesadlo na „Farnosť Košice-Sever".
     */
    private function hostMatchesName(string $host, string $name): bool
    {
        $labels = explode('.', $host);
        array_pop($labels);
        $compact = str_replace('-', '', implode('', $labels));

        $tokens = array_values(array_filter(
            explode('-', Str::slug($name)),
            fn (string $token) => $token !== '' && ! in_array($token, self::CONNECTORS, true),
        ));

        if ($tokens === [] || $compact === '') {
            return false;
        }

        $initials = implode('', array_map(fn (string $token) => $token[0], $tokens));
        if (strlen($initials) >= 3 && in_array($initials, $labels, true)) {
            return true;
        }

        $significant = array_filter($tokens, fn (string $token) => strlen($token) >= 4);
        if ($significant === []) {
            return false;
        }

        $matched = count(array_filter($significant, fn (string $token) => str_contains($compact, substr($token, 0, 5))));

        return $matched >= min(2, count($significant));
    }

    private function fromWikidata(string $name): ?string
    {
        if (! (bool) config('services.imports.organizer_website_lookup', true)) {
            return null;
        }

        $website = Cache::remember(
            'imports:organizer_website:'.md5(mb_strtolower($name)),
            now()->addDays(7),
            fn (): string => $this->lookupWikidata($name) ?? '',
        );

        return $website !== '' ? $website : null;
    }

    private function lookupWikidata(string $name): ?string
    {
        try {
            $search = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => (string) config('services.imports.user_agent', 'Event API importer')])
                ->get('https://www.wikidata.org/w/api.php', [
                    'action' => 'wbsearchentities',
                    'search' => $name,
                    'language' => 'sk',
                    'uselang' => 'sk',
                    'type' => 'item',
                    'limit' => 5,
                    'format' => 'json',
                ]);

            if (! $search->ok()) {
                return null;
            }

            $base = ImportedNameMatcher::baseSlug($name);
            $id = null;

            // Len položka s rovnakým názvom — hľadanie vráti aj príbuzné
            // („Katolícka univerzita" na „Teologická fakulta Katolíckej univerzity").
            foreach ((array) ($search->json('search') ?? []) as $item) {
                $label = is_array($item) ? ($item['label'] ?? $item['match']['text'] ?? null) : null;

                if (is_string($label) && ImportedNameMatcher::baseSlug($label) === $base) {
                    $id = is_string($item['id'] ?? null) ? $item['id'] : null;
                    break;
                }
            }

            if ($id === null) {
                return null;
            }

            $entity = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => (string) config('services.imports.user_agent', 'Event API importer')])
                ->get('https://www.wikidata.org/wiki/Special:EntityData/'.rawurlencode($id).'.json');

            if (! $entity->ok()) {
                return null;
            }

            $website = Url::normalize($entity->json('entities.'.$id.'.claims.P856.0.mainsnak.datavalue.value'));
            $host = $this->bareHost($website);

            return $host !== null && ! $this->isListed($host, self::FOREIGN_HOSTS) ? $website : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function origin(?string $url): ?string
    {
        $normalized = Url::normalize($url);

        if ($normalized === null) {
            return null;
        }

        return parse_url($normalized, PHP_URL_SCHEME).'://'.parse_url($normalized, PHP_URL_HOST);
    }

    private function bareHost(?string $url): ?string
    {
        $host = Url::host($url);

        return $host === null ? null : preg_replace('/^www\./', '', $host);
    }

    /**
     * @param  array<int, string>  $list
     */
    private function isListed(string $host, array $list): bool
    {
        foreach ($list as $listed) {
            if ($host === $listed || str_ends_with($host, '.'.$listed)) {
                return true;
            }
        }

        return false;
    }
}
