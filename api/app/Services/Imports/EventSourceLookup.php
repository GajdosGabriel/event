<?php

namespace App\Services\Imports;

use App\Models\Event;

/**
 * Nájde podujatie podľa zdrojovej adresy článku.
 *
 * Zdrojová URL je jednoznačný identifikátor článku, takže sa na ňu pýtame
 * naprieč všetkými kanálmi. Kým bolo hľadanie zúžené na canal_id, stačilo, aby
 * AI pri ďalšom behu určila organizátora inak (alebo aby medzitým vznikol
 * duplicitný kanál), a ten istý článok sa naimportoval druhýkrát ako nový
 * event.
 *
 * Druhý dotaz je pamäť na zlúčené duplicity: zahodený záznam je v koši a
 * `where orginal_source` ho nevidí, takže by ho import vyrobil znova a človek
 * by tú istú dvojicu zlučoval po každom behu. Adresa zahodeného článku preto
 * žije ďalej v `meta.merged_sources` toho, ktorý ostal (viď
 * App\Services\Events\DuplicateEventMerger).
 */
class EventSourceLookup
{
    public static function find(string $sourceUrl): ?Event
    {
        $sourceUrl = trim($sourceUrl);

        if ($sourceUrl === '') {
            return null;
        }

        $event = Event::query()
            ->where('orginal_source', $sourceUrl)
            ->orderBy('id')
            ->first();

        if ($event instanceof Event) {
            return $event;
        }

        return Event::query()
            ->whereJsonContains('meta->merged_sources', $sourceUrl)
            ->orderBy('id')
            ->first();
    }
}
