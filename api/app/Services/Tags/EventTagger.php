<?php

namespace App\Services\Tags;

use App\Enums\TagGroup;
use App\Models\Event;
use App\Models\Tag;
use App\Models\TagSuggestion;
use App\Services\OpenAI\ChatGPT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Priradí podujatiu obsahové štítky pomocou AI.
 *
 * Nikdy nevyhadzuje výnimku — vracia ['success' => bool], rovnako ako Detector.
 * Beží z plánovaného príkazu a zlyhanie jedného podujatia (mŕtve API, divná
 * odpoveď) nesmie zhodiť celý beh ani ostatné podujatia v dávke.
 */
// Nie `final` — príbuzný AttendeeRegistrar tiež nie je. Príkaz si službu berie
// z kontajnera a testy ju cez neho podstrkujú (rovnako ako Detector
// v AiDetectorCommandTest), čo by final znemožnil.
class EventTagger
{
    /** Viac štítkov už podujatie neopisuje, len rozmazáva. */
    private const MAX_TAGS = 8;

    /**
     * Pod touto istotou je priradenie hádanie. Prah je nastavený podľa meraní
     * na reálnych dátach: správne štítky vychádzali na 85–90, zjavné nezmysly
     * („online" na púti, „festival" na omši) na 50–60.
     */
    private const MIN_CONFIDENCE = 70;

    private const MAX_SUGGESTIONS = 3;

    /** Popisy majú v priemere 1,5 kB; strop je poistka proti importovaným kolosom. */
    private const TEXT_LIMIT = 4000;

    /** Číselník sa počas jedného behu nemení — stačí ho zhashovať raz. */
    private ?string $catalogVersion = null;

    public function __construct(
        private readonly ChatGPT $chatGPT = new ChatGPT(),
        private readonly EventAttributeDeriver $attributeDeriver = new EventAttributeDeriver(),
    ) {}

