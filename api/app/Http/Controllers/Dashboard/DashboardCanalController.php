<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CanalIdentityMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Repositories\Contracts\CanalRepository;
use App\Http\Requests\CanalStoreRequest;
use App\Http\Requests\IndexFilterRequest;
use App\Http\Requests\PublishRequest;
use App\Http\Resources\CanalResource;
use App\Models\Event;
use App\Services\Publishing\RecordPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\Canal;

class DashboardCanalController extends Controller
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
        $data = $this->canalRepository->dashboardIndexWithFilters($filters['per_page'], $filters);

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
            'data' => $this->canalRepository->dashboardMunicipalityOverview(),
            'meta' => [
                'resource' => 'canals',
            ],
        ]);
    }

    public function show($id): JsonResponse
    {
        $canal = $this->canalRepository->dashboardShow($id);
        $this->authorize('view', $canal);

        return response()->json(new CanalResource($canal));
    }

    public function update(string $id, CanalStoreRequest $request): JsonResponse
    {
        $canal = $this->canalRepository->dashboardShow($id);
        $this->authorize('update', $canal);

        $this->canalRepository->update($id, $request->validated());

        return response()->json(
            new CanalResource($this->canalRepository->dashboardShow($id)),
            200
        );
    }

    public function store(CanalStoreRequest $request): JsonResponse
    {
        $canal = $this->canalRepository->create($request->validated());

        return response()->json(
            new CanalResource($this->canalRepository->dashboardShow($canal->id)),
            201
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $canal = $this->canalRepository->dashboardShow($id);
        $this->authorize('delete', $canal);

        // Trojica ad-hoc kontrol tu bola nahradená referenčným zámkom na modeli
        // (App\Models\Traits\ProtectsReferencedRecords) — okrem používateľov a
        // miest pozerá aj na podujatia, ktoré tu doteraz chýbali, a vracia
        // hlášku, ktorú Resource posiela do tlačidla.
        if ($blocker = $canal->deletionBlocker()) {
            abort(422, $blocker);
        }

        $this->canalRepository->delete($id);

        return response()->json(null, 204);
    }

    public function publish(string $id, PublishRequest $request, RecordPublisher $publisher): JsonResponse
    {
        $canal = $this->canalRepository->dashboardShow($id);
        $this->authorize($request->shouldPublish() ? 'publish' : 'unpublish', $canal);

        $publisher->apply($canal, $request->shouldPublish());

        return response()->json(
            new CanalResource($this->canalRepository->dashboardShow($id)),
            200
        );
    }

    public function restore(string $id): JsonResponse
    {
        $this->canalRepository->dashboardShowForRestore($id);

        $canal = $this->canalRepository->restore($id);

        return response()->json(new CanalResource($canal), 200);
    }

    public function events(string $id): JsonResponse
    {
        $canal = $this->canalRepository->dashboardShow($id);
        $this->authorize('view', $canal);

        $events = Event::where('canal_id', $canal->id)
            ->orderByDesc('start_at')
            ->limit(50)
            ->get(['id', 'name', 'start_at', 'end_at', 'status']);

        return response()->json($events);
    }
}
