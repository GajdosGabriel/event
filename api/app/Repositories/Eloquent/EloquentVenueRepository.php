<?php

namespace App\Repositories\Eloquent;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Venue;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\VenueRepository;
use App\Services\Files\FileManager;
use App\Services\Municipalities\MunicipalityOverviewQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class EloquentVenueRepository extends AbstractRepository implements VenueRepository
{
    public function __construct(
        private readonly FileManager $fileManager,
        private readonly MunicipalityOverviewQuery $municipalityOverviewQuery,
    ) {
        parent::__construct();
    }

    public function entity(): string
    {
        return Venue::class;
    }

    public function adminIndexQuery()
    {
        return $this->latestFirst($this->model()->withTrashed());
    }

    public function dashboardIndexQuery()
    {
        $canalIds = auth('sanctum')->user()?->dashboardCanalIds() ?? collect();

        return $this->latestFirst(
            $this->model()->withTrashed()->whereHas(
                'canals',
                fn ($query) => $query
                    ->whereIn('canals.id', $canalIds)
                    ->where('canal_venue.status', ModelStatus::Published->value)
            )
        );
    }

    public function dashboardIndexWithFilters($perPage = 15, array $filters = []): LengthAwarePaginator
    {
        Gate::authorize('viewAny', $this->entity);

        $query = empty($filters['search'])
            ? $this->dashboardIndexQuery()
            : $this->latestFirst($this->model()->withTrashed());

        return $this->paginateFilteredQuery($query, $perPage, $filters);
    }

    public function dashboardShow($id)
    {
        $venue = $this->dashboardIndexQuery()->where('id', $id)->firstOrFail();
        Gate::authorize('view', $venue);

        return $venue;
    }

    public function adminMunicipalityOverview(): Collection
    {
        return $this->municipalityOverviewQuery
            ->apply($this->model()->newQuery()->withTrashed(), 'venues.village_id', 'venues.id', true)
            ->get();
    }

    public function dashboardMunicipalityOverview(): Collection
    {
        return $this->municipalityOverviewQuery
            ->apply($this->dashboardIndexQuery(), 'venues.village_id', 'venues.id', true)
            ->get();
    }

    public function create(array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);
        $canalIds = $this->extractCanalIds($properties);

        /** @var Venue $venue */
        $venue = parent::create($properties);
        $venue->syncCanalAssignments($canalIds, true);

        $this->syncVenueFiles($venue, $filePayload);

        return $venue->fresh(['files', 'canals']);
    }

    public function update($id, array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);
        $canalIds = $this->extractCanalIds($properties, false);

        $venue = $this->model()->withTrashed()->findOrFail($id);
        $venue->update($properties);

        if ($canalIds !== null) {
            $currentOwnerCanalId = $venue->ownerCanals()->value('canals.id');
            $ownerCanalId = in_array((int) $currentOwnerCanalId, $canalIds, true)
                ? (int) $currentOwnerCanalId
                : ($canalIds[0] ?? null);

            $venue->syncCanalAssignments($canalIds, false, $ownerCanalId);
        }

        $this->syncVenueFiles($venue, $filePayload);

        return $venue->fresh(['files', 'canals']);
    }

    public function delete($id)
    {
        $venue = $this->model()->withTrashed()->findOrFail($id);
        $user = auth('sanctum')->user() ?? auth()->user();

        if ($user !== null && ! $this->userOwnsVenue($venue, $user->ownedCanals()->pluck('canals.id')->all())) {
            return $this->detachVenueFromUserCanals($venue, $user->dashboardCanalIds()->all());
        }

        if ($venue->events()->withTrashed()->exists()) {
            abort(422, 'Venue cannot be deleted because it has already been used by an event.');
        }

        return $venue->delete();
    }

    private function userOwnsVenue(Venue $venue, array $ownedCanalIds): bool
    {
        $ownedCanalIds = collect($ownedCanalIds)
            ->map(fn ($id) => (int) $id)
            ->all();

        return $venue->ownerCanals()
            ->whereIn('canals.id', $ownedCanalIds)
            ->exists();
    }

    private function detachVenueFromUserCanals(Venue $venue, array $canalIds): int
    {
        $canalIds = collect($canalIds)
            ->map(fn ($id) => (int) $id)
            ->all();

        $detachCanalIds = $venue->activeCanals()
            ->whereIn('canals.id', $canalIds)
            ->where('canal_venue.is_owner', false)
            ->pluck('canals.id')
            ->all();

        if ($detachCanalIds === []) {
            abort(403, 'You are not allowed to delete this venue.');
        }

        return $venue->canals()->detach($detachCanalIds);
    }

    private function extractCanalIds(array &$properties, bool $required = true): ?array
    {
        $canalIds = $properties['canal_ids'] ?? null;

        if ($canalIds === null && array_key_exists('canal_id', $properties)) {
            $canalIds = [$properties['canal_id']];
        }

        unset($properties['canal_id'], $properties['canal_ids']);

        if ($canalIds === null) {
            return $required ? [] : null;
        }

        return collect((array) $canalIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
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

    private function syncVenueFiles(Venue $venue, array $filePayload): void
    {
        if (empty($filePayload['files'])) {
            return;
        }

        $this->fileManager->storeForModel(
            model: $venue,
            files: $filePayload['files'],
            type: $filePayload['type'],
            disk: $filePayload['disk'],
            directory: null,
            makePrimary: $filePayload['make_primary']
        );
    }

    public function publicIndexQuery()
    {
        return $this->latestFirst($this->model());
    }
}
