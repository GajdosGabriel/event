<?php

namespace App\Services\Events;

use App\Models\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Presunie AI prepis popisu z `body_ai` do `body`.
 *
 * `body_ai` bola „AI verzia" popisu: pri importe ju písal `app:ai-detector`
 * (copywriter), pri ručnom zadávaní to bolo odkladisko funkcie „vylepšiť text".
 * Verejné plochy vykresľovali `body`, takže z importu bol na fronte surový
 * zoškrabaný text namiesto prepisu. Zjednocujeme na jediný stĺpec `body`:
 *
 *  - importované podujatie (`orginal_source` alebo `meta.import`): pôvodný `body`
 *    sa odloží do `meta.imported_raw_body` a `body_ai` sa stane `body`,
 *  - ručne zadané podujatie: `body` je kanonický text organizátora a ostáva —
 *    staging `body_ai` sa zahodí (dropom stĺpca v migrácii).
 *
 * Idempotentné: záznam s nastaveným `body_rewritten_at` alebo so `body === body_ai`
 * sa preskočí. Spúšťa to `app:consolidate-event-body` (predbežne na produkcii)
 * aj samotná migrácia `consolidate_event_body_drop_body_ai` (poistka).
 */
class EventBodyConsolidator
{
    /**
     * @return array{processed:int,skipped:int,manual:int,rows:array<int,array{id:int,name:string}>}
     */
    public function run(bool $dryRun = false): array
    {
        // Stĺpec `body_ai` po migrácii zmizne — dovtedy ho čítame surovo
        // z atribútov modelu (`SELECT *`), nie cez pomenovaný accessor.
        $summary = ['processed' => 0, 'skipped' => 0, 'manual' => 0, 'rows' => []];

        if (! Schema::hasColumn('events', 'body_ai')) {
            return $summary;
        }

        Event::withTrashed()
            ->whereNotNull('body_ai')
            ->where('body_ai', '<>', '')
            ->orderBy('id')
            ->chunkById(200, function ($events) use (&$summary, $dryRun): void {
                foreach ($events as $event) {
                    $this->consolidate($event, $summary, $dryRun);
                }
            });

        return $summary;
    }

    /**
     * @param  array{processed:int,skipped:int,manual:int,rows:array<int,array{id:int,name:string}>}  $summary
     */
    private function consolidate(Event $event, array &$summary, bool $dryRun): void
    {
        $bodyAi = (string) $event->getAttribute('body_ai');
        $body = (string) ($event->body ?? '');

        if ($event->body_rewritten_at !== null || $body === $bodyAi) {
            $summary['skipped']++;

            return;
        }

        $isImported = ! empty($event->orginal_source)
            || (is_array($event->meta) && ! empty($event->meta['import']));

        if (! $isImported) {
            // Ručné podujatie: `body` je autorita, staging text nechávame zmiznúť
            // s dropom stĺpca. Nič nezapisujeme.
            $summary['manual']++;

            return;
        }

        $summary['processed']++;
        $summary['rows'][] = ['id' => (int) $event->id, 'name' => (string) $event->name];

        if ($dryRun) {
            return;
        }

        $meta = is_array($event->meta) ? $event->meta : [];
        if ($body !== '' && empty($meta['imported_raw_body'])) {
            $meta['imported_raw_body'] = $body;
        }

        $event->forceFill([
            'body' => $bodyAi,
            'meta' => $meta,
            'body_rewritten_at' => $event->body_rewritten_at ?? now(),
        ])->save();

        Log::info('EventBodyConsolidator: body_ai presunuté do body.', [
            'event_id' => $event->id,
        ]);
    }
}
