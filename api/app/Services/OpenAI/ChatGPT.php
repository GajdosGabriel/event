<?php

namespace App\Services\OpenAI;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Services\OpenAI\{PromptCanal, PromptCopywriter, PromptData, PromptProfile, PromptTags, PromptTextEditor, PromptVenue};

class ChatGPT
{
    /**
     * Koľko strán plagátu ide do jedného vision volania. Každý obrázok v „high"
     * detaile stojí rádovo tisíce tokenov a plagát má podstatné údaje takmer
     * vždy na prvej strane — ďalšie strany bývajú program alebo partneri.
     */
    private const MAX_VISION_IMAGES = 3;

    /** Strop vstupu na JEDNO volanie copywritera — viď extractCopywriter(). */
    private const MAX_COPYWRITER_INPUT_CHARS = 5000;

    /**
     * Koľko častí dlhého textu si necháme prepísať. Štyri časti sú 20 000
     * znakov, čo pokryje aj celý program púte; zvyšok už nie je popis podujatia,
     * ale zoškrabaná pätička webu a nemá zmysel za jeho rozšírenie platiť.
     */
    private const MAX_COPYWRITER_CHUNKS = 4;

    /**
     * Odkiaľ považujeme textovú vrstvu dokumentu za použiteľnú. Rovnaká hranica
     * ako v `PosterExtraction::hasUsableText()` — pod ňou ide o zvyšok po
     * neúspešnej extrakcii a text plagátu si necháme prepísať cez vision.
     */
    private const MIN_TEXT_LAYER_CHARS = 120;

    public function __construct(
        private readonly PromptData $promptData = new PromptData(),
        private readonly PromptCopywriter $promptCopywriter = new PromptCopywriter(),
        private readonly PromptVenue $promptVenue = new PromptVenue(),
        private readonly PromptCanal $promptCanal = new PromptCanal(),
        private readonly PromptTextEditor $promptTextEditor = new PromptTextEditor(),
        private readonly PromptProfile $promptProfile = new PromptProfile(),
        private readonly PromptTags $promptTags = new PromptTags(),
    ) {}

