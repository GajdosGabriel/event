<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use App\Services\Canals\CanalSeatDeriver;
use App\Services\Imports\CollectionCanal;
use App\Services\Imports\EventOrganizerReassigner;
use App\Services\Imports\ImportedVenueManager;
use App\Services\OpenAI\Detector;
use App\Services\Publishing\EventDependencyPublisher;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class AiDetector extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ai-detector';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Detect and process AI content for events';

    private const MAX_ATTEMPTS = 5;

    /**
     * Execute the console command.
     */
    public function handle(
        Detector $detector,
        CanalSeatDeriver $seatDeriver,
        EventOrganizerReassigner $reassigner,
        ImportedVenueManager $venueManager,
        EventDependencyPublisher $dependencyPublisher,
    ): int {
        $event = $this->claimForRewrite() ?? $this->claimForOrganizerCheck();

        if (! $event instanceof Event) {
            $this->info('AiDetector: no eligible event found.');

            return self::SUCCESS;
        }

        // Prvý beh nad podujatím prepisuje popis, ďalšie už len dohľadávajú
        // organizátora — hotový copywriter text sa druhýkrát neprepisuje.
        $rewritePass = $event->body_rewritten_at === null;

        $result = $detector->detectFromUrl((string) $event->orginal_source);
        $sourceGone = null;

        // Zdroj článok už nemá — typicky podujatia prenesené z archívu
        // hlascirkvi.sk s odkazmi na vyveska.sk z roku 2024. Ich text ale máme
        // v DB a AI ho spracuje rovnako, ako by ho stiahla. Bez toho by
        // podujatie ostalo navždy s neprepísaným popisom, na zbernom kanáli
        // a na zbernom mieste „Celé Slovensko".
        if (! ($result['success'] ?? false) && $this->sourceIsGone($result)) {
            $storedText = $this->pickString($event->meta['imported_raw_body'] ?? null)
                ?? $this->pickString($event->body);
            // Bez popisu ostáva názov — „Púť Medžugorie 2025" miesto prezradí.
            $title = $this->pickString($event->name);

            if ($storedText !== null || $title !== null) {
                $fallback = $detector->detectFromStoredText((string) $storedText, $title);

                if ($fallback['success'] ?? false) {
                    $sourceGone = $result;
                    $result = $fallback;
                }
            }
        }

        if (! ($result['success'] ?? false)) {
            $meta = is_array($event->meta) ? $event->meta : [];
            $attempts = (int) ($meta['ai_detector']['attempts'] ?? 0) + 1;

            // Natrvalo sa vzdávame, keď stránka neexistuje, keď v nej niet čo
            // čítať (hlascirkvi.sk/akcie/… je JS aplikácia bez obsahu v HTML),
            // alebo keď zlyhala priveľakrát — inak by sa to isté podujatie
            // skúšalo každú hodinu donekonečna.
            $permanent = $this->sourceIsGone($result) || $attempts >= self::MAX_ATTEMPTS;

            $meta['ai_detector'] = array_merge($meta['ai_detector'] ?? [], [
                'failed_at' => now()->toIso8601String(),
                'source_url' => $event->orginal_source,
                'error' => $result['error'] ?? 'Unknown detector error',
                'source_http_status' => $result['source_http_status'] ?? null,
                'attempts' => $attempts,
                'skipped_at' => $permanent ? now()->toIso8601String() : null,
                // 1, 2, 4, 8 hodín — prechodný výpadok zdroja sa tým prečká.
                'retry_at' => $permanent ? null : now()->addHours(2 ** ($attempts - 1))->toIso8601String(),
            ]);
            $event->update(['meta' => $meta]);

            if ($permanent) {
                Log::info('AiDetector gave up on source.', ['event_id' => $event->id, ...$meta['ai_detector']]);
                $this->info('AiDetector skipped event id '.$event->id.': '.$meta['ai_detector']['error']);

                return self::SUCCESS;
            }

            Log::warning('AiDetector failed for event.', [
                'event_id' => $event->id,
                'source_url' => $event->orginal_source,
                'error' => $result['error'] ?? 'Unknown detector error',
            ]);

            $this->warn('AiDetector failed for event id '.$event->id.'.');

            return self::FAILURE;
        }

        $meta = is_array($event->meta) ? $event->meta : [];
        $meta['ai_detector'] = [
            'processed_at' => now()->toIso8601String(),
            'source_url' => $event->orginal_source,
            'links' => $result['links'] ?? [],
            'attachments' => $result['attachments'] ?? [],
            'event_payload' => $result['event_payload'] ?? null,
            // Značka pre druhý claim nižšie: organizátora sme sa už pýtali,
            // takže sa na to isté podujatie neminie ďalšie AI volanie —
            // aj keď z toho žiadny presun nevyšiel.
            'organizer_checked_at' => now()->toIso8601String(),
        ];

        if ($sourceGone !== null) {
            $meta['ai_detector']['from_stored_text'] = true;
            $meta['ai_detector']['source_error'] = $sourceGone['error'] ?? null;
        }

        // Podujatie je spracované (claim `body_rewritten_at IS NULL`), nech sa
        // ďalší beh posunie na staršie. Tvrdé zlyhania OpenAI sem nedôjdu —
        // rieši ich `$result['success']` vyššie a tie sa skúšajú znova.
        $payload = ['meta' => $meta];

        if ($rewritePass) {
            $payload['body_rewritten_at'] = now();

            // Popis prepíšeme len keď máme skutočný copywriter HTML. Surový extrakt
            // je jeden zlepený odstavec bez formátovania a bez „Odkazov" — horší než
            // to, čo už v `body` je z importu.
            $rewritten = $this->pickString($result['corrected_text'] ?? null);
            if ($rewritten !== null) {
                $raw = (string) ($event->body ?? '');
                if ($raw !== '' && empty($meta['imported_raw_body'])) {
                    $meta['imported_raw_body'] = $raw;
                    $payload['meta'] = $meta;
                }
                $payload['body'] = $rewritten;
            }
        }

        $event->update($payload);

        // Tento beh číta celý článok naraz, takže o organizátorovi vie viac než
        // import (ten skladá výsledok z regexov a jedného AI volania nad
        // scrapnutým textom). Keď z neho vyjde meno, podujatie sa presunie zo
        // zberného kanála zdroja (vyveska.sk) ku skutočnému organizátorovi —
        // inde sa kanál nikdy neprepisuje, viď EventOrganizerReassigner.
        $organizer = $result['event_payload']['organizer'] ?? null;
        $organizerName = is_array($organizer) ? $this->pickString($organizer['name'] ?? null) : null;

        $movedTo = $reassigner->reassign($event, $organizerName);

        if ($movedTo instanceof Canal) {
            $this->info('AiDetector: podujatie '.$event->id.' prešlo zo zberného kanála na „'.$movedTo->name.'" ('.$movedTo->id.').');
        }

        // Keď z článku vyjde mesto organizátora, je to najlepší údaj o sídle
        // kanála, aký o ňom máme — lepší než obec odvodená z miesta konania.
        // `applyDetectedCity()` si sám stráži, aby neprepísal obec, ktorú
        // niekto zadal ručne.
        $organizerCity = $result['event_payload']['organizer']['city'] ?? null;
        $canal = $movedTo ?? $event->canal;

        // Až po presune kanála — nové miesto má patriť organizátorovi, nie
        // zbernému kanálu zdroja.
        $venue = $canal instanceof Canal
            ? $this->replaceFallbackVenue($event, $canal, $result['event_payload']['venue'] ?? null, $venueManager, $dependencyPublisher)
            : null;

        if ($venue instanceof Venue) {
            $this->info('AiDetector: podujatie '.$event->id.' dostalo miesto „'.$venue->name.'" ('.$venue->id.').');
        }

        if ($canal instanceof Canal && $seatDeriver->applyDetectedCity($canal, $this->pickString($organizerCity))) {
            $this->info('AiDetector: kanál '.$canal->id.' dostal sídlo podľa organizátora ('.$this->pickString($organizerCity).').');
        }

        $this->info('AiDetector processed event id '.$event->id.'.');

        return self::SUCCESS;
    }

    /**
     * Prvý rad: importované podujatie, ktoré ešte nemá prepísaný popis.
     */
    private function claimForRewrite(): ?Event
    {
        return Event::query()
            ->where(fn (Builder $query) => $this->eligibleForAttempt($query))
            ->whereNotNull('published_at')
            ->whereNotNull('orginal_source')
            ->whereNull('body_rewritten_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Druhý rad: podujatie visiace na zbernom kanáli zdroja, ktorého sa tento
     * príkaz na organizátora ešte nepýtal.
     *
     * Zberné kanály vznikli tak, že import organizátora z článku neprečítal.
     * Ostávajú v nich desiatky podujatí a bez tohto radu by sa k nim príkaz
     * nikdy nevrátil — prvý claim ich preskočí, lebo popis už prepísaný majú.
     * Rad sa vyčerpá: každý beh označí podujatie `organizer_checked_at`.
     */
    private function claimForOrganizerCheck(): ?Event
    {
        $canalIds = CollectionCanal::ids();

        if ($canalIds === []) {
            return null;
        }

        return Event::query()
            ->where(fn (Builder $query) => $this->eligibleForAttempt($query))
            ->whereIn('canal_id', $canalIds)
            ->whereNotNull('orginal_source')
            ->whereNull('meta->ai_detector->organizer_checked_at')
            // Najbližšie termíny prvé — na nich záleží najviac, archív počká.
            ->orderByDesc('start_at')
            ->first();
    }

    private function eligibleForAttempt(Builder $query): void
    {
        $query->whereNull('meta->ai_detector->skipped_at')
            ->where(function (Builder $query) {
                $query->whereNull('meta->ai_detector->retry_at')
                    ->orWhere('meta->ai_detector->retry_at', '<=', now()->toIso8601String());
            });
    }

    /**
     * Nahradí zberné „Celé Slovensko" miestom, ktoré AI prečítala z textu.
     *
     * Archív hlascirkvi.sk preniesol tisíce podujatí bez miesta — import ich
     * odložil na zberné miesto. Miesto zadané človekom alebo trafené importom
     * sa neprepisuje: je to hotový údaj a AI nad krátkym textom sa môže mýliť.
     * Hľadanie aj zakladanie ide cez ImportedVenueManager, takže platia tie
     * isté pravidlá proti duplikátom ako pri importe.
     */
    private function replaceFallbackVenue(
        Event $event,
        Canal $canal,
        mixed $detected,
        ImportedVenueManager $venueManager,
        EventDependencyPublisher $dependencyPublisher,
    ): ?Venue {
        $current = $event->venue;

        if ($current instanceof Venue && $current->category !== 'fallback') {
            return null;
        }

        if (! is_array($detected)) {
            return null;
        }

        $name = $this->pickString($detected['name'] ?? null);
        $city = $this->pickString($detected['city'] ?? null);

        if ($name === null && $city === null) {
            return null;
        }

        $venue = $venueManager->resolveOrDetect(
            $canal,
            $name,
            $city,
            $this->pickString($detected['street_and_number'] ?? null) ?? $this->pickString($detected['street'] ?? null),
        );

        if ($venue->category === 'fallback' || $venue->id === $current?->id) {
            return null;
        }

        $event->forceFill(['venue_id' => $venue->id])->save();

        // Import zakladá miesto ako koncept. Zverejnené podujatie nesmie
        // odkazovať na rozrobený profil — rovnako ako v EventImportService.
        if ($event->status === ModelStatus::Published) {
            $dependencyPublisher->publishAll($event);
        }

        return $venue;
    }

    /**
     * Stránka neexistuje alebo v nej niet čo čítať — opakovanie to nezmení.
     */
    private function sourceIsGone(array $result): bool
    {
        return in_array($result['source_http_status'] ?? null, [404, 410], true)
            || ($result['source_unreadable'] ?? false);
    }

    private function pickString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
