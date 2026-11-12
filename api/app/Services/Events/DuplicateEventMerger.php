<?php

namespace App\Services\Events;

use App\Models\Admission;
use App\Models\Event;
use App\Models\QuestionBoard;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Imports\CollectionCanal;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * Druhá zhoda: rovnaký slug, rovnaký KONIEC a rovnaké miesto, ale iný začiatok.
 * Organizátor na vyveska.sk uverejní tú istú akciu viackrát (`…-2/`, `…-3/`)
 * a každému článku dá iné „Kedy" (dni pripomienky registrácie), koniec ale
 * ostáva skutočný. Ostáva článok s najneskorším začiatkom — skorší začiatok
 * pri rovnakom konci je pripomienka, nie termín akcie.
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
        $result = [];
        $seen = [];

        foreach (['start_at', 'end_at'] as $column) {
            foreach ($this->groups($column, $limit) as $group) {
                if (count($result) >= $limit) {
                    return $result;
                }

                $events = $this->imported()
                    ->with(['canal', 'venue'])
                    ->where('slug', $group->slug)
                    ->where($column, $group->{$column})
                    ->orderBy('id')
                    ->get();

                // Podujatie z prvej skupiny sa do druhej nedostane: po jej
                // zlúčení by už neexistovalo. Zvyšok chytí ďalší beh.
                $events = $events->reject(fn (Event $event) => isset($seen[$event->id]))->values();

                if ($events->count() < 2) {
                    continue;
                }

                foreach ($events as $event) {
                    $seen[$event->id] = true;
                }

                $result[] = $this->describeGroup($events);
            }
        }

        return $result;
    }

    /**
     * Skupiny podľa slugu a začiatku, resp. konca. Pri konci len tie, ktoré
     * majú rôzne začiatky — rovnaké začiatky sú prvá zhoda.
     *
     * @return Collection<int, Event>
     */
    private function groups(string $column, int $limit): Collection
    {
        return $this->imported()
            ->selectRaw('slug, '.$column)
            ->whereNotNull('start_at')
            ->whereNotNull($column)
            ->groupBy('slug', $column)
            ->havingRaw($column === 'start_at' ? 'count(*) > 1' : 'count(distinct start_at) > 1')
            ->orderByDesc($column)
            ->limit($limit)
            ->get();
    }

    /** @return Builder<Event> */
    private function imported(): Builder
    {
        return Event::query()
            ->whereNotNull('orginal_source')
            ->whereNotNull('slug');
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
     * 2. najneskorší začiatok — pri zhode podľa konca je skorší začiatok len
     *    pripomienka; pri zhode podľa začiatku nerozhoduje,
     * 3. dlhší popis — ten istý článok býva na jednom zdroji orezaný,
     * 4. nižšie id, nech je výsledok pri opakovanom behu rovnaký.
     *
     * @param  Collection<int, Event>  $events
     */
    private function survivor(Collection $events): Event
    {
        // sortBy([...]) volá closures ako porovnávače ($a, $b), nie ako kľúče —
        // preto kľúč a jedno porovnanie poľa.
        $key = fn (Event $event): array => [
            CollectionCanal::is($event->canal) ? 1 : 0,
            -($event->start_at?->getTimestamp() ?? 0),
            -mb_strlen((string) $event->body),
            $event->id,
        ];

        return $events
            ->sort(fn (Event $a, Event $b) => $key($a) <=> $key($b))
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
            // Automatickú rezerváciu zdarma má každé podujatie — to nie je
            // stopa po organizátorovi (DefaultReservation). Vstupenky na ňu
            // by zachytil riadok vyššie.
            'typ lístka' => TicketType::query()->where('event_id', $event->id)->get()
                ->reject(fn (TicketType $type) => $type->isAutoDefault())->count(),
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
