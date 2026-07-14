<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Services\Imports\HtmlBodyCleaner;
use App\Services\OpenAI\Chatgpt;
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

        return response()->json(new EventResource($event));
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

    public function duplicate(string $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->adminShow($id);
        $this->authorize('duplicate', $event);

        $copy = $this->eventRepository->duplicateForUser($request->user(), $event);

        return response()->json(new EventResource($copy), 201);
    }

    public function improveText(Request $request, Chatgpt $chatgpt, HtmlBodyCleaner $cleaner): JsonResponse
    {
        $this->authorize('update', Event::class);

        $validated = $request->validate([
            'text'    => 'required|string|min:50|max:20000',
            'modes'   => 'sometimes|array',
            'modes.*' => 'string|in:grammar,style,expand,html',
        ]);

        $modes = $validated['modes'] ?? ['grammar', 'style'];

        try {
            $result = $chatgpt->extractTextEdit($validated['text'], $modes);

            if (in_array('html', $modes, true) && is_string($result['improved_text'] ?? null)) {
                $result['improved_text'] = $cleaner->cleanHtmlString($result['improved_text']);
            }

            return response()->json(['success' => true, ...$result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
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
