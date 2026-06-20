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
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class EloquentEventRepository extends AbstractRepository implements EventRepository
{
    public function __construct(
        private readonly FileManager $fileManager,
        private readonly MunicipalityOverviewQuery $municipalityOverviewQuery,
    ) {
        parent::__construct();
    }

    public function entity(): string
    {
        return Event::class;
    }

    public function create(array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);
        $this->normalizeLocationPayload($properties);

        /** @var Event $event */
        $event = parent::create($properties);

        $this->syncEventFiles($event, $filePayload);

        return $event->fresh(['files']);
    }

    public function createForUser(User $user, array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);

        $canal = $user->canal
            ?? $user->canals()->wherePivot('status', ModelStatus::Published->value)->first()
            ?? $user->canals()->first();

        if (! $canal) {
            abort(422, 'Active canal not found for this user.');
        }

        $this->normalizeLocationPayload($properties, (int) $canal->id);
        $properties['user_id'] = $properties['user_id'] ?? $user->id;

        /** @var Event $event */
        $event = $canal->events()->create($properties);

        $this->syncEventFiles($event, $filePayload);

        return $event->fresh(['files']);
    }

    public function update($id, array $properties)
    {
        $filePayload = $this->extractFilePayload($properties);

        /** @var Event $event */
        $event = $this->model()->withTrashed()->findOrFail($id);
        $targetCanalId = isset($properties['canal_id'])
            ? (int) $properties['canal_id']
            : (int) $event->canal_id;

        $this->normalizeLocationPayload($properties, $targetCanalId, true);
        $event->update($properties);

        $this->syncEventFiles($event, $filePayload);

        return $event->fresh(['files']);
    }

    public function publish($id)
    {
        /** @var Event $event */
        $event = $this->model()->findOrFail($id);

        $event->update([
            'status' => ModelStatus::Published->value,
            'published_at' => $event->published_at ?? now(),
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

    public function adminIndexQuery()
    {
        return $this->latestFirst($this->model()->withTrashed());
    }

    public function dashboardIndexQuery()
    {
        $canalIds = auth('sanctum')->user()?->dashboardCanalIds() ?? collect();

        return $this->latestFirst(
            $this->model()->withTrashed()
                ->whereIn('canal_id', $canalIds)
        );
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
            ->whereNotNull('events.published_at')
            ->where('events.end_at', '>=', now())
            ->whereNotNull('events.venue_id');

        if ($scope === 'planned') {
            $query->where('events.start_at', '>=', now());
        }

        return $this->municipalityOverviewQuery
            ->apply($query, 'venues.village_id', 'events.id')
            ->get();
    }

    public function publicIndexQuery()
    {
        return $this->model()
            ->where('status', ModelStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('end_at', '>=', now())
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
