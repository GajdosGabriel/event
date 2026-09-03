<?php

namespace App\Repositories\Eloquent;

use App\Enums\CanalRole;
use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Municipality;
use App\Models\User;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\CanalRepository;
use App\Services\Files\FileManager;
use App\Services\Geocoding\PlaceCoordinateResolver;
use App\Services\Municipalities\MunicipalityOverviewQuery;
use App\Services\Publishing\UnpublishGuard;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class EloquentCanalRepository extends AbstractRepository implements CanalRepository
{
    public function __construct(
        private readonly FileManager $fileManager,
        private readonly MunicipalityOverviewQuery $municipalityOverviewQuery,
        private readonly PlaceCoordinateResolver $coordinateResolver = new PlaceCoordinateResolver,
    ) {
        parent::__construct();
    }

    public function entity(): string
    {
        return Canal::class;
    }

    public function adminIndexQuery()
    {
        return $this->latestFirst($this->model()->withTrashed());
    }

    public function dashboardIndexQuery()
    {
        $user = Auth::user();

        return $this->latestFirst($user->canals()->withTrashed(), 'canals.created_at');
    }

    public function adminIndexWithFilters($perPage = 15, array $filters = []): LengthAwarePaginator
    {
        Gate::authorize('viewAny', $this->entity);

        return $this->paginateFilteredQuery($this->withRowContext($this->adminIndexQuery()), $perPage, $filters);
    }

    public function dashboardIndexWithFilters($perPage = 15, array $filters = []): LengthAwarePaginator
    {
        Gate::authorize('viewAny', $this->entity);

        return $this->paginateFilteredQuery($this->withRowContext($this->dashboardIndexQuery()), $perPage, $filters);
    }

    /**
     * Kontext pre riadok výpisu — obec, firma a počty naviazaných záznamov.
     * Bez nich nesie riadok len názov a stav, čo kanál od kanála nerozlíši.
     *
     * Zámerne tu, a nie v `adminIndexQuery()`/`dashboardIndexQuery()`: tie isté
     * dotazy stavia aj prehľad obcí, ktorý ich prepína na agregáciu cez
     * GROUP BY — podselecty z `withCount()` by mu do SELECTu pridali stĺpce
     * mimo zoskupenia. Počty idú do odpovede samy ako `*_count` atribúty
     * modelu, `CanalResource` k nim nemusí nič dopĺňať.
     */
    private function withRowContext($query)
    {
        $query
            ->with(['municipality', 'organization'])
            ->withCount(['events', 'venues', 'users']);

        // `withCount()` pripne SELECT na `canals.*`. Dashboardový dotaz ide cez
        // pivot používateľa, ktorého stĺpce sa dovtedy do odpovede dostávali
        // spolu so `select *` — `is_owner` by tým z výpisu ticho zmizol.
        if ($query instanceof BelongsToMany) {
            $query->addSelect('canal_user.is_owner');
        }

        return $query;
    }

    public function adminShow($id)
    {
        $canal = $this->model()->withTrashed()->with(['municipality', 'organization', 'venues', 'users'])->findOrFail($id);
        Gate::authorize('view', $canal);

        return $canal;
    }

    public function dashboardShow($id)
    {
        $canal = $this->dashboardIndexQuery()->with(['municipality', 'organization', 'venues', 'users'])->where('canals.id', $id)->firstOrFail();
        Gate::authorize('view', $canal);

        return $canal;
    }

    public function adminMunicipalityOverview(): Collection
    {
        return $this->municipalityOverviewQuery
            ->apply($this->model()->newQuery()->withTrashed(), 'canals.municipality_id', 'canals.id', true)
            ->get();
    }

    public function dashboardMunicipalityOverview(): Collection
    {
        $query = $this->dashboardIndexQuery();

        return $this->municipalityOverviewQuery
            ->apply($query, 'canals.municipality_id', 'canals.id', true)
            ->get();
    }

    public function publicShow($id)
    {
        return $this->model()
            ->with([
                'municipality',
                'venues' => fn ($q) => $q->wherePivot('status', ModelStatus::Published->value),
            ])
            ->find($id);
    }

    public function publicIndexQuery()
    {
        return $this->latestFirst(
            $this->model()
                ->where('status', ModelStatus::Published->value)
                ->whereNotNull('published_at')
        );
    }

    public function create(array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);

        $canal = $this->model()->create($properties);

        $user = auth('sanctum')->user();

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        $user->canals()->attach($canal->id, [
            'is_owner' => true,
            'role' => CanalRole::Owner->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->forgetCanalRoles();

        $this->backfillCoordinates($canal);
        $this->syncCanalFiles($canal, $filePayload);

        return $canal->fresh(['files']);
    }

    public function update($id, array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);

        // withTrashed: adminShow() zmazaný kanál načíta, takže sa musí dať aj
        // uložiť — inak si ho admin otvorí a pri uložení dostane 404. Rovnako
        // to majú EloquentEventRepository aj EloquentVenueRepository.
        $canal = $this->model()->withTrashed()->findOrFail($id);

        // Stav sa dá zhodiť aj <select>-om vo formulári, nielen tlačidlom —
        // zámok odpublikovania musí stáť v oboch cestách. Viď UnpublishGuard.
        (new UnpublishGuard)->assert($canal, $properties['status'] ?? null);

        $canal->update($properties);

        $this->backfillCoordinates($canal);
        $this->syncCanalFiles($canal, $filePayload);

        return $canal->fresh(['files']);
    }

    /**
     * Ak kanalu chybaju GPS suradnice, skus ich doplnit cez AI/Nominatim podla
     * nazvu kanalu a jeho obce, aby sa na detaile zobrazila mapa.
     * Chyba geokodovania nie je fatalna.
     */
    private function backfillCoordinates(Canal $canal): void
    {
        if ($canal->latitude !== null && $canal->longitude !== null) {
            return;
        }

        $city = $this->resolveMunicipalityName($canal->municipality_id);
        $country = trim((string) $canal->country) !== '' ? $canal->country : 'Slovensko';

        // Rozpisana adresa ma prednost pred holym nazvom kanalu — odkedy ju
        // editor zbiera, je to najpresnejsi vstup, aky geokoder dostane.
        $coords = $this->coordinateResolver->resolve(
            $canal->name,
            $city,
            $country,
            $canal->street,
            $canal->postcode,
        );

        // Kanal je casto organizator (nie fyzicke miesto) - ak sa podla nazvu nic
        // nenajde, pouzi aspon stred obce, aby sa mapa dala zobrazit.
        if (($coords['latitude'] === null || $coords['longitude'] === null) && $city !== null) {
            $coords = $this->coordinateResolver->resolve(null, $city, $country);
        }

        if ($coords['latitude'] === null || $coords['longitude'] === null) {
            return;
        }

        $canal->forceFill([
            'latitude' => $coords['latitude'],
            'longitude' => $coords['longitude'],
            // Bez zdroja by sa priblizna poloha (stred obce) navonok nelisila
            // od presnej — rovnako to drzi EloquentVenueRepository.
            'coordinates_source' => $coords['source'],
        ])->save();
    }

    private function resolveMunicipalityName(mixed $municipalityId): ?string
    {
        if ($municipalityId === null || $municipalityId === '') {
            return null;
        }

        $municipality = Municipality::query()->find($municipalityId, ['shortname', 'fullname']);

        $name = trim((string) ($municipality?->shortname ?? ''));
        if ($name !== '') {
            return $name;
        }

        $fullname = trim((string) ($municipality?->fullname ?? ''));

        return $fullname !== '' ? $fullname : null;
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

    private function syncCanalFiles(Canal $canal, array $filePayload): void
    {
        if (empty($filePayload['files'])) {
            return;
        }

        $this->fileManager->storeForModel(
            model: $canal,
            files: $filePayload['files'],
            type: $filePayload['type'],
            disk: $filePayload['disk'],
            directory: null,
            makePrimary: $filePayload['make_primary']
        );
    }
}