    /**
     * @return array{success: bool, tags?: array<int, string>, suggested?: array<int, string>, error?: string}
     */
    public function tag(Event $event, bool $dryRun = false): array
    {
        $text = $this->sourceText($event);

        if ($text === '') {
            return ['success' => false, 'error' => 'Podujatie nemá text na klasifikáciu.'];
        }

        $catalog = $this->catalog();

        if ($catalog === []) {
            return ['success' => false, 'error' => 'Číselník štítkov je prázdny — spustite TagSeeder.'];
        }

        try {
            $response = $this->chatGPT->extractTags($text, $catalog);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $slugToId = Tag::query()->active()->pluck('id', 'slug');
        $resolved = $this->resolveTags($response['tags'] ?? [], $slugToId);
        $suggested = $this->resolveSuggestions($response['suggested'] ?? [], $slugToId);

        // Facet „charakter" ide mimo AI — z termínu, ceny a typov lístkov.
        $derived = $dryRun
            ? $this->attributeDeriver->derive($event)
            : $this->attributeDeriver->sync($event);

        if (! $dryRun) {
            $this->persist($event, $resolved, $suggested);
        }

        return [
            'success' => true,
            'tags' => array_column($resolved, 'slug'),
            'derived' => $derived,
            'suggested' => array_column($suggested, 'label'),
        ];
    }

    /**
     * Odtlačok vstupov klasifikácie. Musí sedieť s výrazom v SQL strážcovi
     * príkazu app:events-ai-tag, inak by sa podujatia štítkovali stále dokola.
     *
     * Zahŕňa aj verziu číselníka — doplnenie štítka do TagSeeder-a tak samo
     * zneplatní všetky podujatia a tie sa pri najbližšom behu preštítkujú.
     * Bez toho by nový štítok dostali len novo pridané podujatia.
     */
    public function contentHash(Event $event): string
    {
        return md5(implode('|', [
            $this->catalogVersion(),
            (string) $event->name,
            (string) ($event->body_ai ?? ''),
            (string) ($event->body ?? ''),
        ]));
    }

    /**
     * Odtlačok číselníka, ktorý dostáva AI. Facet „charakter" v ňom nie je —
     * ten sa odvádza z dát a prepočítava sa pri každom behu tak či tak.
     */
    public function catalogVersion(): string
    {
        return $this->catalogVersion ??= md5((string) json_encode($this->catalog()));
    }

    /**
     * Prázdny číselník nie je zlyhanie podujatia, ale nedokončený deploy
     * (chýba TagSeeder). Príkaz sa pýta pred dávkou, aby ňou zbytočne
     * nespálil pokusy — zlyhalo by na ňom každé podujatie rovnako.
     */
    public function hasCatalog(): bool
    {
        return $this->catalog() !== [];
    }

    private function sourceText(Event $event): string
    {
        // Prednosť má `body`, hoci je to HTML z editora: `body_ai` drží surový
        // zoškrabaný text z importu a v 26 z 91 podujatí má rozbitú diakritiku
        // (dvojité prekódovanie Windows-1250 v import pipeline) — model potom
        // číta „rodièov" namiesto „rodičov".
        $body = trim(strip_tags((string) ($event->body ?? ''))) !== ''
            ? strip_tags((string) $event->body)
            : (string) ($event->body_ai ?? '');

        $text = trim((string) $event->name . "\n\n" . $body);

        return Str::limit(preg_replace('/\s+/u', ' ', $text) ?? $text, self::TEXT_LIMIT, '');
    }

    /**
     * Číselník pre AI — bez facetu „charakter".
     *
     * Ten sa odvádza z dát (EventAttributeDeriver): model ho halucinoval aj pri
     * explicitnom zákaze, pričom termín, cenu a registráciu vie systém presne.
     *
     * @return array<string, array<int, array{slug: string, name: string}>>
     */
    private function catalog(): array
    {
        return Tag::query()
            ->active()
            ->where('group', '<>', TagGroup::Attribute->value)
            ->ordered()
            ->get(['id', 'group', 'slug', 'name'])
            ->groupBy(fn (Tag $tag) => $tag->group->value)
            ->map(fn ($tags) => $tags
                ->map(fn (Tag $tag) => ['slug' => $tag->slug, 'name' => $tag->name])
                ->values()
                ->all())
            ->all();
    }

    /**
     * Orezanie a normalizácia sa robí tu, nie vo validátore promptu: jedna
     * nezmyselná hodnota nemá zahodiť aj správne určené štítky.
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, array{tag_id: int, slug: string, confidence: int}>
     */
    private function resolveTags(array $rows, \Illuminate\Support\Collection $slugToId): array
    {
        $resolved = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $slug = is_string($row['slug'] ?? null) ? $row['slug'] : null;

            // Schéma síce slugy obmedzuje enumom, ale medzi behom a odpoveďou
            // mohol štítok v DB zmiznúť alebo sa deaktivovať.
            if ($slug === null || ! $slugToId->has($slug) || isset($resolved[$slug])) {
                continue;
            }

            $confidence = (int) round((float) ($row['confidence'] ?? 0));
            $confidence = max(0, min(100, $confidence));

            if ($confidence < self::MIN_CONFIDENCE) {
                continue;
            }

            $resolved[$slug] = [
                'tag_id' => (int) $slugToId->get($slug),
                'slug' => $slug,
                'confidence' => $confidence,
            ];
        }

        // Pri prekročení stropu si necháme tie, ktorými si je model najistejší.
        uasort($resolved, static fn (array $a, array $b) => $b['confidence'] <=> $a['confidence']);

        return array_slice(array_values($resolved), 0, self::MAX_TAGS);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{slug: string, label: string}>
     */
    private function resolveSuggestions(array $rows, \Illuminate\Support\Collection $slugToId): array
    {
        $resolved = [];

        foreach ($rows as $row) {
            if (! is_string($row)) {
                continue;
            }

            $label = trim(preg_replace('/\s+/u', ' ', $row) ?? $row);
            $slug = Str::slug($label);

            // Návrh, ktorý už v číselníku je, nie je návrh.
            if ($label === '' || $slug === '' || $slugToId->has($slug) || isset($resolved[$slug])) {
                continue;
            }

            $resolved[$slug] = [
                'slug' => Str::limit($slug, 60, ''),
                'label' => Str::limit($label, 80, ''),
            ];
        }

        return array_slice(array_values($resolved), 0, self::MAX_SUGGESTIONS);
    }

    /**
     * @param  array<int, array{tag_id: int, slug: string, confidence: int}>  $resolved
     * @param  array<int, array{slug: string, label: string}>  $suggested
     */
    private function persist(Event $event, array $resolved, array $suggested): void
    {
        DB::transaction(function () use ($event, $resolved, $suggested) {
            // Ručné a importované priradenia sú nedotknuteľné — AI prepisuje
            // výhradne vlastné riadky, inak by preštítkovanie zmazalo prácu
            // organizátora.
            $manualIds = DB::table('event_tag')
                ->where('event_id', $event->id)
                ->where('source', '<>', 'ai')
                ->pluck('tag_id')
                ->all();

            DB::table('event_tag')
                ->where('event_id', $event->id)
                ->where('source', 'ai')
                ->delete();

            $payload = [];

            foreach ($resolved as $row) {
                if (in_array($row['tag_id'], $manualIds, true)) {
                    continue;
                }

                $payload[$row['tag_id']] = [
                    'confidence' => $row['confidence'],
                    'source' => 'ai',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($payload !== []) {
                $event->tags()->attach($payload);
            }

            foreach ($suggested as $row) {
                $existing = TagSuggestion::query()->where('slug', $row['slug'])->first();

                if ($existing instanceof TagSuggestion) {
                    $existing->increment('occurrences');
                    $existing->forceFill([
                        'last_event_id' => $event->id,
                        'last_seen_at' => now(),
                    ])->save();

                    continue;
                }

                TagSuggestion::create([
                    'slug' => $row['slug'],
                    'label' => $row['label'],
                    'occurrences' => 1,
                    'last_event_id' => $event->id,
                    'last_seen_at' => now(),
                ]);
            }

            // Zámerne raw update namiesto save(): štítkovanie nesmie hýbať
            // updated_at (inak by sa všetkých 91 podujatí tvárilo ako práve
            // upravené) ani spúšťať EventObserver.
            $state = [
                'ai_tagged_at' => now(),
                'ai_tags_hash' => $this->contentHash($event),
                'ai_tags_attempts' => 0,
            ];

            DB::table('events')->where('id', $event->id)->update($state);

            $event->forceFill($state)->syncChanges();
        });
    }
}
