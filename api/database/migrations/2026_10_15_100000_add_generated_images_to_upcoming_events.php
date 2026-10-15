<?php

use App\Enums\FileType;
use App\Models\Event;
use App\Models\File;
use App\Services\Files\FileDuplicator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

/**
 * Nadchádzajúce zverejnené podujatia bez obrázka (stav k 15. 10. 2026) dostanú
 * ilustráciu nakreslenú podľa textu podujatia. Súbory ležia vedľa migrácie
 * v assets/upcoming-event-images a nahrávajú sa cez bežné addFiles — na
 * produkcii teda idú na jej S3 a varianty spraví GenerateFileVariantsJob.
 *
 * Podujatia sa hľadajú podľa názvu, nie podľa ID: týždenné termíny UPC Nitra
 * a ruženca v katedrále vznikli až migráciou 2026_10_14_100000 a ich ID sa
 * medzi databázami líšia. Výber je ten istý ako v prehľade „bez obrázka"
 * (Event::needsAttention('missing_image')), takže podujatie, ktoré si obrázok
 * medzitým dostalo, sa nechá na pokoji.
 *
 * Termín série najprv skúsi prevziať skutočnú fotku z iného termínu tej istej
 * série — ilustrácia je len náhrada, keď séria vlastnú fotku nemá.
 */
return new class extends Migration
{
    private const SOURCE = 'migration:2026_10_15_generated_event_image';

    /** Názov podujatia => súbor v assets/upcoming-event-images. */
    private const IMAGES = [
        'Psychológovia, psychiatri, psychoterapeuti – výmena skúseností z praxe s klientami v kríze identity' => 'psychology-conference.jpg',
        'UPC Pavla Straussa v Nitre otvára nový akademický rok cyklom prednášok' => 'upc-nitra-lectures.jpg',
        'V bratislavskej katedrále sa spoločne modlia za nové kňazské povolania' => 'rosary-vocations-cathedral.jpg',
        'Sv. omša za bezdetných manželov a za slobodných + spoločenské hry/diskusia' => 'mass-singles-couples.jpg',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('files')) {
            return;
        }

        $events = Event::query()
            ->needsAttention('missing_image')
            ->whereIn('events.name', array_keys(self::IMAGES))
            ->get();

        foreach ($events as $event) {
            if ($this->copyFromSeries($event)) {
                continue;
            }

            $name = self::IMAGES[$event->name];
            $path = __DIR__.'/assets/upcoming-event-images/'.$name;

            if (! is_file($path)) {
                continue;
            }

            $event->addFiles(
                files: new UploadedFile($path, $name, 'image/jpeg', null, true),
                type: FileType::IMAGE,
                makePrimary: true,
                meta: ['source' => self::SOURCE],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        File::query()
            ->where('fileable_type', (new Event)->getMorphClass())
            ->where(fn ($query) => $query
                ->where('meta->source', self::SOURCE)
                ->orWhere('meta->copied_by', self::SOURCE))
            ->get()
            ->each(fn (File $file) => $file->delete());
    }

    /** Prevezme hlavný obrázok iného termínu série. False, keď niet čo prevziať. */
    private function copyFromSeries(Event $event): bool
    {
        if ($event->series_id === null) {
            return false;
        }

        $sources = File::query()
            ->where('fileable_type', $event->getMorphClass())
            ->whereIn('fileable_id', Event::query()
                ->where('series_id', $event->series_id)
                ->whereKeyNot($event->getKey())
                ->select('id'))
            ->where('type', FileType::IMAGE->value)
            ->where('is_primary', true)
            ->whereNull('meta->copied_by')
            ->orderBy('id')
            ->get();

        $duplicator = app(FileDuplicator::class);

        foreach ($sources as $source) {
            $meta = is_array($source->meta) ? $source->meta : [];
            $meta['copied_from'] = $source->id;
            $meta['copied_by'] = self::SOURCE;

            if ($duplicator->copyTo($source, $event, ['meta' => $meta, 'is_primary' => true]) !== null) {
                return true;
            }
        }

        return false;
    }
};
