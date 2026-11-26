<?php

namespace App\Services\Imports;

use App\Models\Event;
use App\Models\Venue;
use App\Services\OpenAI\ChatGPT;
use Carbon\CarbonInterface;
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
 * Kandidát musí mať presne rovnaký začiatok a to isté konkrétne miesto
 * (nie zberné „Celé Slovensko" ani miesto pomenované len obcou — tam sa v ten
 * istý čas deje veľa rôznych vecí). Istá zhoda je veľmi podobný titulok alebo
 * text; pri strednej podobnosti rozhodne AI, ak je zapnutá
 * (IMPORTS_DEDUPE_WITH_AI). Rozdielne akcie toho istého organizátora v ten
 * istý čas a na tom istom mieste existujú (paralelné online kurzy kolégia),
 * preto samotný čas, miesto a kanál nestačia.
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

    /** Text kratší ako toľko slov sa podľa obsahu neporovnáva. */
    private const MIN_TEXT_TOKENS = 15;

    public function __construct(
        private readonly ChatGPT $chatGPT = new ChatGPT,
    ) {}

    public function find(string $name, string $bodyText, ?CarbonInterface $startAt, ?int $venueId, ?int $exceptId = null): ?Event
    {
        if ($startAt === null || $venueId === null || ! $this->isSpecificVenue($venueId)) {
            return null;
        }

        $candidates = Event::query()
            ->where('start_at', $startAt)
            ->where('venue_id', $venueId)
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $title = DuplicateResolver::tokens($name);
        $text = self::contentTokens($name.' '.$bodyText);
        $scored = [];

        foreach ($candidates as $event) {
            $titleScore = self::jaccard($title, DuplicateResolver::tokens((string) $event->name));
            $textScore = self::overlap($text, self::contentTokens($event->name.' '.html_entity_decode(strip_tags((string) $event->body))));

            if ($titleScore >= self::SURE_TITLE || $textScore >= self::SURE_TEXT) {
                return $event;
            }

            if ($titleScore >= self::ASK_TITLE || $textScore >= self::ASK_TEXT) {
                $scored[] = [$event, max($titleScore, $textScore)];
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

    /** Miesto, pri ktorom zhoda času niečo znamená: nie zberné, nie len obec. */
    private function isSpecificVenue(int $venueId): bool
    {
        $venue = Venue::query()->select(['id', 'name', 'village_id', 'category'])->find($venueId);

        if (! $venue instanceof Venue || $venue->category === 'fallback') {
            return false;
        }

        $village = $venue->village_id !== null
            ? DB::table('municipalities')->where('id', $venue->village_id)->value('fullname')
            : null;

        return $village === null || ImportedNameMatcher::baseSlug((string) $venue->name) !== ImportedNameMatcher::baseSlug((string) $village);
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

    /** @return array<int, string> dlhšie slová textu bez diakritiky, každé raz */
    private static function contentTokens(string $text): array
    {
        return array_values(array_unique(array_filter(
            DuplicateResolver::tokens($text),
            fn (string $token) => mb_strlen($token) >= 5,
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
