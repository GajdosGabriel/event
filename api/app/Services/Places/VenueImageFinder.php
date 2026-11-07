<?php

namespace App\Services\Places;

use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Imports\ImportedNameMatcher;
use App\Support\NationwideCoordinates;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fotky miesta z Wikimedia Commons.
 *
 * Hľadá sa podľa polohy, nie podľa textu: Wikidata vráti objekty okolo
 * súradníc miesta a z nich sa vezme ten, ktorého názov sedí s názvom miesta.
 * Textové hľadanie („kostol sv. Martina") by vrátilo prvý kostol toho mena
 * kdekoľvek na Slovensku.
 *
 * Z nájdeného objektu idú najprv jeho vlastné fotky (P18), potom súbory z
 * jeho kategórie na Commons (P373) — spolu najviac $max. Ku každej fotke sa
 * vráti autor a licencia, Commons ich pri CC BY-SA vyžaduje uvádzať.
 */
class VenueImageFinder
{
    /** Polomer hľadania pri presnej polohe a pri polohe len zo stredu obce (km). */
    private const RADIUS_EXACT_KM = 0.4;

    private const RADIUS_MUNICIPALITY_KM = 3.0;

    /** Fotka užšia ako toto je na avatar/galériu málo. */
    private const MIN_WIDTH = 600;

    /** Štandardná veľkosť zmenšeniny — originály a neštandardné šírky Wikimedia botom škrtí (429). */
    private const THUMB_WIDTH = 1280;

    private const FILLER_WORDS = ['a', 'v', 'vo', 'na', 'pri', 'z', 'zo', 's', 'so', 'do', 'u', 'pod', 'nad', 'the', 'of', 'and'];

    /** Slová, ktoré samy osebe miesto neidentifikujú — kostolov sv. X je v meste viac. */
    private const GENERIC_WORDS = [
        'kostol', 'kostolik', 'chram', 'kaplnka', 'bazilika', 'katedrala', 'dom', 'kultury', 'kulturny', 'centrum',
        'sv', 'farnost', 'farsky', 'fara', 'mestsky', 'mestske', 'obecny', 'obecne', 'divadlo', 'kino', 'klub',
        'sala', 'hala', 'park', 'namestie', 'ulica', 'hrad', 'zamok', 'kastiel', 'muzeum', 'galeria', 'kniznica',
        'skola', 'zakladna', 'stredna', 'gymnazium', 'evanjelicky', 'katolicky', 'reformovany', 'greckokatolicky',
        'rimskokatolicky', 'pravoslavny', 'cirkev', 'zbor', 'spolocensky', 'amfiteater',
    ];

    /** Veľkosť sakrálnej stavby — zhoda nesmie preskočiť o dva stupne. */
    private const BUILDING_SIZES = [
        'kaplnka' => 0, 'chapel' => 0, 'kostolik' => 1, 'kostol' => 2, 'chram' => 2, 'church' => 2,
        'bazilika' => 2, 'basilica' => 2, 'katedrala' => 3, 'cathedral' => 3,
    ];

    /** Anglické a iné všeobecné slová v názvoch kategórií Commons. */
    private const GENERIC_CATEGORY_WORDS = [
        'church', 'basilica', 'cathedral', 'chapel', 'saint', 'st', 'castle', 'palace', 'museum', 'square',
        'street', 'house', 'building', 'monastery', 'interior', 'exterior', 'kirche', 'templom', 'kostel',
    ];

    /** Súbory, ktoré nie sú fotkou miesta. */
    private const SKIP_FILE_PATTERN = '/\b(map|mapa|plan|pl[aá]n|logo|erb|coa|coat[_ ]of[_ ]arms|znak|flag|vlajka|sign|diagram|svg)\b/iu';

    /**
     * @return array<int, array{url: string, name: string, commons_title: string, commons_page: string, author: ?string, license: ?string, license_url: ?string, wikidata: string, label: string}>
     */
    public function find(Venue $venue, int $max = 4): array
    {
        $match = $this->matchItem($venue);

        if ($match === null) {
            return [];
        }

        $titles = $match['images'];

        if (count($titles) < $max && $match['category'] !== null) {
            $titles = array_merge($titles, $this->relevantCategoryFiles($match['category'], $match['labels'], $match['locality'], $max * 5));
        }

        $titles = array_values(array_unique(array_filter(
            $titles,
            // Len fotky: PNG na Commons sú väčšinou tabuľky, mapy a schémy.
            fn (string $title) => preg_match('/\.jpe?g$/i', $title) === 1
                && preg_match(self::SKIP_FILE_PATTERN, str_replace('_', ' ', $title)) !== 1,
        )));

        $images = [];

        foreach (array_chunk($titles, 10) as $chunk) {
            foreach ($this->imageInfo($chunk) as $info) {
                $images[] = $info + ['wikidata' => $match['id'], 'label' => $match['label']];

                if (count($images) >= $max) {
                    return $images;
                }
            }
        }

        return $images;
    }

    /**
     * Objekt Wikidata pri mieste, ktorého názov sedí s názvom miesta.
     *
     * @return array{id: string, label: string, labels: array<int, string>, images: array<int, string>, category: ?string, locality: ?string}|null
     */
    public function matchItem(Venue $venue): ?array
    {
        $lat = $venue->latitude;
        $lng = $venue->longitude;

        if ($lat === null || $lng === null || NationwideCoordinates::isPlaceholder($lat, $lng)) {
            return null;
        }

        if ($this->looksLikeAddress((string) $venue->name)) {
            return null;
        }

        // Miesto pomenované len obcou („Poprad", „Galanta") je zástupné —
        // fotka obce z jej kategórie by bola náhodná (tabuľky, autá, erby).
        if (Municipality::where('slug', ImportedNameMatcher::baseSlug((string) $venue->name))->exists()) {
            return null;
        }

        $municipality = $venue->municipality?->shortname ?? $venue->municipality?->fullname;

        // Bez priradenej obce poslúži upresnenie v zátvorke: „Katedrála sv. Martina (Bratislava)".
        if ($municipality === null && preg_match('/\(([^()]+)\)\s*$/u', (string) $venue->name, $paren) === 1) {
            $municipality = trim($paren[1]);
        }

        $approximate = $venue->coordinates_source === NationwideCoordinates::SOURCE;
        $radius = $approximate ? self::RADIUS_MUNICIPALITY_KM : self::RADIUS_EXACT_KM;

        $venueTokens = $this->tokens((string) $venue->name, $municipality);

        if ($venueTokens === []) {
            return null;
        }

        $best = null;

        foreach ($this->nearbyItems((float) $lat, (float) $lng, $radius) as $item) {
            // Obce, mestá a okresy (majú počet obyvateľov) nie sú objektom miesta.
            if ($item['settlement'] || ($item['images'] === [] && $item['category'] === null)) {
                continue;
            }

            foreach ($item['labels'] as $label) {
                $score = $this->score($venueTokens, $this->tokens($label, $municipality), $item['distance'], $approximate);

                if ($score === null) {
                    continue;
                }

                // Pri podobnej zhode vyhrá bližší objekt; pri polohe len zo stredu
                // obce vzdialenosť nič nehovorí.
                $rank = $score - $item['distance'] * ($approximate ? 0.01 : 0.5);

                if ($best === null || $rank > $best['rank']) {
                    $best = [
                        'rank' => $rank,
                        'id' => $item['id'],
                        'label' => $label,
                        'labels' => $item['labels'],
                        'images' => $item['images'],
                        'category' => $item['category'],
                        'locality' => $municipality,
                    ];
                }
            }
        }

        if ($best === null) {
            return null;
        }

        unset($best['rank']);

        return $best;
    }

    /**
     * Zhoda názvov 0–1, alebo null, keď objekt nie je tým miestom.
     *
     * Slová sa porovnávajú na prvých päť písmen, aby sedeli aj skloňované
     * tvary („Františka" × „František"). Musí sedieť aspoň jedno slovo, ktoré
     * miesto odlišuje od iných; názov zo samých všeobecných slov („Dom
     * kultúry") prejde len pri úplnej zhode oboma smermi a blízko.
     *
     * @param  array<int, string>  $venueTokens
     * @param  array<int, string>  $labelTokens
     */
    private function score(array $venueTokens, array $labelTokens, float $distanceKm, bool $approximate): ?float
    {
        if ($labelTokens === []) {
            return null;
        }

        $matched = array_values(array_filter($venueTokens, fn (string $token) => $this->contains($labelTokens, $token)));
        $ratio = count($matched) / count($venueTokens);
        $reverse = count(array_filter($labelTokens, fn (string $token) => $this->contains($venueTokens, $token))) / count($labelTokens);

        $distinctive = array_values(array_diff($venueTokens, self::GENERIC_WORDS));

        if ($distinctive === []) {
            $limit = $approximate ? self::RADIUS_MUNICIPALITY_KM : 0.15;

            return $ratio === 1.0 && $reverse === 1.0 && $distanceKm <= $limit ? 1.0 : null;
        }

        // Väčšina odlišujúcich slov miesta musí sedieť — inak „Spišské
        // diecézne múzeum" nájde diecézne múzeum v Nitre.
        $distinctiveMatched = array_values(array_diff($matched, self::GENERIC_WORDS));

        if ($distinctiveMatched === [] || count($distinctiveMatched) / count($distinctive) < 0.6) {
            return null;
        }

        // Kaplnka nie je kostol a kostolík nie je katedrála, aj keď majú
        // rovnaké zasvätenie.
        $venueSize = $this->buildingSize($venueTokens);
        $labelSize = $this->buildingSize($labelTokens);

        if ($venueSize !== null && $labelSize !== null && abs($venueSize - $labelSize) >= 2) {
            return null;
        }

        // Odlišujúce slová objektu, ktoré v názve miesta nie sú, znamenajú
        // iný objekt („Kostol sv. Martina" × „Kostol sv. Mikuláša").
        $labelDistinctive = array_values(array_diff($labelTokens, self::GENERIC_WORDS));
        $labelDistinctiveMatched = array_filter($labelDistinctive, fn (string $token) => $this->contains($venueTokens, $token));

        if ($labelDistinctive !== [] && count($labelDistinctiveMatched) / count($labelDistinctive) < 0.5) {
            return null;
        }

        return $ratio >= 0.6 ? ($ratio + $reverse) / 2 : null;
    }

    /** @param  array<int, string>  $tokens */
    private function buildingSize(array $tokens): ?int
    {
        foreach ($tokens as $token) {
            if (isset(self::BUILDING_SIZES[$token])) {
                return self::BUILDING_SIZES[$token];
            }
        }

        return null;
    }

    /**
     * Rovnaké slovo, alebo skloňovaný tvar: spoločný začiatok bez posledných
     * troch písmen kratšieho slova, aspoň štyri („Františka" × „František",
     * ale nie „Mariazell" × „Mariánsky").
     *
     * @param  array<int, string>  $tokens
     */
    private function contains(array $tokens, string $needle): bool
    {
        foreach ($tokens as $token) {
            if ($token === $needle) {
                return true;
            }

            $length = max(4, min(strlen($token), strlen($needle)) - 3);

            if (strlen($needle) >= 4 && strlen($token) >= 4 && strncmp($token, $needle, $length) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    private function tokens(string $value, ?string $municipality): array
    {
        $value = preg_replace('/\s*\([^()]*\)\s*$/u', '', $value) ?? $value;
        $slug = Str::slug($value, ' ');
        $locality = $municipality !== null ? explode(' ', Str::slug($municipality, ' ')) : [];

        $tokens = [];

        foreach (explode(' ', $slug) as $token) {
            $token = match (true) {
                in_array($token, ['svateho', 'svatej', 'svatych', 'svaty', 'svata', 'svate'], true) => 'sv',
                default => $token,
            };

            if ($token === '' || ctype_digit($token) || in_array($token, self::FILLER_WORDS, true)) {
                continue;
            }

            // Aj skloňovaná obec: „v Bratislave", „v Bratislavě".
            if ($locality !== [] && $this->contains($locality, $token)) {
                continue;
            }

            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    /** „Ružová 4, Trnava", „Prešov, Konštantínova 2" — adresa nie je objekt, fotka by bola tipom. */
    private function looksLikeAddress(string $name): bool
    {
        return preg_match('/\p{L}{3,}\.?\s+\d+[a-zA-Z]?([\/-]\d+[a-zA-Z]?)?\s*(,|$)/u', trim($name)) === 1;
    }

    /**
     * @return array<int, array{id: string, labels: array<int, string>, images: array<int, string>, category: ?string, distance: float}>
     */
    private function nearbyItems(float $lat, float $lng, float $radiusKm): array
    {
        $point = sprintf('Point(%F %F)', $lng, $lat);
        $sparql = <<<SPARQL
SELECT ?item ?label ?image ?category ?population ?dist WHERE {
  SERVICE wikibase:around {
    ?item wdt:P625 ?loc .
    bd:serviceParam wikibase:center "{$point}"^^geo:wktLiteral .
    bd:serviceParam wikibase:radius "{$radiusKm}" .
    bd:serviceParam wikibase:distance ?dist .
  }
  ?item rdfs:label ?label .
  FILTER(LANG(?label) IN ("sk", "cs", "hu", "en"))
  OPTIONAL { ?item wdt:P18 ?image . }
  OPTIONAL { ?item wdt:P373 ?category . }
  OPTIONAL { ?item wdt:P1082 ?population . }
}
LIMIT 2000
SPARQL;

        try {
            // Query service pri náraze vracia 429/5xx — jeden pokus navyše s pauzou.
            $response = $this->http()
                ->withHeaders(['Accept' => 'application/sparql-results+json'])
                ->retry(2, 3000, fn ($e) => ! $e instanceof RequestException || $e->response->status() === 429 || $e->response->serverError(), throw: false)
                ->get('https://query.wikidata.org/sparql', ['query' => $sparql, 'format' => 'json']);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        $items = [];

        foreach ((array) $response->json('results.bindings', []) as $row) {
            $uri = (string) ($row['item']['value'] ?? '');
            $id = Str::afterLast($uri, '/');

            if ($id === '') {
                continue;
            }

            $items[$id] ??= ['id' => $id, 'labels' => [], 'images' => [], 'category' => null, 'settlement' => false, 'distance' => (float) ($row['dist']['value'] ?? 0)];

            if (isset($row['population'])) {
                $items[$id]['settlement'] = true;
            }

            if (is_string($label = $row['label']['value'] ?? null)) {
                $items[$id]['labels'][] = $label;
            }

            if (is_string($image = $row['image']['value'] ?? null)) {
                $items[$id]['images'][] = 'File:'.rawurldecode(Str::afterLast($image, '/'));
            }

            if (is_string($category = $row['category']['value'] ?? null)) {
                $items[$id]['category'] = $category;
            }
        }

        return array_values(array_map(function (array $item) {
            $item['labels'] = array_values(array_unique($item['labels']));
            $item['images'] = array_values(array_unique($item['images']));

            return $item;
        }, $items));
    }

    /**
     * Súbory kategórie, ktorých názov spomína objekt.
     *
     * Kategória veľkej pamiatky obsahuje aj plagáty, exponáty a detaily
     * („1945 poster", „Clock museum"). Súbor prejde, len keď jeho názov
     * obsahuje odlišujúce slovo z kategórie alebo z názvu objektu.
     *
     * @param  array<int, string>  $labels
     * @return array<int, string>
     */
    private function relevantCategoryFiles(string $category, array $labels, ?string $locality, int $limit): array
    {
        $keywords = [];

        foreach (array_merge([$category], $labels) as $text) {
            foreach ($this->tokens($text, $locality) as $token) {
                if (strlen($token) >= 4 && ! in_array($token, self::GENERIC_WORDS, true) && ! in_array($token, self::GENERIC_CATEGORY_WORDS, true)) {
                    $keywords[] = $token;
                }
            }
        }

        $keywords = array_values(array_unique($keywords));

        if ($keywords === []) {
            return [];
        }

        return array_values(array_filter(
            $this->categoryFiles($category, $limit),
            function (string $title) use ($keywords) {
                $fileTokens = $this->tokens(preg_replace('/\.[a-z]+$/i', '', Str::after($title, ':')) ?? $title, null);

                foreach ($keywords as $keyword) {
                    if ($this->contains($fileTokens, $keyword)) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    /** @return array<int, string> */
    private function categoryFiles(string $category, int $limit): array
    {
        try {
            $response = $this->http()->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'list' => 'categorymembers',
                'cmtitle' => 'Category:'.$category,
                'cmtype' => 'file',
                'cmlimit' => $limit,
                'format' => 'json',
            ]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($member) => is_array($member) && is_string($member['title'] ?? null) ? $member['title'] : null,
            (array) $response->json('query.categorymembers', []),
        )));
    }

    /**
     * @param  array<int, string>  $titles
     * @return array<int, array{url: string, name: string, commons_title: string, commons_page: string, author: ?string, license: ?string, license_url: ?string}>
     */
    private function imageInfo(array $titles): array
    {
        try {
            $response = $this->http()->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'titles' => implode('|', $titles),
                'prop' => 'imageinfo',
                'iiprop' => 'url|size|mime|extmetadata',
                'iiurlwidth' => self::THUMB_WIDTH,
                'format' => 'json',
            ]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        $byTitle = [];

        foreach ((array) $response->json('query.pages', []) as $page) {
            $info = $page['imageinfo'][0] ?? null;
            $title = $page['title'] ?? null;

            if (! is_array($info) || ! is_string($title)) {
                continue;
            }

            if (! in_array($info['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true)
                || (int) ($info['width'] ?? 0) < self::MIN_WIDTH) {
                continue;
            }

            $url = $info['thumburl'] ?? $info['url'] ?? null;

            if (! is_string($url)) {
                continue;
            }

            $meta = (array) ($info['extmetadata'] ?? []);

            $byTitle[$title] = [
                'url' => $url,
                'name' => Str::after($title, 'File:'),
                'commons_title' => $title,
                'commons_page' => (string) ($info['descriptionurl'] ?? 'https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $title))),
                'author' => $this->plain($meta['Artist']['value'] ?? null),
                'license' => $this->plain($meta['LicenseShortName']['value'] ?? null),
                'license_url' => $this->plain($meta['LicenseUrl']['value'] ?? null),
            ];
        }

        // Poradie podľa vstupu — vlastné fotky objektu (P18) idú pred kategóriou.
        $ordered = [];

        foreach ($titles as $title) {
            $normalized = str_replace('_', ' ', $title);

            if (isset($byTitle[$normalized])) {
                $ordered[] = $byTitle[$normalized];
            }
        }

        return $ordered;
    }

    private function plain(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text !== '' ? Str::limit($text, 250, '…') : null;
    }

    /**
     * User-Agent podľa pravidiel Wikimedie: meno, verzia a kontakt (web).
     * Generický „bot" UA pri sťahovaní originálov dostáva 429.
     */
    public function userAgent(): string
    {
        return sprintf('%sBot/1.0 (%s; venue photos)', Str::studly((string) config('app.name', 'Event')), (string) config('app.url'));
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(20)
            ->acceptJson()
            ->withHeaders(['User-Agent' => $this->userAgent()]);
    }
}
