<?php

namespace App\Repositories\Eloquent;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\EventRepository;
use App\Services\Events\EventContentCopier;
use App\Services\Events\EventSeriesManager;
use App\Services\Files\FileManager;
use App\Services\Municipalities\MunicipalityOverviewQuery;
use App\Services\Publishing\EventDependencyPublisher;
use App\Services\Tags\EventAttributeDeriver;
use App\Support\EventTimeframe;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EloquentEventRepository extends AbstractRepository implements EventRepository
{
    public function __construct(
        private readonly FileManager $fileManager,
        private readonly MunicipalityOverviewQuery $municipalityOverviewQuery,
        private readonly EventAttributeDeriver $attributeDeriver,
    ) {
        parent::__construct();
    }

    /**
     * Prepočíta štítky facetu „charakter" (viacdňové, s registráciou, vstup
     * voľný, vonku, online).
     *
     * Volá sa pri každom zápise, nie len z AI príkazu: odvodenie je zadarmo
     * a deterministické, kým AI beh sa spúšťa len pri zmene textu. Bez toho by
     * novo vytvorené podujatie nemalo tieto štítky vôbec a zmena termínu
     * z jednodňového na trojdňový by ich nechala zastarané.
     */
    private function deriveEventAttributes(Event $event): void
    {
        $this->attributeDeriver->sync($event->fresh() ?? $event);
    }

    public function entity(): string
    {
        return Event::class;
    }

    public function create(array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);
        $tagIds = $this->extractTagIds($properties);
        $this->normalizeLocationPayload($properties);

        /** @var Event $event */
        $event = parent::create($properties);

        $this->syncEventFiles($event, $filePayload);
        $this->syncEventTags($event, $tagIds);
        $this->deriveEventAttributes($event);

        return $event->fresh(['files', 'tags']);
    }

    public function createForUser(User $user, array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);
        $tagIds = $this->extractTagIds($properties);

        $canal = $user->canal
            ?? $user->canals()->wherePivot('status', ModelStatus::Published->value)->first()
            ?? $user->canals()->first();

        if (! $canal) {
            abort(422, 'Active canal not found for this user.');
        }

        // EventPolicy::create() vie len to, či používateľ smie zakladať podujatia
        // niekde. Podujatie však vzniká v jeho práve aktívnom kanáli, a v tom
        // môže mať slabšiu rolu (brigádnik na vstupe) než vo vlastnom.
        abort_unless($user->canInCanal((int) $canal->id, 'event.create'), 403);

        $this->normalizeLocationPayload($properties, (int) $canal->id);
        $this->assertDependenciesPublishable($properties);
        $properties['user_id'] = $properties['user_id'] ?? $user->id;

        /** @var Event $event */
        $event = $canal->events()->create($properties);

        $this->syncEventFiles($event, $filePayload);
        $this->syncEventTags($event, $tagIds);
        $this->deriveEventAttributes($event);

        return $event->fresh(['files', 'tags']);
    }

    public function duplicateForUser(User $user, Event $source): Event
    {
        return DB::transaction(function () use ($user, $source) {
            /** @var Event $copy */
            $copy = $source->replicate([
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

            $copy->status = ModelStatus::Draft->value;
            $copy->user_id = $user->id;
            $copy->name = $source->name . ' (kópia)';
            // Duplikát je nové podujatie, nie ďalší termín — do série zdrojového
            // podujatia nepatrí. Termín pridáva EventSeriesManager.
            $copy->series_id = null;
            $copy->save();

            // Typy lístkov, štítky aj prílohy — jedno miesto zdieľané so sériami.
            // Obrázky sa dovtedy nekopírovali vôbec a duplikát prišiel o plagát.
            app(EventContentCopier::class)->copy($source, $copy);

            return $copy->fresh(['files', 'ticketTypes', 'tags']);
        });
    }

    public function update($id, array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);
        $tagIds = $this->extractTagIds($properties, false);

        /** @var Event $event */
        $event = $this->model()->withTrashed()->findOrFail($id);

        $canalChanged = isset($properties['canal_id']) && (int) $properties['canal_id'] !== (int) $event->canal_id;

        // Presun podujatia do iného kanála je fakticky založenie v cieľovom
        // kanáli — policy nad podujatím pozná len ten pôvodný, preto sa právo
        // v cieli overuje tu. Bez toho by sa dalo podujatie presunúť kamkoľvek,
        // kde je používateľ hoci len brigádnikom na vstupe.
        // Import a konzolové príkazy bežia bez prihláseného účtu — tam sa
        // nekontroluje nič, cudzie právo tam nie je čo zneužiť.
        $mover = auth('sanctum')->user();

        if ($canalChanged && $mover instanceof User && ! $mover->hasRole('super-admin')) {
            abort_unless($mover->canInCanal((int) $properties['canal_id'], 'event.create'), 403);
        }

        // When the user explicitly picks a new canal, the existing venue may belong to the old canal.
        // Rather than silently reverting canal_id to the venue's canal, clear venue_id so the
        // canal change is honoured (user can re-select a compatible venue afterwards).
        if ($canalChanged && ! empty($properties['venue_id'])) {
            $newCanalId = (int) $properties['canal_id'];
            $venue = Venue::query()->find((int) $properties['venue_id']);
            if ($venue && ! $venue->activeCanals()->where('canals.id', $newCanalId)->exists()) {
                $properties['venue_id'] = null;
            }
        }

        $targetCanalId = isset($properties['canal_id'])
            ? (int) $properties['canal_id']
            : (int) $event->canal_id;

        // syncCanalFromVenue only when canal itself wasn't changed — i.e. venue drives the canal.
        $this->normalizeLocationPayload($properties, $targetCanalId, ! $canalChanged);

        $this->assertDependenciesPublishable($properties, $event);

        if (isset($properties['status']) && $properties['status'] === ModelStatus::Published->value && $event->published_at === null) {
            $properties['published_at'] = now();
        }

        // Naplánovaný termín patrí výhradne k stavu `scheduled`. Pri prechode
        // inam by ostal visieť vo formulári ako sľub, ktorý už nikto nesplní —
        // príkaz app:events-publish-scheduled berie len `scheduled` riadky.
        if (isset($properties['status']) && $properties['status'] !== ModelStatus::Scheduled->value) {
            $properties['publish_at'] = null;
        }

        $event->update($properties);

        // `getChanges()` vráti len to, čo sa naozaj zapísalo — nie celý payload
        // formulára. Séria podľa toho vie, čo má prepísať do ostatných termínov
        // a čoho sa nemá dotknúť.
        $savedChanges = $event->getChanges();

        $imagesBefore = $this->imageFingerprint($event);
        $this->syncEventFiles($event, $filePayload);

        if ($tagIds !== null) {
            $this->syncEventTags($event, $tagIds);
        }

        // Bezpodmienečne — termín alebo cena sa mohli zmeniť aj bez zásahu
        // do štítkov a odvodené štítky by inak ostali zastarané.
        $this->deriveEventAttributes($event);

        $this->propagateToSeries($event, $savedChanges, $tagIds, $imagesBefore);

        return $event->fresh(['files', 'tags']);
    }

    /**
     * Prepíše spoločné veci do ostatných termínov série.
     *
     * Beží až po tom, čo je uložené všetko na samotnom podujatí — inak by
     * súrodenci dostali polovicu zmeny. Podujatie mimo série tu neurobí nič.
     *
     * @param  array<string, mixed>  $savedChanges
     * @param  array<int, int>|null  $tagIds
     */
    private function propagateToSeries(Event $event, array $savedChanges, ?array $tagIds, string $imagesBefore): void
    {
        if ($event->series_id === null) {
            return;
        }

        $series = app(EventSeriesManager::class);

        $series->propagate($event, $savedChanges);

        // Štítky sa prepisujú len keď ich formulár naozaj poslal. Bez tejto
        // podmienky by uloženie akéhokoľvek iného poľa zmazalo štítky
        // v ostatných termínoch — `syncEventTags` sa volá s prázdnym zoznamom.
        if ($tagIds !== null) {
            foreach ($series->siblings($event) as $sibling) {
                $this->syncEventTags($sibling, $tagIds);
            }
        }

        if ($this->imageFingerprint($event->fresh(['files'])) !== $imagesBefore) {
            $series->propagateImages($event);
        }
    }

    /**
     * Odtlačok množiny obrázkov podujatia. Slúži len na porovnanie „zmenilo sa
     * to?" — kopírovanie obrázkov do celej série je drahé a nemá bežať pri
     * každom uložení formulára, ktorý sa obrázkov nedotkol.
     */
    private function imageFingerprint(Event $event): string
    {
        return $event->images()
            ->orderBy('id')
            ->pluck('id')
            ->implode(',');
    }

    /**
     * Verejný detail. Prekrýva AbstractRepository::publicShow(), ktorý nerobí
     * žiadny eager load — Public\EventController::show() serializuje model cez
     * toArray(), takže bez načítanej relácie by na detaile štítky chýbali.
     *
     * Filter na stav je tu, nie až v kontroléri: bez neho sa dal koncept aj
     * naplánované podujatie prečítať uhádnutím id, čím by naplánované
     * publikovanie stratilo zmysel.
     */
    public function publicShow($id)
    {
        return $this->model()
            ->with('tags')
            ->whereIn('status', ModelStatus::publiclyReadableValues())
            ->find($id);
    }

    /**
     * Publikuje alebo zruší publikovanie. Zrušenie vracia podujatie do konceptu
     * a maže `published_at` — verejné scope-y filtrujú podľa oboch
     * (status + published_at), takže musia ísť dole spolu.
     *
     * `publish_at` v oboch smeroch padá: publikovanie „hneď" naplánovaný termín
     * predbehlo a zrušenie ho ruší. Bez toho by naplánované podujatie stiahnuté
     * do konceptu ostalo v čakárni príkazu app:events-publish-scheduled.
     */
    public function publish($id, bool $published = true)
    {
        /** @var Event $event */
        $event = $this->model()->findOrFail($id);

        $event->update($published
            ? [
                'status' => ModelStatus::Published->value,
                'published_at' => $event->published_at ?? now(),
                'publish_at' => null,
            ]
            : [
                'status' => ModelStatus::Draft->value,
                'published_at' => null,
                'publish_at' => null,
            ]);

        return $event->fresh(['files']);
    }

    public function events($user = null)
    {
        $canal = $user->canal
            ?? $user->canals()->wherePivot('status', ModelStatus::Published->value)->first()
            ?? $user->canals()->first();

        if (! $canal) {
            return collect();
        }

        return $canal->events()->get();
    }

    public function published()
    {
        return $this->model()->whereHas('organization', function (Builder $query) {
            $query->whereNotNull('published_at');
        })->whereNotNull('published_at');
    }

    public function orderByStarting()
    {
        return $this->published()->where('start_at', '>', Carbon::now())->orderBy('start_at', 'asc');
    }

    public function curentlyEvents()
    {
        return $this->published()->where('start_at', '<=', Carbon::now())
            ->where('end_at', '>=', Carbon::now())
            ->orderBy('end_at', 'asc');
    }

    /**
     * Admin vidí eventy naprieč celým systémom, takže na rozlíšenie riadkov
     * potrebuje aj firmu, pod ktorú kanál patrí. V dashboarde je zbytočná —
     * tam sú všetky eventy z kanálov jedného používateľa.
     */
    public function adminIndexQuery()
    {
        return $this->latestFirst(
            $this->model()->withTrashed()->with([
                ...$this->indexEagerLoads(),
                'canal.organization:id,title',
            ])
        );
    }

    public function dashboardIndexQuery()
    {
        $canalIds = auth('sanctum')->user()?->dashboardCanalIds() ?? collect();

        return $this->latestFirst(
            $this->model()->withTrashed()
                ->with($this->indexEagerLoads())
                ->whereIn('canal_id', $canalIds)
        );
    }

    /**
     * Relations serialized by EventResource for every row. Eager loading them keeps the
     * model's canal/venue/files/image accessors from re-querying per row (N+1). Venue
     * carries the columns EventResource exposes; images come from the loaded files.
     */
    private function indexEagerLoads(): array
    {
        return [
            // Výber musí pokryť všetko, čo EventResource z kanála vypisuje —
            // nevybraný stĺpec sa nesťažuje, len ticho vráti null (`website`).
            // `organization_id` je navyše cudzí kľúč pre `canal.organization`.
            'canal:id,name,website,organization_id',
            'canal.files',
            'venue:id,name,street,postcode,latitude,longitude,phone,website,opening_hours',
            'files',
            'tags',
        ];
    }

    public function dashboardShow($id)
    {
        $event = $this->dashboardIndexQuery()->where('id', $id)->firstOrFail();
        Gate::authorize('view', $event);

        return $event;
    }

    public function dashboardMunicipalityOverview(string $scope = 'all'): Collection
    {
        $canalIds = auth('sanctum')->user()?->dashboardCanalIds() ?? collect();

        $query = $this->applyMunicipalityOverviewScope(
            $this->model()->newQuery()->whereIn('events.canal_id', $canalIds),
            $scope
        );

        return $query->get();
    }

    public function adminMunicipalityOverview(string $scope = 'all'): Collection
    {
        $query = $this->applyMunicipalityOverviewScope($this->model()->newQuery(), $scope);

        return $query->get();
    }

    public function publicMunicipalityOverview(string $scope = 'all'): Collection
    {
        $scope = in_array($scope, ['all', 'planned'], true) ? $scope : 'all';

        $query = $this->model()->newQuery()
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('events.status', ModelStatus::Published->value)
            ->whereNotNull('events.venue_id')
            ->where(function ($q) {
                $q->where('events.end_at', '>=', now())
                  ->orWhere(function ($inner) {
                      $inner->whereNull('events.end_at')
                            ->where('events.start_at', '>=', now()->startOfDay());
                  });
            });

        if ($scope === 'planned') {
            $query->where('events.start_at', '>=', now());
        }

        return $this->municipalityOverviewQuery
            ->apply($query, 'venues.village_id', 'events.id')
            ->get();
    }

    public function publicIndexWithFilters($perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $list = $filters['list'] ?? 'upcoming';

        $query = $list === 'past'
            ? $this->publicArchiveQuery()
            : $this->applyPublicTimeframe($this->publicIndexQuery(), $list);

        return $this->paginateFilteredQuery($query, $perPage, $filters);
    }

    /**
     * Splits the public list into ongoing (already started, still running) and
     * upcoming events so long-running events don't sit on top of the agenda.
     */
    private function applyPublicTimeframe(Builder $query, string $list): Builder
    {
        return match ($list) {
            'ongoing' => $query
                ->where('start_at', '<=', now())
                ->reorder()
                ->orderBy('end_at'),
            'all' => $query,
            default => $query->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '>', now());
            }),
        };
    }

    public function publicIndexQuery()
    {
        return $this->collapseSeries(
            EventTimeframe::upcoming($this->publicEventQuery())
                ->where('status', ModelStatus::Published->value)
        )->orderBy('start_at', 'asc');
    }

    /**
     * Zo série ponechá vo výpise len najbližší termín.
     *
     * Divadlo s ôsmimi reprízami by inak zabralo celú prvú stranu agendy a
     * vytlačilo z nej všetko ostatné — pre návštevníka je to osemkrát ten istý
     * riadok. Ostatné termíny nezmiznú: sú na detaile („ďalšie termíny") a majú
     * vlastné adresy aj v sitemape, takže SEO to neuberá.
     *
     * Poddotaz hľadá súrodenca, ktorý je **skôr a stále v budúcnosti**. Práve
     * preto sa výpis sám posúva: keď najbližší termín prebehne, na jeho miesto
     * nastúpi ďalší, bez akéhokoľvek prepočtu.
     */
    private function collapseSeries(Builder $query): Builder
    {
        return $query->where(function (Builder $outer) {
            $outer->whereNull('series_id')
                ->orWhereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('events as earlier_in_series')
                        ->whereColumn('earlier_in_series.series_id', 'events.series_id')
                        ->whereColumn('earlier_in_series.start_at', '<', 'events.start_at')
                        ->where('earlier_in_series.start_at', '>=', now())
                        ->where('earlier_in_series.status', ModelStatus::Published->value)
                        ->whereNull('earlier_in_series.deleted_at');
                });
        });
    }

    /**
     * Archív — uplynulé podujatia od najnovšieho.
     *
     * Stav je širší než vo výpise: desať minút po skončení preklopí podujatie
     * `app:events-archive-finished` na `archived`, takže filter len na
     * `published` by vrátil prázdny archív. Rovnaké stavy púšťa aj verejný
     * detail (ModelStatus::publiclyReadableValues()) — zoznam a detail sa tak
     * nemôžu rozísť a odkaz z archívu nikdy nekončí na 404.
     */
    public function publicArchiveQuery(): Builder
    {
        return EventTimeframe::past($this->publicEventQuery())
            ->whereIn('status', ModelStatus::publiclyReadableValues())
            ->orderByDesc('start_at');
    }

    /**
     * Spoločný základ verejných výpisov — bez stavu, termínu a zoradenia; tie
     * si každý výpis určuje sám.
     *
     * @return Builder<Event>
     */
    private function publicEventQuery(): Builder
    {
        return $this->model()
            ->with([
                // `website` vypisuje EventResource — bez neho vo výbere by na
                // verejnom výpise ticho chodilo null.
                'canal:id,name,website',
                'canal.files',
                'venue' => fn ($query) => $query
                    ->select(['id', 'name', 'street', 'postcode', 'latitude', 'longitude', 'phone', 'website', 'opening_hours', 'village_id'])
                    ->with('municipality'),
                'files',
                'tags',
            ])
            // Odznak „a ďalších N termínov" na karte zbalenej série. Počíta sa
            // poddotazom, nie načítaním termínov — na kartu treba číslo, nie
            // zoznam.
            ->withCount(['seriesEvents as series_upcoming_count' => fn (Builder $query) => $query
                ->where('status', ModelStatus::Published->value)
                ->where('start_at', '>=', now())]);
    }

    private function applyMunicipalityOverviewScope(Builder $query, string $scope): Builder
    {
        $scope = in_array($scope, ['all', 'planned'], true) ? $scope : 'all';

        $query
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->whereNotNull('events.venue_id');

        if ($scope === 'planned') {
            $query->where(function (Builder $plannedQuery) {
                $plannedQuery
                    ->where('events.status', ModelStatus::Scheduled->value)
                    ->orWhere(function (Builder $futurePublishedQuery) {
                        $futurePublishedQuery
                            ->where('events.status', ModelStatus::Published->value)
                            ->where('events.start_at', '>=', now());
                    });
            });
        }

        return $this->municipalityOverviewQuery
            ->apply($query, 'venues.village_id', 'events.id');
    }

    /**
     * Publikované podujatie musí mať publikované miesto aj kanál — inak by
     * karta odkazovala na profil, ktorý sa tvári ako rozrobený.
     *
     * Volá sa až za normalizeLocationPayload(), aby sa pýtalo na tú väzbu,
     * ktorá sa naozaj uloží. Cesta cez `create()` (import, admin store) tu
     * zámerne nie je — tam si závislosti dopublikuje volajúci sám.
     *
     * `publish_dependencies` je súhlas z dialógu „publikovať aj ich?"; nie je
     * to stĺpec, preto sa z payloadu vyberie preč.
     */
    private function assertDependenciesPublishable(array &$properties, ?Event $event = null): void
    {
        $cascade = (bool) ($properties['publish_dependencies'] ?? false);
        unset($properties['publish_dependencies']);

        $status = $properties['status'] ?? $event?->status?->value;

        if (! in_array($status, [ModelStatus::Published->value, ModelStatus::Scheduled->value], true)) {
            return;
        }

        $venueId = array_key_exists('venue_id', $properties) ? $properties['venue_id'] : $event?->venue_id;
        $canalId = array_key_exists('canal_id', $properties) ? $properties['canal_id'] : $event?->canal_id;

        $venueId = $venueId === null ? null : (int) $venueId;
        $canalId = $canalId === null ? null : (int) $canalId;

        $publisher = app(EventDependencyPublisher::class);

        $cascade
            ? $publisher->publishAllFor($venueId, $canalId, auth('sanctum')->user())
            : $publisher->assertPublishableFor($venueId, $canalId);
    }

    private function normalizeLocationPayload(array &$properties, ?int $forcedCanalId = null, bool $syncCanalFromVenue = false): void
    {
        if (! array_key_exists('venue_id', $properties) || $properties['venue_id'] === null) {
            if ($forcedCanalId !== null) {
                $properties['canal_id'] = $forcedCanalId;
            }

            return;
        }

        /** @var Venue $venue */
        $venue = Venue::query()->findOrFail((int) $properties['venue_id']);

        $targetCanalId = $forcedCanalId ?? (isset($properties['canal_id']) ? (int) $properties['canal_id'] : null);

        if ($targetCanalId !== null && ! $venue->activeCanals()->where('canals.id', $targetCanalId)->exists()) {
            if ($syncCanalFromVenue) {
                $venueCanalId = $this->resolveVenueCanalId($venue);
                $this->authorizeCanalReassignment($venueCanalId);
                $properties['canal_id'] = $venueCanalId;

                return;
            }

            abort(422, 'Selected venue does not belong to the selected canal.');
        }

        if ($forcedCanalId !== null) {
            $properties['canal_id'] = $forcedCanalId;
        }
    }

    private function resolveVenueCanalId(Venue $venue): int
    {
        $user = auth('sanctum')->user();
        $query = $venue->activeCanals();

        if ($user instanceof User && ! $user->hasRole('super-admin')) {
            $accessibleCanalIds = $user->dashboardCanalIds();
            $accessibleVenueCanalId = (clone $query)
                ->whereIn('canals.id', $accessibleCanalIds)
                ->value('canals.id');

            if ($accessibleVenueCanalId !== null) {
                return (int) $accessibleVenueCanalId;
            }
        }

        $canalId = $venue->activeCanals()->value('canals.id');

        if ($canalId === null) {
            abort(422, 'Selected venue is not assigned to any canal.');
        }

        return (int) $canalId;
    }

    private function authorizeCanalReassignment(int $canalId): void
    {
        $user = auth('sanctum')->user();

        if (! $user instanceof User || $user->hasRole('super-admin')) {
            return;
        }

        if (! $user->dashboardCanalIds()->contains($canalId)) {
            abort(422, 'Selected venue does not belong to an accessible canal.');
        }
    }

    /**
     * Vytiahne tag_ids z payloadu, aby ich $event->update() neskúšal zapísať
     * ako stĺpec.
     *
     * Rozdiel medzi „chýba" a „prázdne pole" je podstatný: pri update znamená
     * chýbajúci kľúč „štítkov sa nedotýkaj" (napr. rýchla zmena stavu), kým
     * prázdne pole znamená „odpoj všetky". Rovnaká konvencia ako pri canal_ids
     * v EloquentVenueRepository.
     *
     * @return array<int, int>|null
     */
    private function extractTagIds(array &$properties, bool $required = true): ?array
    {
        $hasKey = array_key_exists('tag_ids', $properties);
        $tagIds = $properties['tag_ids'] ?? null;

        unset($properties['tag_ids']);

        if (! $hasKey || $tagIds === null) {
            return $required ? [] : null;
        }

        return collect((array) $tagIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ručný výber človeka. Prepisuje len riadky so source='manual' — priradenia
     * od AI a odvodené z dát zostávajú, pretože ich vlastnia príslušné služby
     * (EventTagger, EventAttributeDeriver) a tie ich vedia prepočítať.
     *
     * @param  array<int, int>  $tagIds
     */
    private function syncEventTags(Event $event, array $tagIds): void
    {
        DB::transaction(function () use ($event, $tagIds) {
            $automatedIds = DB::table('event_tag')
                ->where('event_id', $event->id)
                ->where('source', '<>', 'manual')
                ->pluck('tag_id')
                ->all();

            DB::table('event_tag')
                ->where('event_id', $event->id)
                ->where('source', 'manual')
                ->delete();

            $payload = [];

            foreach ($tagIds as $tagId) {
                // Štítok, ktorý už na podujatí visí od AI, sa nedá pridať druhý
                // raz — kompozitný primárny kľúč by to odmietol.
                if (in_array($tagId, $automatedIds, true)) {
                    continue;
                }

                $payload[$tagId] = [
                    'confidence' => 100,
                    'source' => 'manual',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($payload !== []) {
                $event->tags()->attach($payload);
            }
        });
    }

    private function extractFilePayload(array &$properties): array
    {
        $files = $properties['files'] ?? [];
        $fileType = $properties['file_type'] ?? FileType::FILE->value;
        $fileDisk = $properties['file_disk'] ?? config('filesystems.default', 'public');
        $makePrimary = (bool) ($properties['make_primary_file'] ?? false);

        unset(
            $properties['files'],
            $properties['file_type'],
            $properties['file_disk'],
            $properties['make_primary_file']
        );

        return [
            'files' => $files,
            'type' => FileType::from($fileType),
            'disk' => $fileDisk,
            'make_primary' => $makePrimary,
        ];
    }

    private function syncEventFiles(Event $event, array $filePayload): void
    {
        if (empty($filePayload['files'])) {
            return;
        }

        $this->fileManager->storeForEvent(
            event: $event,
            files: $filePayload['files'],
            type: $filePayload['type'],
            disk: $filePayload['disk'],
            directory: null,
            makePrimary: $filePayload['make_primary']
        );
    }
}
