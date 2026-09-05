<?php

namespace App\Services\Events;

use App\Enums\FileType;
use App\Models\Event;
use App\Services\Files\FileDuplicator;
use Illuminate\Support\Facades\DB;

/**
 * Prenesie obsah podujatia na jeho kópiu: typy lístkov, štítky a prílohy.
 *
 * Vzniklo pri sériách, ale používa to aj obyčajné „Duplikovať" — dovtedy sa
 * obrázky nekopírovali vôbec, takže duplikát prišiel o plagát a organizátor ho
 * musel nahrať znova. Jedno miesto zaručí, že sa obe cesty nerozídu.
 *
 * Čo sa **neprenáša**, rieši volajúci cez `replicate()`: termín, stav,
 * publikovanie a všetko, čo patrí konkrétnemu behu podujatia.
 */
class EventContentCopier
{
    public function __construct(
        protected FileDuplicator $files
    ) {}

    public function copy(Event $source, Event $copy): void
    {
        $this->copyTicketTypes($source, $copy);
        $this->copyTags($source, $copy);
        $this->files->copy($source, $copy);
    }

    /**
     * Typy lístkov bez predajných okien a termínov workshopov — tie patria
     * k dátumu, ktorý kópia ešte nemá.
     */
    public function copyTicketTypes(Event $source, Event $copy): void
    {
        foreach ($source->ticketTypes as $ticketType) {
            $typeCopy = $ticketType->replicate([
                'sale_starts_at',
                'sale_ends_at',
                'starts_at',
                'ends_at',
                'deleted_at',
            ]);
            $typeCopy->event_id = $copy->id;
            $typeCopy->save();
        }
    }

    /**
     * `replicate()` pivot riadky neprenáša — štítky treba skopírovať ručne, aj
     * s tým, kto ich priradil (`source`), inak by preštítkovanie cez AI zmazalo
     * ručný výber organizátora.
     */
    public function copyTags(Event $source, Event $copy): void
    {
        $sourceTags = DB::table('event_tag')->where('event_id', $source->id)->get();

        if ($sourceTags->isEmpty()) {
            return;
        }

        DB::table('event_tag')->insert($sourceTags->map(fn ($row) => [
            'event_id' => $copy->id,
            'tag_id' => $row->tag_id,
            'confidence' => $row->confidence,
            'source' => $row->source,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }

    /** Len obrázky — pri prepise obsahu v sérii sa prílohy typu dokument nechávajú tak. */
    public function copyImages(Event $source, Event $copy): void
    {
        $this->files->copy($source, $copy, [FileType::IMAGE]);
    }
}
