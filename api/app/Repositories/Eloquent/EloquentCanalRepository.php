<?php

namespace App\Repositories\Eloquent;

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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class EloquentCanalRepository extends AbstractRepository implements CanalRepository
{
    public function __construct(
        private readonly FileManager $fileManager,
        private readonly MunicipalityOverviewQuery $municipalityOverviewQuery,
        private readonly PlaceCoordinateResolver $coordinateResolver = new PlaceCoordinateResolver(),
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

    public function adminShow($id)
    {
        $canal = $this->model()->withTrashed()->with(['municipality', 'venues', 'users'])->findOrFail($id);
        Gate::authorize('view', $canal);

        return $canal;
    }

    public function dashboardShow($id)
    {
        $canal = $this->dashboardIndexQuery()->with(['municipality', 'venues', 'users'])->where('canals.id', $id)->firstOrFail();
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
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->backfillCoordinates($canal);
        $this->syncCanalFiles($canal, $filePayload);

        return $canal->fresh(['files']);
    }

    public function update($id, array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);

        $canal = $this->model()->findOrFail($id);
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

        $coords = $this->coordinateResolver->resolve($canal->name, $city, 'Slovensko');

        // Kanal je casto organizator (nie fyzicke miesto) - ak sa podla nazvu nic
        // nenajde, pouzi aspon stred obce, aby sa mapa dala zobrazit.
        if (($coords['latitude'] === null || $coords['longitude'] === null) && $city !== null) {
            $coords = $this->coordinateResolver->resolve(null, $city, 'Slovensko');
        }

        if ($coords['latitude'] === null || $coords['longitude'] === null) {
            return;
        }

        $canal->forceFill([
            'latitude' => $coords['latitude'],
            'longitude' => $coords['longitude'],
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
        $fileDisk = $properties['file_disk'] ?? 'public';
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
