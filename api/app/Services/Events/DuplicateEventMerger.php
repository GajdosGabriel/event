<?php

namespace App\Services\Events;

use App\Models\Admission;
use App\Models\Event;
use App\Models\QuestionBoard;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Imports\CollectionCanal;
use Illuminate\Support\Collection;

/**
 * Nájde a zlúči tú istú akciu naimportovanú dvakrát.
 *
 * Import duplicity naprieč kanálmi zámerne nezlučuje: „Adventná obnova" o 16:00
 * môže v ten istý deň prebiehať v dvoch farnostiach a zlé zlúčenie by jednu
 * z nich zmazalo. Nechať to tak ale znamená, že vo výpise visia dva rovnaké
 * riadky — raz zo zberného kanála zdroja, raz z kanála skutočného organizátora.
 * (Rovnako to riešil aj jednorazový prenos archívu z hlascirkvi, ktorého kód
 * už v strome nie je — viď docs/hlascirkvi-legacy-import.md.)
 *
 * Tento zlučovač preto ide po zhode, ktorú si import dovoliť nemôže, lebo pri
 * ňom ešte neexistuje: rovnaký slug, rovnaký začiatok A rovnaké miesto. Dve
 * farnosti majú dve rôzne miesta, takže sa nezlúčia. Rozhoduje človek —
 * príkaz `app:events-merge-duplicates` bez `--apply` len vypíše, čo by urobil.
 */
class DuplicateEventMerger
{
    /**
     * Skupiny na zlúčenie: `keep` je ten, ktorý ostane, `drop` idú do koša.
     *
     * @return array<int, array{keep: Event, drop: array<int, Event>, reason: string|null}>
     */
    public function candidates(int $limit = 50): array
    {
        $groups = Event::query()
            ->selectRaw('slug, start_at')
            ->whereNotNull('orginal_source')
            ->whereNotNull('start_at')
            ->whereNotNull('slug')
            ->groupBy('slug', 'start_at')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('start_at')
            ->limit($limit)
            ->get();

        $result = [];

        foreach ($groups as $group) {
            $events = Event::query()
                ->with(['canal', 'venue'])
                ->whereNotNull('orginal_source')
                ->where('slug', $group->slug)
                ->where('start_at', $group->start_at)
                ->orderBy('id')
                ->get();

            if ($events->count() < 2) {
                continue;
            }

            $result[] = $this->describeGroup($events);
        }

        return $result;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array{keep: Event, drop: array<int, Event>, reason: string|null}
     */
    private function describeGroup(Collection $events): array
    {
        $keep = $this->survivor($events);
        $drop = $events->reject(fn (Event $event) => $event->id === $keep->id)->values();

        return [
            'keep' => $keep,
            'drop' => $drop->all(),
            'reason' => $this->blocker($events, $drop),
        ];
    }

    /**
     * Ostáva ten záznam, ktorý po zlúčení unesie viac.
     *
     * 1. kanál skutočného organizátora pred zberným kanálom zdroja — presne
     *    kvôli tomu sa duplicity riešia,
     * 2. dlhší popis — ten istý článok býva na jednom zdroji orezaný,
     * 3. nižšie id, nech je výsledok pri opakovanom behu rovnaký.
     *
     * @param  Collection<int, Event>  $events
     */
    private function survivor(Collection $events): Event
    {
        return $events
            ->sortBy([
                fn (Event $event) => CollectionCanal::is($event->canal) ? 1 : 0,
                fn (Event $event) => -mb_strlen((string) $event->body),
                fn (Event $event) => $event->id,
            ])
            ->first();
    }

    /**
     * Prečo sa skupina zlúčiť nesmie — alebo null, keď sa smie.
     *
     * @param  Collection<int, Event>  $events
     * @param  Collection<int, Event>  $drop
     */
    private function blocker(Collection $events, Collection $drop): ?string
    {
        // Rovnaké miesto je celá istota tohto zlučovača. Bez neho je zhoda
        // názvu a času len náhoda dvoch akcií v dvoch obciach.
        $venueIds = $events->pluck('venue_id')->unique();

        if ($venueIds->count() !== 1 || $venueIds->first() === null) {
            return 'rôzne (alebo chýbajúce) miesto konania';
        }

        foreach ($drop as $event) {
            $referenced = $this->referenceCount($event);

            if ($referenced !== null) {
                return 'na podujatí '.$event->id.' už visí '.$referenced;
            }
        }

        return null;
    }

    /**
     * Čo by po zmazaní podujatia ostalo visieť v prázdne. Importované
     * podujatia nič z toho nemávajú — keď áno, niekto s ním už pracoval a
     * automatika sa ho nesmie dotknúť.
     */
    private function referenceCount(Event $event): ?string
    {
        $counts = [
            'objednávka' => Ticket::query()->where('event_id', $event->id)->count(),
            'vstupenka' => Admission::query()->where('event_id', $event->id)->count(),
            'typ lístka' => TicketType::query()->where('event_id', $event->id)->count(),
            'odber' => Subscription::query()
                ->where('subscribable_type', Event::class)
                ->where('subscribable_id', $event->id)
                ->count(),
            'nástenka otázok' => QuestionBoard::query()
                ->where('boardable_type', Event::class)
                ->where('boardable_id', $event->id)
                ->count(),
        ];

        foreach ($counts as $label => $count) {
            if ($count > 0) {
                return $label.' ('.$count.')';
            }
        }

        return null;
    }

    /**
     * Zlúči jednu skupinu. Zahodené podujatie ide do koša (soft delete) a jeho
     * zdrojová adresa sa zapíše tomu, ktorý ostal — inak by ju najbližší import
     * nenašiel (mazanie je mäkké, dotaz `where orginal_source` ho nevidí)
     * a duplicitu by vyrobil znova. Pozri App\Services\Imports\EventSourceLookup.
     *
     * @param  array<int, Event>  $drop
     */
    public function mergeGroup(Event $keep, array $drop): void
    {
        $meta = is_array($keep->meta) ? $keep->meta : [];
        $sources = collect($meta['merged_sources'] ?? []);

        foreach ($drop as $event) {
            $source = trim((string) $event->orginal_source);

            if ($source !== '') {
                $sources->push($source);
            }

            $dropMeta = is_array($event->meta) ? $event->meta : [];
            $dropMeta['merged_into'] = $keep->id;

            $event->forceFill(['meta' => $dropMeta])->save();
            $event->delete();
        }

        $meta['merged_sources'] = $sources->unique()->values()->all();

        $keep->forceFill(['meta' => $meta])->save();
    }
}
