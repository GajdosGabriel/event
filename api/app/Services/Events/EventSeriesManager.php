<?php

namespace App\Services\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Série opakovaných termínov — všetko, čo o nich systém vie, je tu.
 *
 * Model: **každý termín je samostatné podujatie.** Má vlastnú kapacitu, vlastný
 * zoznam prihlásených, vlastný check-in aj vlastnú verejnú adresu; séria ich len
 * spája. Klub s repríznym programom tak vidí na každý večer, kto príde — čo je
 * presne to, čo od termínov chce — a zároveň nemusí popis písať osemkrát.
 *
 * Čo je spoločné a čo nie:
 *
 * | Spoločné (prepíše sa do všetkých) | Vlastné (nikdy sa neprepisuje) |
 * |---|---|
 * | popis, web, miesto a adresa, štítky, obrázky | termín, stav, kapacita, typy lístkov a ceny, prihlásení, check-in |
 *
 * Typy lístkov sa **zámerne neprepisujú**: pri sérii je bežné, že jeden termín
 * je vypredaný a druhý má ešte miesta, prípadne že premiéra stojí viac než
 * repríza. Prepisovať ceny do termínov, ktoré sú už v predaji, by menilo
 * podmienky ľuďom, ktorí si lístok kúpili.
 */
class EventSeriesManager
{
    /**
     * Polia, ktoré séria drží spoločné. Vedome tu **nie sú** `name` ani `slug`:
     * meno je súčasťou adresy termínu a jeho zmena by prepísala kanonické URL
     * všetkých termínov naraz.
     */
    public const SHARED_FIELDS = [
        'body',
        'website',
        'email',
        'phone',
        // Adresa je na mieste, nie na podujatí — `events` súradnice ani ulicu
        // nemá. Prepisom `venue_id` sa preto presunie celá adresa naraz.
        'venue_id',
        'municipality_id',
    ];

    public function __construct(
        protected EventContentCopier $content
    ) {}

    /**
     * Pridá k podujatiu ďalší termín.
     *
     * Prvé volanie sériu založí a zaradí do nej aj zdrojové podujatie — dovtedy
     * séria neexistuje, lebo jeden termín ju nepotrebuje.
     *
     * Nový termín vzniká ako **koncept**: má rovnaký obsah, ale prázdny dátum
     * (alebo ten, ktorý prišiel v požiadavke) a organizátor ho musí zverejniť
     * sám. Automatické publikovanie by z preklepu spravilo verejnú stránku.
     */
    public function addOccurrence(User $user, Event $source, ?string $startAt = null, ?string $endAt = null): Event
    {
        return DB::transaction(function () use ($user, $source, $startAt, $endAt) {
            $series = $this->ensureSeries($source);

            /** @var Event $occurrence */
            $occurrence = $source->replicate([
                'status',
                'published_at',
                'publish_at',
                'start_at',
                'end_at',
                'registration_deadline_at',
                'reminder_sent_at',
                'orginal_source',
                'deleted_at',
            ]);

            $occurrence->series_id = $series->id;
            $occurrence->status = ModelStatus::Draft->value;
            $occurrence->user_id = $user->id;
            $occurrence->start_at = $startAt;
            $occurrence->end_at = $endAt;
            $occurrence->save();

            $this->content->copy($source, $occurrence);

            return $occurrence->fresh(['files', 'ticketTypes', 'tags']);
        });
    }

    /**
     * Vyradí termín zo série a ponechá ho ako samostatné podujatie. Nič sa
     * nemaže — organizátor len povie, že tento večer už k programu nepatrí.
     *
     * Keď v sérii zostane menej než dva termíny, séria stráca zmysel a zaniká:
     * jednoprvková séria by vo výpise stále hlásila „a ďalšie termíny", hoci
     * žiadne nie sú.
     */
    public function detach(Event $event): void
    {
        $series = $event->series;

        if ($series === null) {
            return;
        }

        DB::transaction(function () use ($event, $series) {
            $event->forceFill(['series_id' => null])->save();

            $remaining = Event::query()->where('series_id', $series->id)->get();

            if ($remaining->count() < 2) {
                Event::query()->where('series_id', $series->id)->update(['series_id' => null]);
                $series->delete();
            }
        });
    }

    /**
     * Prepíše spoločné polia do ostatných termínov série.
     *
     * Prepisuje sa **len to, čo sa v tejto požiadavke naozaj zmenilo** — nie
     * celý zoznam spoločných polí. Rozdiel je podstatný: keby sa zapisovalo
     * všetko, uloženie formulára by prepísalo aj to, čo si niekto v inom termíne
     * vedome upravil, a organizátor by nemal ako to zistiť.
     *
     * @param  array<string, mixed>  $changed  Polia zmenené na zdrojovom podujatí.
     * @return int Počet termínov, do ktorých sa zapísalo.
     */
    public function propagate(Event $source, array $changed): int
    {
        if ($source->series_id === null) {
            return 0;
        }

        $shared = array_intersect_key($changed, array_flip(self::SHARED_FIELDS));

        if ($shared === []) {
            return 0;
        }

        return Event::query()
            ->where('series_id', $source->series_id)
            ->whereKeyNot($source->getKey())
            ->update($shared);
    }

    /**
     * Prepíše obrázky do ostatných termínov série.
     *
     * Prepíše len tie termíny, ktorých obrázky **všetky prišli zo série**
     * (`meta.copied_from`, značka z [FileDuplicator]). Kto v niektorom termíne
     * nahral vlastný plagát, oň neprípde — jeho termín sa preskočí. Bez tejto
     * podmienky by úprava obrázka v jednom večere ticho zmazala cudziu prácu
     * vo zvyšku série.
     *
     * @return int Počet termínov, ktorým sa obrázky vymenili.
     */
    public function propagateImages(Event $source): int
    {
        if ($source->series_id === null) {
            return 0;
        }

        $updated = 0;

        foreach ($this->siblings($source) as $sibling) {
            if (! $this->imagesAreSeriesCopies($sibling)) {
                continue;
            }

            DB::transaction(function () use ($source, $sibling) {
                // Riadky sa mažú mäkko — fyzické súbory kópií nechávame na
                // disku. Sú to kópie, nie originály, a `forceDelete` by pri
                // rýchlom slede úprav zmazal cestu, ktorú ešte niekto číta.
                $sibling->images()->get()->each(fn (File $file) => $file->delete());

                $this->content->copyImages($source, $sibling);
            });

            $updated++;
        }

        return $updated;
    }

    /** Ostatné termíny série. Prázdne, keď podujatie sériu nemá. */
    public function siblings(Event $event): Collection
    {
        if ($event->series_id === null) {
            return new Collection();
        }

        return Event::query()
            ->where('series_id', $event->series_id)
            ->whereKeyNot($event->getKey())
            ->orderByRaw('start_at IS NULL')
            ->orderBy('start_at')
            ->get();
    }

    private function ensureSeries(Event $source): EventSeries
    {
        if ($source->series_id !== null) {
            return $source->series;
        }

        $series = EventSeries::create(['canal_id' => $source->canal_id]);

        $source->forceFill(['series_id' => $series->id])->save();

        return $series;
    }

    /**
     * Sú všetky obrázky termínu kópie zo série? Termín bez obrázkov sa počíta
     * ako áno — nie je čo stratiť a zo série ich má dostať.
     */
    private function imagesAreSeriesCopies(Event $event): bool
    {
        foreach ($event->images()->get() as $image) {
            if (data_get($image->meta, 'copied_from') === null) {
                return false;
            }
        }

        return true;
    }
}
