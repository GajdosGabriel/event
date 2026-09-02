<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Services\Publishing\EventDependencyPublisher;
use App\Http\Requests\EventPublishRequest;
use App\Http\Requests\EventStoreRequest;
use App\Http\Requests\IndexFilterRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    use HasAllowedStatuses;

    protected $eventRepository;

    public function __construct(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    /** Podujatie sa dá naplánovať na neskôr — viď ModelStatus::allowedForEvent(). */
    protected function allowedStatuses(Request $request): array
    {
        return ModelStatus::allowedForEvent($request->user());
    }

    public function index(IndexFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $filters = $request->getFilters();
        $data = $this->eventRepository->adminIndexWithFilters($filters['per_page'], $filters);

        return EventResource::collection($data)
            ->additional([
                'meta' => [
                    'permissions' => [
                        'create' => request()->user()?->can('create', Event::class) ?? false,
                    ],
                    'allowed_statuses' => $this->allowedStatuses($request),
                ],
            ]);
    }

    public function show($id): JsonResponse
    {
        $event = $this->eventRepository->adminShow($id);
        $this->authorize('view', $event);

        return response()->json(new EventResource($event));
    }

    public function store(EventStoreRequest $request, EventDependencyPublisher $dependencies): JsonResponse
    {
        $this->authorize('create', Event::class);

        $payload = $request->validated();
        $payload['user_id'] = $payload['user_id'] ?? $request->user()->id;

        // create() gate na závislosti nemá — beží cez ňu aj import, ktorý si
        // miesto dopublikuje sám. Ručné založenie rovno v stave „publikované"
        // musí prejsť tou istou kontrolou ako úprava.
        $cascade = (bool) ($payload['publish_dependencies'] ?? false);
        unset($payload['publish_dependencies']);

        if (in_array($payload['status'] ?? null, [ModelStatus::Published->value, ModelStatus::Scheduled->value], true)) {
            $venueId = isset($payload['venue_id']) ? (int) $payload['venue_id'] : null;
            $canalId = isset($payload['canal_id']) ? (int) $payload['canal_id'] : null;

            $cascade
                ? $dependencies->publishAllFor($venueId, $canalId, $request->user())
                : $dependencies->assertPublishableFor($venueId, $canalId);
        }

        $event = $this->eventRepository->create($payload);

        return response()->json(new EventResource($event), 201);
    }

    public function update(string $id, EventStoreRequest $request): JsonResponse
    {
        $event = $this->eventRepository->adminShow($id);
        $this->authorize('update', $event);

        $event = $this->eventRepository->update($id, $request->validated());

        return response()->json(new EventResource($event), 200);
    }

    public function restore(string $id): JsonResponse
    {
        $event = $this->eventRepository->adminShow($id);
        $this->authorize('restore', $event);

        $event = $this->eventRepository->restore($id);

        return response()->json(new EventResource($event), 200);
    }

    public function publish(string $id, EventPublishRequest $request, EventDependencyPublisher $dependencies): JsonResponse
    {
        $event = $this->eventRepository->adminShow($id);
        $this->authorize($request->shouldPublish() ? 'publish' : 'unpublish', $event);

        $request->validated();

        // Viď DashboardEventController::publish() — rovnaké pravidlo.
        if ($request->shouldPublish()) {
            $request->shouldPublishDependencies()
                ? $dependencies->publishAll($event, $request->user())
                : $dependencies->assertPublishable($event);
        }

        $event = $this->eventRepository->publish($id, $request->shouldPublish());

        return response()->json(new EventResource($event), 200);
    }

    /** Viď DashboardEventController::unarchive() — rovnaké pravidlo. */
    public function unarchive(string $id): JsonResponse
    {
        $event = $this->eventRepository->adminShow($id);
        $this->authorize('unarchive', $event);

        $event = $this->eventRepository->publish($id, false);

        return response()->json(new EventResource($event), 200);
    }

    public function duplicate(string $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->adminShow($id);
        $this->authorize('duplicate', $event);

        $copy = $this->eventRepository->duplicateForUser($request->user(), $event);

        return response()->json(new EventResource($copy), 201);
    }


    public function municipalitiesOverview(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Event::class);

        $scope = $request->validate([
            'scope' => ['nullable', 'in:all,planned'],
        ])['scope'] ?? 'all';

        return response()->json([
            'data' => $this->eventRepository->adminMunicipalityOverview($scope),
            'meta' => ['scope' => $scope],
        ]);
    }
}
