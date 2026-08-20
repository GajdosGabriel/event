<?php

namespace App\Repositories\Eloquent;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\EventRepository;
use App\Services\Files\FileManager;
use App\Services\Municipalities\MunicipalityOverviewQuery;
use App\Services\Tags\EventAttributeDeriver;
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
            $copy->save();

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

            // replicate() pivot riadky neprenáša — štítky treba skopírovať ručne,
            // aj s tým, kto ich priradil.
            $sourceTags = DB::table('event_tag')->where('event_id', $source->id)->get();

            if ($sourceTags->isNotEmpty()) {
                DB::table('event_tag')->insert($sourceTags->map(fn ($row) => [
                    'event_id' => $copy->id,
                    'tag_id' => $row->tag_id,
                    'confidence' => $row->confidence,
                    'source' => $row->source,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            }

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

        $this->syncEventFiles($event, $filePayload);

        if ($tagIds !== null) {
            $this->syncEventTags($event, $tagIds);
        }

        // Bezpodmienečne — termín alebo cena sa mohli zmeniť aj bez zásahu
        // do štítkov a odvodené štítky by inak ostali zastarané.
        $this->deriveEventAttributes($event);

        return $event->fresh(['files', 'tags']);
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
        $query = $this->applyPublicTimeframe($this->publicIndexQuery(), $filters['list'] ?? 'upcoming');

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
            ->where('status', ModelStatus::Published->value)
            ->where(function ($q) {
                $q->where('end_at', '>=', now())
                  ->orWhere(function ($inner) {
                      $inner->whereNull('end_at')
                            ->where('start_at', '>=', now()->startOfDay());
                  });
            })
            ->orderBy('start_at', 'asc');
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