    /**
     * Obsahové štítky podujatia z pevného číselníka.
     *
     * Povolené slugy idú do JSON schémy ako `enum`, takže model nemá ako vrátiť
     * hodnotu mimo číselníka. Rozsahy a počty sa tu NEorezávajú — surová
     * odpoveď patrí volajúcemu (App\Services\Tags\EventTagger), ktorý jediný
     * vie, čo je v databáze ešte platné.
     *
     * @param  array<string, array<int, array{slug: string, name: string}>>  $catalog  facet => štítky
     * @return array{tags: array<int, array{slug: string, confidence: mixed}>, suggested: array<int, string>}
     */
    public function extractTags(string $text, array $catalog): array
    {
        $allowedSlugs = [];

        foreach ($catalog as $tags) {
            foreach ($tags as $tag) {
                $allowedSlugs[] = $tag['slug'];
            }
        }

        if ($allowedSlugs === []) {
            throw new \RuntimeException('Ciselnik stitkov je prazdny.');
        }

        $content = $this->chatComplete(
            'gpt-4o-mini',
            0,
            $this->promptTags->prompt($this->sanitizeUtf8($text), $catalog),
            $this->promptTags->jsonSchema($allowedSlugs),
        );

        $data = $this->decodeJson($content);

        // normalizeResponseData() sa tu zámerne NEvolá — sploštila by polia
        // na reťazec oddelený čiarkami.
        $validator = Validator::make($data, $this->promptTags->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        return [
            'tags' => $data['tags'] ?? [],
            'suggested' => $data['suggested'] ?? [],
        ];
    }

    public function extractData(array|string $input, ?Carbon $referenceDate = null): array
    {
        $text = $this->normalizeInput($input);
        $referenceDate ??= Carbon::now(config('app.timezone', 'Europe/Bratislava'));

        $content = $this->chatComplete('gpt-4o-mini', 0, $this->promptData->prompt($text, $referenceDate), $this->promptData->jsonSchema());
        $data = $this->decodeJson($content);
        $data = $this->normalizeResponseData($data);
        $data = $this->applyEventDateTimeFallbackFromText($data, $text);
        $data = $this->enforceCurrentOrFutureYear($data, $referenceDate);

        $validator = Validator::make($data, $this->promptData->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        return $data;
    }

    /**
     * Extrakcia z plagátu: textová vrstva + obrázky.
     *
     * `extractData()` vidí len text, čo pri plagáte zlyhá — plagát býva grafika
     * bez textovej vrstvy (sken, JPG, „obrázkové" PDF) a textová extrakcia z neho
     * vráti prázdno alebo pár útržkov z pätičky. Preto sa k rovnakému promptu
     * a rovnakej JSON schéme priložia aj obrázky a model si termín, miesto
     * aj organizátora prečíta priamo z grafiky.
     *
     * Bez obrázkov je to presne `extractData()` — volajúci sa nemusí rozhodovať.
     *
     * @param  array<int, string>  $imageDataUrls  `data:image/...;base64,…` URL
     */
    public function extractDataFromPoster(string $text, array $imageDataUrls = [], ?Carbon $referenceDate = null): array
    {
        $imageDataUrls = array_values(array_filter(
            $imageDataUrls,
            static fn ($url) => is_string($url) && $url !== '',
        ));

        if ($imageDataUrls === []) {
            return $this->extractData($text, $referenceDate);
        }

        $text = $this->sanitizeUtf8(trim($text));
        $referenceDate ??= Carbon::now(config('app.timezone', 'Europe/Bratislava'));

        // Prepis plagátu pýtame len vtedy, keď textová vrstva chýba alebo je
        // útržkovitá — inak popis vznikne z nej a prepis by len platený výstup
        // predĺžil o to isté, čo už máme presnejšie.
        $withPosterText = mb_strlen($text) < self::MIN_TEXT_LAYER_CHARS;

        $messages = $this->promptData->prompt(
            $text !== '' ? $text : '(Dokument nemá použiteľnú textovú vrstvu — všetky údaje čítaj z priložených obrázkov plagátu.)',
            $referenceDate,
            $withPosterText,
        );

        // Vision je pomalšie než textové volanie — plagát na výšku v „high"
        // detaile sa rozpadá na desiatky dlaždíc. 60 s default by tu padalo.
        $content = $this->chatComplete(
            'gpt-4o-mini',
            0,
            $this->attachImages($messages, $imageDataUrls),
            $this->promptData->jsonSchema($withPosterText),
            timeout: 120,
        );

        $data = $this->decodeJson($content);
        $data = $this->normalizeResponseData($data);
        $data = $this->applyEventDateTimeFallbackFromText($data, $text);
        $data = $this->enforceCurrentOrFutureYear($data, $referenceDate);

        $validator = Validator::make($data, $this->promptData->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        return $data;
    }

    /**
     * Obrázky sa vešajú na poslednú `user` správu. Chat Completions ich prijíma
     * len ako pole blokov `{type: text|image_url}`, nie ako obyčajný reťazec,
     * takže sa pôvodný text zabalí do prvého bloku.
     *
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<int, string>  $imageDataUrls
     * @return array<int, array{role: string, content: mixed}>
     */
    private function attachImages(array $messages, array $imageDataUrls): array
    {
        $lastUserIndex = null;

        foreach ($messages as $index => $message) {
            if (($message['role'] ?? '') === 'user') {
                $lastUserIndex = $index;
            }
        }

        if ($lastUserIndex === null) {
            return $messages;
        }

        $parts = [['type' => 'text', 'text' => (string) $messages[$lastUserIndex]['content']]];

        foreach (array_slice($imageDataUrls, 0, self::MAX_VISION_IMAGES) as $dataUrl) {
            $parts[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $dataUrl, 'detail' => 'high'],
            ];
        }

        $messages[$lastUserIndex]['content'] = $parts;

        return $messages;
    }

    /**
     * Rozšírenie textu podujatia do HTML.
     *
     * Copywriter má text ROZŠÍRIŤ, takže výstup je dlhší než vstup. Pri celom
     * programe púte (harmonogram, ceny, strava — cez 7 000 znakov) by odpoveď
     * na jedno volanie narazila na strop tokenov a vrátil by sa useknutý JSON.
     *
     * Vstup sa preto NEorezáva ani nezahadzuje — výsledok nahrádza celé telo
     * podujatia a z orezaného vstupu by ticho zmizol koniec programu. Dlhý text
     * ide po častiach a HTML sa poskladá späť; nezachytiteľný zvyšok (viac než
     * MAX_COPYWRITER_CHUNKS častí) sa pripojí aspoň ako odstavce.
     */
    public function extractCopywriter(array|string $input): array
    {
        $text = $this->normalizeInput($input);

        if (mb_strlen($text) > self::MAX_COPYWRITER_INPUT_CHARS) {
            return ['event_body' => $this->copywriteLongText($text)];
        }

        return $this->copywriteChunk($text);
    }

    /**
     * Jedno volanie copywritera nad textom, ktorý sa zmestí do limitu.
     *
     * @return array{event_body?: string|null}
     */
    private function copywriteChunk(string $text, bool $partial = false): array
    {
        $content = $this->chatComplete('gpt-4o-mini', 0, $this->promptCopywriter->prompt($text, $partial), $this->promptCopywriter->jsonSchema());
        $data = $this->decodeJson($content);
        $data = $this->normalizeResponseData($data);

        $validator = Validator::make($data, $this->promptCopywriter->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        // zakomentováno, protože teraz chcem len čistý text
        // if (!empty($data['event_body']) && is_string($data['event_body'])) {
        //     $data['event_body'] = $this->addEventClasses($data['event_body']);
        // }

        return $data;
    }

    /**
     * Dlhý text po častiach: každá časť zvlášť cez copywritera, HTML sa zlepí.
     *
     * Zlyhanie jednej časti (útržkovitý JSON, výpadok OpenAI) nezhodí celý
     * prepis — tá časť sa pripojí ako obyčajné odstavce, takže obsah ostane
     * kompletný. Ak zlyhajú všetky, hodí sa prvá chyba a volajúci si spadne na
     * surový text tak ako doteraz.
     */
    private function copywriteLongText(string $text): ?string
    {
        $chunks = $this->splitForCopywriter($text, self::MAX_COPYWRITER_INPUT_CHARS);

        // Zvyšok nad strop počtu častí sa neprepisuje (cena a čas volaní), ale
        // ani nezahodí — pripojí sa ako odstavce.
        $tail = array_splice($chunks, self::MAX_COPYWRITER_CHUNKS);

        $parts = [];
        $firstFailure = null;
        $rewritten = 0;

        foreach ($chunks as $chunk) {
            try {
                $body = $this->copywriteChunk($chunk, partial: true)['event_body'] ?? null;
            } catch (\Throwable $e) {
                $firstFailure ??= $e;
                $body = null;
            }

            if (is_string($body) && trim($body) !== '') {
                $parts[] = trim($body);
                $rewritten++;
            } else {
                $parts[] = $this->textToParagraphs($chunk);
            }
        }

        if ($rewritten === 0 && $firstFailure !== null) {
            throw $firstFailure;
        }

        foreach ($tail as $chunk) {
            $parts[] = $this->textToParagraphs($chunk);
        }

        $html = trim(implode("\n", array_filter($parts, static fn ($part) => $part !== '')));

        return $html !== '' ? $html : null;
    }

    /**
     * Rozdelí text na časti do `$limit` znakov, prednostne na hranici odstavca,
     * inak vety. Hranica vety je dôležitá — z časti useknutej uprostred vety by
     * copywriter domýšľal, ako sa veta končí, a to sú vymyslené fakty.
     *
     * @return array<int, string>
     */
    private function splitForCopywriter(string $text, int $limit): array
    {
        $chunks = [];
        $current = '';

        foreach ($this->splitToBlocks($text, $limit) as $block) {
            if ($current === '') {
                $current = $block;

                continue;
            }

            if (mb_strlen($current) + mb_strlen($block) + 2 <= $limit) {
                $current .= "\n\n" . $block;

                continue;
            }

            $chunks[] = $current;
            $current = $block;
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Text na bloky kratšie než `$limit`: odstavce, pri predlhom odstavci vety,
     * pri predlhej vete (zlepenec z extraktora bez interpunkcie) tvrdý rez.
     *
     * @return array<int, string>
     */
    private function splitToBlocks(string $text, int $limit): array
    {
        $blocks = [];

        foreach (preg_split('/\n{2,}/u', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) <= $limit) {
                $blocks[] = $paragraph;

                continue;
            }

            $sentence = '';

            foreach (preg_split('/(?<=[.!?…:])\s+/u', $paragraph) ?: [] as $part) {
                if ($sentence !== '' && mb_strlen($sentence) + mb_strlen($part) + 1 > $limit) {
                    $blocks[] = $sentence;
                    $sentence = '';
                }

                $sentence = $sentence === '' ? $part : $sentence . ' ' . $part;

                while (mb_strlen($sentence) > $limit) {
                    $blocks[] = mb_substr($sentence, 0, $limit);
                    $sentence = mb_substr($sentence, $limit);
                }
            }

            if (trim($sentence) !== '') {
                $blocks[] = $sentence;
            }
        }

        return $blocks;
    }

    /**
     * Núdzové HTML bez copywritera: riadky sa zabalia do `<p>`. Nie je to pekné,
     * ale je to celý obsah a v `v-html` sa to vykreslí ako text s odstavcami.
     */
    private function textToParagraphs(string $text): string
    {
        $paragraphs = [];

        foreach (preg_split('/\n+/u', trim($text)) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $paragraphs[] = '<p>' . e($line) . '</p>';
            }
        }

        return implode("\n", $paragraphs);
    }

    public function extractTextEdit(string $text, array $modes): array
    {
        $content = $this->chatComplete('gpt-4o-mini', 0.3, $this->promptTextEditor->prompt($text, $modes), $this->promptTextEditor->jsonSchema());
        $data = $this->decodeJson($content);

        // Safety net: some responses use "text" instead of the requested "improved_text" key.
        if (! isset($data['improved_text']) && isset($data['text'])) {
            $data['improved_text'] = $data['text'];
        }

        $validator = Validator::make($data, $this->promptTextEditor->validator());
        if ($validator->fails()) {
            throw new \RuntimeException('Neplatná štruktúra dát: ' . $validator->errors()->toJson());
        }

        return $data;
    }

    public function extractVenueDetails(array|string $input): array
    {
        $text = $this->normalizeInput($input);

        $content = $this->chatComplete('gpt-4o-mini', 0, $this->promptVenue->prompt($text), $this->promptVenue->jsonSchema());
        $data = $this->decodeJson($content);
        $data = $this->normalizeResponseData($data);
        $data = $this->applyVenueFallbackFromText($data, $text);

        $validator = Validator::make($data, $this->promptVenue->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        return $data;
    }

    public function extractCanalName(array|string $input): ?string
    {
        $text = $this->normalizeInput($input);

        $content = $this->chatComplete('gpt-4o-mini', 0, $this->promptCanal->prompt($text), $this->promptCanal->jsonSchema());
        $data = $this->decodeJson($content);

        $validator = Validator::make($data, $this->promptCanal->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        $name = $data['canal_name'] ?? null;

        if (! is_string($name)) {
            return null;
        }

        $name = trim($name);

        return $name !== '' ? $name : null;
    }

    /**
     * Krátky popis organizátora alebo miesta. Vráti null, keď model subjekt
     * spoľahlivo nepozná — volajúci si vtedy zvolí neutrálny fallback.
     */
    public function extractProfileDescription(string $kind, string $name, ?string $context = null): ?string
    {
        $content = $this->chatComplete(
            'gpt-4o-mini',
            0.2,
            $this->promptProfile->prompt($kind, $name, $context),
            $this->promptProfile->jsonSchema(),
        );

        $data = $this->decodeJson($content);

        $validator = Validator::make($data, $this->promptProfile->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        $description = $this->normalizeStringValue($data['description'] ?? null);

        if ($description === null) {
            return null;
        }

        $description = trim($description);

        return $description !== '' ? $description : null;
    }

    private function applyEventDateTimeFallbackFromText(array $data, string $text): array
    {
        // Regex fallback — only fills in what AI left null; never overrides a found value
        if (($data['start_at'] ?? null) === null) {
            $explicitStart = $this->extractExplicitStartDateTime($text);
            if ($explicitStart instanceof Carbon) {
                $data['start_at'] = $explicitStart->format('Y-m-d H:i:s');
            }
        }

        $startAt = $this->parseDateTime($data['start_at'] ?? null);
        $endAt = $this->parseDateTime($data['end_at'] ?? null);

        if ($startAt instanceof Carbon && $endAt instanceof Carbon && $endAt->lessThanOrEqualTo($startAt)) {
            $data['end_at'] = null;
        }

        return $data;
    }

    /**
     * Safety net on top of the prompt instruction: the year of an AI-guessed
     * event date must never fall before the reference (publish/extraction)
     * date — a missing year in the source text must never be resolved to a
     * past year, only the current year or, if extracted in December for an
     * earlier-month event, the next year.
     */
    private function enforceCurrentOrFutureYear(array $data, Carbon $referenceDate): array
    {
        $startAt = $this->parseDateTime($data['start_at'] ?? null);
        if (! $startAt instanceof Carbon) {
            return $data;
        }

        $minAllowedYear = $this->resolveMinimumEventYear($startAt->month, $referenceDate);
        if ($startAt->year >= $minAllowedYear) {
            return $data;
        }

        $yearShift = $minAllowedYear - $startAt->year;
        $data['start_at'] = $startAt->copy()->addYears($yearShift)->format('Y-m-d H:i:s');

        $endAt = $this->parseDateTime($data['end_at'] ?? null);
        if ($endAt instanceof Carbon) {
            $data['end_at'] = $endAt->copy()->addYears($yearShift)->format('Y-m-d H:i:s');
        }

        return $data;
    }

    private function resolveMinimumEventYear(int $eventMonth, Carbon $referenceDate): int
    {
        $year = $referenceDate->year;

        // Extracting in December for an event whose month is earlier than
        // December (e.g. a January Mass mentioned in a December article)
        // means the event belongs to next year.
        if ($referenceDate->month === 12 && $eventMonth < 12) {
            $year++;
        }

        return $year;
    }

    private function extractExplicitStartDateTime(string $text): ?Carbon
    {
        $patterns = [
            '/\b(?:v\s+)?(?:pondelok|utorok|streda|štvrtok|piatok|sobota|nedeľa)?\s*(\d{1,2})\.\s*([[:alpha:]áäčďéíĺľňóôŕšťúýž]+)\s+(\d{4})\s+(?:o\s*)?(\d{1,2}):(\d{2})\b/iu',
            '/\b(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(?:o\s*)?(\d{1,2}):(\d{2})\b/u',
        ];

        foreach ($patterns as $index => $pattern) {
            if (! preg_match($pattern, $text, $match)) {
                continue;
            }

            if ($index === 0) {
                $month = $this->slovakMonthToNumber((string) $match[2]);
                if ($month === null) {
                    continue;
                }

                return $this->safeCreateDateTime((int) $match[3], $month, (int) $match[1], (int) $match[4], (int) $match[5]);
            }

            return $this->safeCreateDateTime((int) $match[3], (int) $match[2], (int) $match[1], (int) $match[4], (int) $match[5]);
        }

        return null;
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, config('app.timezone', 'Europe/Bratislava'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeCreateDateTime(int $year, int $month, int $day, int $hour, int $minute): ?Carbon
    {
        try {
            return Carbon::create(
                $year,
                $month,
                $day,
                $hour,
                $minute,
                0,
                config('app.timezone', 'Europe/Bratislava')
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function slovakMonthToNumber(string $month): ?int
    {
        $normalized = mb_strtolower(trim($month, ". \t\n\r\0\x0B"));

        return match ($normalized) {
            'januar', 'januára', 'januara' => 1,
            'februar', 'februára', 'februara' => 2,
            'marec', 'marca' => 3,
            'april', 'apríla', 'aprila' => 4,
            'maj', 'mája', 'maja' => 5,
            'jun', 'júna', 'juna' => 6,
            'jul', 'júla', 'jula' => 7,
            'august', 'augusta' => 8,
            'september', 'septembra' => 9,
            'oktober', 'októbra', 'oktobra' => 10,
            'november', 'novembra' => 11,
            'december', 'decembra' => 12,
            default => null,
        };
    }

    private function addEventClasses(string $html): string
    {

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//p') as $node) {
            if ($node instanceof \DOMElement) {
                $node->setAttribute('class', 'event-text');
            }
        }

        foreach ($xpath->query('//h3') as $node) {
            if ($node instanceof \DOMElement) {
                $node->setAttribute('class', 'event-section-title');
            }
        }

        foreach ($xpath->query('//ul') as $node) {
            if ($node instanceof \DOMElement) {
                $node->setAttribute('class', 'event-list');
            }
        }

        foreach ($xpath->query('//li') as $node) {
            if ($node instanceof \DOMElement) {
                $node->setAttribute('class', 'event-list-item');
            }
        }

        libxml_clear_errors();

        return $dom->saveHTML();
    }

    private function applyVenueFallbackFromText(array $data, string $text): array
    {
        $venueLine = $this->extractVenueLine($text);
        if ($venueLine === null) {
            return $data;
        }

        $parts = preg_split('/\s*,\s*/u', $venueLine) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return $data;
        }

        $fallbackName = $parts[0] ?? null;
        if (($data['name'] ?? null) === null) {
            $data['name'] = $fallbackName;
        }

        if (($data['city'] ?? null) === null) {
            $data['city'] = $parts[1] ?? null;
        }

        if (($data['street'] ?? null) === null && count($parts) >= 3) {
            $data['street'] = $parts[2];
        }

        // If AI returns only city as venue name, prefer the explicit place from "Miesto konania".
        if (
            is_string($fallbackName)
            && $fallbackName !== ''
            && is_string($data['name'] ?? null)
            && is_string($data['city'] ?? null)
            && mb_strtolower(trim((string) $data['name'])) === mb_strtolower(trim((string) $data['city']))
        ) {
            $data['name'] = $fallbackName;
        }

        if (($data['country'] ?? null) === null && ($data['city'] ?? null) !== null) {
            $data['country'] = 'Slovensko';
        }

        return $data;
    }

    private function extractVenueLine(string $text): ?string
    {
        if (!preg_match('/Miesto\s+konania\s*:\s*([^\n\r]+)/iu', $text, $match)) {
            return null;
        }

        $line = trim($match[1]);

        return $line !== '' ? $line : null;
    }

    /**
     * Direct HTTP call to OpenAI Chat Completions — bypasses the SDK's CreateResponse which
     * breaks when OpenAI routes certain requests to the new Responses API format.
     */
    private function chatComplete(string $model, float $temperature, array $messages, ?array $responseFormat = null, int $timeout = 60): string
    {
        $apiKey = config('openai.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $response = Http::timeout($timeout)
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => $model,
                'temperature'     => $temperature,
                'response_format' => $responseFormat ?? ['type' => 'json_object'],
                'messages'        => $messages,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();

        // Odpoveď useknutá na limite tokenov je nedokončený JSON. Bez tejto
        // kontroly z toho o dva riadky nižšie vypadlo len „Neplatny JSON:
        // Syntax error", čo vyzeralo ako chyba modelu — pritom stačí kratší
        // vstup alebo vyšší strop. Týka sa to najmä copywritera, ktorý má text
        // rozšíriť: pri dlhom dokumente je výstup dlhší než vstup.
        if (($data['choices'][0]['finish_reason'] ?? null) === 'length') {
            throw new \RuntimeException(
                'Odpoved modelu bola useknuta na limite tokenov (model: ' . $model . ').'
            );
        }

        // Standard Chat Completions format
        $content = $data['choices'][0]['message']['content'] ?? null;

        // Fallback: new Responses API format
        if ($content === null) {
            foreach ((array) ($data['output'] ?? []) as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'message') {
                    foreach ((array) ($block['content'] ?? []) as $part) {
                        if (is_array($part) && ($part['type'] ?? '') === 'output_text') {
                            $content = $part['text'] ?? null;
                            break 2;
                        }
                    }
                }
            }
        }

        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('Prazdna odpoved od OpenAI');
        }

        return $content;
    }

    private function decodeJson(string $content): array
    {
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \RuntimeException('Neplatny JSON: ' . json_last_error_msg());
        }
        return $data;
    }

    private function normalizeInput(array|string $input): string
    {
        if (is_array($input)) {
            if (isset($input['text']) && is_string($input['text'])) {
                return $this->sanitizeUtf8($input['text']);
            }

            $json = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            return $this->sanitizeUtf8($json === false ? '' : $json);
        }

        return $this->sanitizeUtf8($input);
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

        if (!is_string($converted)) {
            $converted = $value;
        }

        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $converted);
        if ($clean !== false) {
            return trim($clean);
        }

        return trim($converted);
    }

    private function normalizeResponseData(array $data): array
    {
        $stringFields = [
            'title',
            'start_at',
            'end_at',
            'organization',
            'building',
            'name',
            'street',
            'street_and_number',
            'city',
            'postcode',
            'country',
            'email',
            'phone',
            'event_body',
        ];

        foreach ($stringFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = $this->normalizeStringValue($data[$field]);
        }

        return $data;
    }

    private function normalizeStringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $this->decodeEscapedString(trim($value));
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_string($item) || is_int($item) || is_float($item) || is_bool($item)) {
                    $parts[] = trim((string) $item);
                }
            }

            return $parts ? implode(', ', $parts) : null;
        }

        return null;
    }

    private function decodeEscapedString(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!preg_match('/\\\\u[0-9a-fA-F]{4}|\\\\[nrtf"\\\\\\/]/', $value)) {
            return $value;
        }

        $value = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static function (array $match): string {
                $bytes = pack('H*', $match[1]);
                $char = @mb_convert_encoding($bytes, 'UTF-8', 'UCS-2BE');
                return is_string($char) ? $char : $match[0];
            },
            $value
        ) ?? $value;

        return strtr($value, [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\f' => "\f",
            '\\"' => '"',
            "\\'" => "'",
            '\\/' => '/',
            '\\\\' => '\\',
        ]);
    }
}
