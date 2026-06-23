<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Http\Requests\EventDetectFromTextRequest;
use App\Http\Requests\EventPublishRequest;
use App\Http\Requests\EventStoreRequest;
use App\Http\Requests\IndexFilterRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use App\Services\OpenAI\Chatgpt;
use App\Services\OpenAI\Detector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardEventController extends Controller
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
        $data = $this->eventRepository->dashboardIndexWithFilters($filters['per_page'], $filters);

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
        $event = $this->eventRepository->dashboardShow($id);
        $this->authorize('view', $event);

        return response()->json(new EventResource($event));
    }

    public function update(string $id, EventStoreRequest $request): JsonResponse
    {
        $event = $this->eventRepository->dashboardShow($id);
        $this->authorize('update', $event);

        $event = $this->eventRepository->update($id, $request->validated());
        return response()->json(
            new EventResource($event),
            200
        );
    }

    public function store(EventStoreRequest $request): JsonResponse
    {
        $event = $this->eventRepository->createForUser($request->user(), $request->validated());
        return response()->json(
            new EventResource($event),
            201
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $event = $this->eventRepository->dashboardShow($id);
        $this->authorize('delete', $event);

        $this->eventRepository->update($id, ['status' => ModelStatus::Draft->value]);
        $this->eventRepository->delete($id);

        return response()->json(null, 204);
    }

    public function restore(string $id): JsonResponse
    {
        $this->eventRepository->dashboardShowForRestore($id);

        $event = $this->eventRepository->restore($id);

        return response()->json(new EventResource($event), 200);
    }

    public function publish(string $id, EventPublishRequest $request): JsonResponse
    {
        $event = $this->eventRepository->dashboardShow($id);
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
            'data' => $this->eventRepository->dashboardMunicipalityOverview($scope),
            'meta' => ['scope' => $scope],
        ]);
    }

    public function detectFromText(EventDetectFromTextRequest $request, Detector $detector): JsonResponse
    {
        $this->authorize('create', Event::class);

        $result = $detector->detectFromText($request->validated()['text']);

        return response()->json($result);
    }

    public function improveText(Request $request, Chatgpt $chatgpt): JsonResponse
    {
        $this->authorize('create', Event::class);

        $validated = $request->validate([
            'text' => 'required|string|min:50|max:20000',
            'modes' => 'sometimes|array',
            'modes.*' => 'string|in:grammar,style,expand,html',
        ]);

        try {
            $result = $chatgpt->extractTextEdit($validated['text'], $validated['modes'] ?? ['grammar', 'style']);
            return response()->json(['success' => true, ...$result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
