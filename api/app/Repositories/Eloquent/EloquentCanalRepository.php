<?php

namespace App\Repositories\Eloquent;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\User;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\CanalRepository;
use App\Services\Files\FileManager;
use App\Services\Municipalities\MunicipalityOverviewQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class EloquentCanalRepository extends AbstractRepository implements CanalRepository
{
    public function __construct(
        private readonly FileManager $fileManager,
        private readonly MunicipalityOverviewQuery $municipalityOverviewQuery,
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

    public function dashboardShow($id)
    {
        $canal = $this->dashboardIndexQuery()->where('canals.id', $id)->firstOrFail();
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

        $this->syncCanalFiles($canal, $filePayload);

        return $canal->fresh(['files']);
    }

    public function update($id, array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);

        $canal = $this->model()->findOrFail($id);
        $canal->update($properties);

        $this->syncCanalFiles($canal, $filePayload);

        return $canal->fresh(['files']);
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
