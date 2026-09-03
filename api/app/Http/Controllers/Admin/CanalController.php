<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CanalIdentityMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Http\Requests\CanalStoreRequest;
use App\Http\Requests\IndexFilterRequest;
use App\Http\Requests\PublishRequest;
use App\Http\Resources\CanalResource;
use App\Repositories\Contracts\CanalRepository;
use App\Models\Event;
use App\Services\Publishing\RecordPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\Canal;

class CanalController extends Controller
{
    use HasAllowedStatuses;

    protected $canalRepository;

    public function __construct(CanalRepository $canalRepository)
    {
        $this->canalRepository = $canalRepository;
    }

    public function index(IndexFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Canal::class);

        $filters = $request->getFilters();
        $data = $this->canalRepository->adminIndexWithFilters($filters['per_page'], $filters);

        return CanalResource::collection($data)
            ->additional([
                'meta' => [
                    'permissions' => [
                        'create' => request()->user()?->can('create', Canal::class) ?? false,
                    ],
                    'allowed_statuses' => $this->allowedStatuses($request),
                ],
            ]);
    }

    /**
     * Možnosti pre výber typu identity vo formulári — popisky idú cez lang,
     * front si ich nedrží natvrdo.
     */
    public function identityModes(): JsonResponse
    {
        return response()->json(['data' => CanalIdentityMode::options()]);
    }

    public function municipalitiesOverview(): JsonResponse
    {
        $this->authorize('viewAny', Canal::class);

        return response()->json([
            'data' => $this->canalRepository->adminMunicipalityOverview(),
            'meta' => [
                'resource' => 'canals',
            ],
        ]);
    }

    public function show($id): JsonResponse
    {
        $canal = $this->canalRepository->adminShow($id);

        return response()->json(new CanalResource($canal));
    }

    public function events(string $id): JsonResponse
    {
        $canal = $this->canalRepository->adminShow($id);

        $user = request()->user();

        $events = Event::where('canal_id', $canal->id)
            ->orderByDesc('start_at')
            ->limit(50)
            ->get(['id', 'name', 'start_at', 'end_at', 'status', 'canal_id']);

        return response()->json($events->map(fn ($ev) => [
            'id' => $ev->id,
            'name' => $ev->name,
            'start_at' => $ev->start_at,
            'end_at' => $ev->end_at,
            'status' => $ev->status,
            // Menu akcií nad zoznamom sa riadi právami — viď dashboard verziu.
            'permissions' => [
                'view' => $user->can('view', $ev),
                'update' => $user->can('update', $ev),
            ],
        ]));
    }

    public function store(CanalStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Canal::class);

        $canal = $this->canalRepository->create($request->validated());

        return response()->json(new CanalResource($this->canalRepository->adminShow($canal->id)), 201);
    }

    public function update(string $id, CanalStoreRequest $request): JsonResponse
    {
        $canal = $this->canalRepository->adminShow($id);
        $this->authorize('update', $canal);

        $canal = $this->canalRepository->update($id, $request->validated());

        return response()->json(new CanalResource($canal), 200);
    }

    public function publish(string $id, PublishRequest $request, RecordPublisher $publisher): JsonResponse
    {
        $canal = $this->canalRepository->adminShow($id);
        $this->authorize($request->shouldPublish() ? 'publish' : 'unpublish', $canal);

        $publisher->apply($canal, $request->shouldPublish());

        return response()->json(new CanalResource($this->canalRepository->adminShow($id)), 200);
    }

    public function restore(string $id): JsonResponse
    {
        $canal = $this->canalRepository->adminShow($id);
        $this->authorize('restore', $canal);

        $canal = $this->canalRepository->restore($id);

        return response()->json(new CanalResource($canal), 200);
    }
}
