<?php

namespace App\Services\Imports;

use App\Models\Event;
use App\Models\Venue;
use App\Services\OpenAI\ChatGPT;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nájde podujatie, ktoré už existuje pod iným článkom.
 *
 * Tá istá akcia chodí z viacerých zdrojov a aj z jedného zdroja viackrát:
 * tkkbs.sk o nej píše pozvánku mesiac vopred, potom „O týždeň sa začne…"
 * a vyveska.sk ju má ešte raz od organizátora. Každý článok má inú URL aj iný
 * titulok, takže zhoda podľa zdroja či slugu ich nechytí — vo výpise potom
 * visí tá istá púť trikrát (11/2026: 34 takých skupín).
 *
 * Kandidát je z toho istého dňa a:
 *  - má presne rovnaký začiatok a to isté konkrétne miesto — istá zhoda je
 *    veľmi podobný titulok alebo text, pri strednej podobnosti rozhodne AI, ak
 *    je zapnutá (IMPORTS_DEDUPE_WITH_AI);
 *  - alebo jeden z článkov uvádza len obec či „Celé Slovensko" (tkkbs.sk píše
 *    „v Bratislave", organizátor „na Námestí slobody"), resp. len dátum bez
 *    času — vtedy rozhoduje výlučne veľmi podobný titulok alebo text.
 * Rozdielne akcie toho istého organizátora v ten istý čas a na tom istom mieste
 * existujú (paralelné online kurzy kolégia), preto samotný čas, miesto a kanál
 * nestačia. Dve presné rôzne miesta alebo dva rôzne časy sa nespájajú nikdy.
 */
class ImportedEventMatcher
{
    /** Podobnosť titulkov (Jaccard), od ktorej je to isté podujatie. */
    private const SURE_TITLE = 0.6;

    /** Podiel spoločných slov textu (k kratšiemu z dvoch), od ktorého je to isté podujatie. */
    private const SURE_TEXT = 0.6;

    /** Pod touto podobnosťou sa AI ani nepýta. */
    private const ASK_TEXT = 0.2;

    private const ASK_TITLE = 0.3;

    private const MAX_CANDIDATES = 10;

    /**
     * Podobnosť vzácnych slov (kosínus váhovaný IDF), od ktorej je to isté
     * podujatie. Články o tej istej akcii bývajú prepísané inými slovami, no
     * zdieľajú mená, názvy diel a miest; bežné „pozýva" či „Bratislave" váhu
     * nemajú. Na dátach 9 – 11/2026: pri presnom čase a mieste 0,45 chytí 11 z 38
     * ručne zlúčených duplicít a žiadnu rôznu akciu (paralelné kurzy kolégia
     * 0,29 – 0,39). Voľnejšia zhoda potrebuje viac.
     */
    private const SURE_RARE_EXACT = 0.45;

    private const SURE_RARE_LOOSE = 0.6;

    private const ASK_RARE = 0.2;

    private const DF_CACHE_KEY = 'imports.event_token_df';

    /** Časové pásmo, v ktorom zdroje píšu termíny. */
    private const LOCAL_TIMEZONE = 'Europe/Bratislava';

    /** Text kratší ako toľko slov sa podľa obsahu neporovnáva. */
    private const MIN_TEXT_TOKENS = 15;

    public function __construct(
        private readonly ChatGPT $chatGPT = new ChatGPT,
    ) {}

