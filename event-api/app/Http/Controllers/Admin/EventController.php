<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\HasAllowedStatuses;
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

        return response()->json(['admin-show' => $event]);
    }

    public function store(EventStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Event::class);

        $payload = $request->validated();
        $payload['user_id'] = $payload['user_id'] ?? $request->user()->id;

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

    public function publish(string $id, EventPublishRequest $request): JsonResponse
    {
        $event = $this->eventRepository->adminShow($id);
        $this->authorize('publish', $event);

        $request->validated();

        $event = $this->eventRepository->publish($id);

        return response()->json(new EventResource($event), 200);
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