    public function find(string $name, string $bodyText, ?CarbonInterface $startAt, ?int $venueId, ?int $exceptId = null): ?Event
    {
        $venue = $venueId !== null ? $this->venue($venueId) : null;

        if ($startAt === null || $venue === null) {
            return null;
        }

        // Kandidáti z celého miestneho dňa: článok bez času má začiatok o polnoci.
        $day = $startAt->copy()->timezone(self::LOCAL_TIMEZONE);
        $candidates = Event::query()
            ->with('venue:id,name,village_id,category')
            ->whereBetween('start_at', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()])
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES * 3)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $title = DuplicateResolver::tokens($name);
        $text = self::contentTokens($name.' '.$bodyText);
        $specific = $this->isSpecific($venue);
        $scored = [];

        foreach ($candidates as $event) {
            $sameVenue = (int) $event->venue_id === $venue->id;
            $sameStart = $event->start_at?->eq($startAt) ?? false;
            $exact = $sameVenue && $sameStart && $specific;

            // Voľnejšia zhoda: jeden z článkov uvádza len obec či „Celé Slovensko",
            // alebo len dátum bez času. Dve presné rôzne miesta či dva rôzne
            // časy v ten deň sú dve akcie (ranné a večerné roráty).
            $loose = ! $exact
                && ($sameVenue || $this->compatibleVenues($venue, $event->venue))
                && ($sameStart || self::isDateOnly($startAt) || self::isDateOnly($event->start_at));

            if (! $exact && ! $loose) {
                continue;
            }

            $other = self::contentTokens($event->name.' '.html_entity_decode(strip_tags((string) $event->body)));
            $titleScore = self::jaccard($title, DuplicateResolver::tokens((string) $event->name));
            $textScore = self::overlap($text, $other);
            $rareScore = $this->rareWordsSimilarity($text, $other);

            if ($titleScore >= self::SURE_TITLE || $textScore >= self::SURE_TEXT
                || $rareScore >= ($exact ? self::SURE_RARE_EXACT : self::SURE_RARE_LOOSE)) {
                return $event;
            }

            // AI posudzuje len presnú zhodu času a miesta.
            if ($exact && ($titleScore >= self::ASK_TITLE || $textScore >= self::ASK_TEXT || $rareScore >= self::ASK_RARE)) {
                $scored[] = [$event, max($titleScore, $textScore, $rareScore)];
            }
        }

        if ($scored === [] || ! (bool) config('services.imports.dedupe_with_ai', false)) {
            return null;
        }

        usort($scored, fn (array $a, array $b) => $b[1] <=> $a[1]);
        $auto = (float) config('services.imports.dedupe_auto_threshold', 0.85);

        foreach (array_slice($scored, 0, 3) as [$event]) {
            try {
                $verdict = $this->chatGPT->judgeDuplicate('event', $this->describe($name, $bodyText, $startAt), $this->describe((string) $event->name, strip_tags((string) $event->body), $startAt));
            } catch (\Throwable $e) {
                Log::warning('Posúdenie duplicity podujatia zlyhalo.', ['name' => $name, 'candidate' => $event->id, 'error' => $e->getMessage()]);

                return null;
            }

            if ($verdict['same'] && $verdict['confidence'] >= $auto) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Zdrojovú adresu článku si zapamätá podujatie, ktoré ostáva — ďalší beh ju
     * nájde cez EventSourceLookup a podujatie znova nezaloží.
     */
    public static function rememberSource(Event $event, string $sourceUrl): void
    {
        $sourceUrl = trim($sourceUrl);

        if ($sourceUrl === '' || $sourceUrl === $event->orginal_source) {
            return;
        }

        $meta = is_array($event->meta) ? $event->meta : [];
        $sources = $meta['merged_sources'] ?? [];

        if (in_array($sourceUrl, $sources, true)) {
            return;
        }

        $meta['merged_sources'] = [...$sources, $sourceUrl];
        $event->forceFill(['meta' => $meta])->saveQuietly();
    }

    /**
     * Podujatie z nového článku má konkrétnejšie miesto, než aké dostalo z prvého
     * („Celé Slovensko" či len obec) — tak ho prevezme. Podujatie prevzatého
     * kanála upravuje už len jeho tím.
     */
    public function refineVenue(Event $event, int $venueId): void
    {
        $new = $this->venue($venueId);
        $old = $event->venue_id !== null ? $this->venue((int) $event->venue_id) : null;

        if ($new === null || $old === null || $new->id === $old->id || ! $this->isSpecific($new) || $this->isSpecific($old)
            || ! $this->compatibleVenues($new, $old)
            || ($event->canal_id !== null && DB::table('canals')->where('id', $event->canal_id)->whereNotNull('claimed_at')->exists())) {
            return;
        }

        $event->forceFill(['venue_id' => $new->id])->save();
    }

    private function venue(int $id): ?Venue
    {
        return Venue::query()->select(['id', 'name', 'village_id', 'category'])->find($id);
    }

    /** Miesto, pri ktorom zhoda času niečo znamená: nie zberné, nie len obec. */
    private function isSpecific(Venue $venue): bool
    {
        return ! $this->isNationwide($venue) && ! $this->isTownOnly($venue);
    }

    private function isNationwide(?Venue $venue): bool
    {
        return $venue?->category === 'fallback';
    }

    /** Miesto pomenované len obcou („Bratislava", „Poprad"). */
    private function isTownOnly(Venue $venue): bool
    {
        $village = $venue->village_id !== null
            ? DB::table('municipalities')->where('id', $venue->village_id)->value('fullname')
            : null;

        return $village !== null && ImportedNameMatcher::baseSlug((string) $venue->name) === ImportedNameMatcher::baseSlug((string) $village);
    }

    /** Jedno z miest je len obec či „Celé Slovensko" a druhé môže ležať v nej. */
    private function compatibleVenues(Venue $a, ?Venue $b): bool
    {
        if ($b === null) {
            return false;
        }

        if ($this->isNationwide($a) || $this->isNationwide($b)) {
            return true;
        }

        return ($this->isTownOnly($a) || $this->isTownOnly($b))
            && $a->village_id !== null && (int) $a->village_id === (int) $b->village_id;
    }

    /** Začiatok bez času — článok uviedol len dátum (polnoc miestneho času). */
    private static function isDateOnly(?CarbonInterface $startAt): bool
    {
        return $startAt !== null && $startAt->copy()->timezone(self::LOCAL_TIMEZONE)->format('H:i') === '00:00';
    }

    /** @return array<string, string|null> */
    private function describe(string $name, string $text, CarbonInterface $startAt): array
    {
        return [
            'Názov' => $name,
            'Začiatok' => $startAt->copy()->timezone(config('app.timezone'))->format('j. n. Y H:i'),
            'Text' => mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, 700),
        ];
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private function rareWordsSimilarity(array $a, array $b): float
    {
        if (count($a) < self::MIN_TEXT_TOKENS || count($b) < self::MIN_TEXT_TOKENS) {
            return 0.0;
        }

        ['n' => $n, 'df' => $df] = $this->documentFrequencies();
        $weight = fn (string $token): float => log(($n + 1) / (($df[$token] ?? 1) + 1));

        $wa = array_combine($a, array_map($weight, $a));
        $wb = array_combine($b, array_map($weight, $b));
        $dot = 0.0;

        foreach ($wa as $token => $value) {
            if (isset($wb[$token])) {
                $dot += $value * $wb[$token];
            }
        }

        $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $wa))) * sqrt(array_sum(array_map(fn ($v) => $v * $v, $wb)));

        return $norm > 0 ? $dot / $norm : 0.0;
    }

    /**
     * V koľkých podujatiach sa slovo vyskytuje — počíta sa raz za deň. Slová
     * z jediného podujatia sa neukladajú (chýbajúce = 1).
     *
     * @return array{n: int, df: array<string, int>}
     */
    private function documentFrequencies(): array
    {
        return Cache::remember(self::DF_CACHE_KEY, now()->addDay(), function (): array {
            $df = [];
            $n = 0;

            Event::withTrashed()->select(['id', 'name', 'body'])->chunkById(500, function ($events) use (&$df, &$n): void {
                foreach ($events as $event) {
                    $n++;
                    foreach (self::contentTokens($event->name.' '.html_entity_decode(strip_tags((string) $event->body))) as $token) {
                        $df[$token] = ($df[$token] ?? 0) + 1;
                    }
                }
            });

            return ['n' => $n, 'df' => array_filter($df, fn (int $count) => $count > 1)];
        });
    }

    /** @return array<int, string> dlhšie slová textu bez diakritiky, každé raz */
    private static function contentTokens(string $text): array
    {
        return array_values(array_unique(array_filter(
            DuplicateResolver::tokens($text),
            fn (string $token) => mb_strlen($token) >= 4,
        )));
    }

    /** @param  array<int, string>  $a  @param  array<int, string>  $b */
    private static function jaccard(array $a, array $b): float
    {
        $union = count(array_unique([...$a, ...$b]));

        return $union === 0 ? 0.0 : count(array_intersect(array_unique($a), array_unique($b))) / $union;
    }

    /** @param  array<int, string>  $a  @param  array<int, string>  $b */
    private static function overlap(array $a, array $b): float
    {
        $smaller = min(count($a), count($b));

        // Pár slov (len titulok, bez popisu) by dal vysokú zhodu náhodou.
        return $smaller < self::MIN_TEXT_TOKENS ? 0.0 : count(array_intersect($a, $b)) / $smaller;
    }
}
