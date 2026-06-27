<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\FileType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Http\Requests\VenueDetectRequest;
use App\Http\Requests\IndexFilterRequest;
use App\Http\Resources\FileResource;
use App\Http\Requests\VenueStoreRequest;
use App\Http\Resources\VenueResource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use App\Repositories\Contracts\VenueRepository;
use App\Services\Files\FileManager;
use App\Services\OpenAI\Detector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardVenueController extends Controller
{
    use HasAllowedStatuses;
    private const FILEABLE_MAP = [
        'canal' => Canal::class,
        'event' => Event::class,
        'venue' => Venue::class,
    ];

    protected $venueRepository;

    public function __construct(
        VenueRepository $venueRepository,
        private readonly FileManager $fileManager,
    ) {
        $this->venueRepository = $venueRepository;
    }

    public function index(IndexFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Venue::class);

        $filters = $request->getFilters();
        $data = $this->venueRepository->dashboardIndexWithFilters($filters['per_page'], $filters);

        return VenueResource::collection($data)
            ->additional([
                'meta' => [
                    'permissions' => [
                        'create' => request()->user()?->can('create', Venue::class) ?? false,
                    ],
                    'allowed_statuses' => $this->allowedStatuses($request),
                ],
            ]);
    }

    public function municipalitiesOverview(): JsonResponse
    {
        $this->authorize('viewAny', Venue::class);

        return response()->json([
            'data' => $this->venueRepository->dashboardMunicipalityOverview(),
            'meta' => [
                'resource' => 'venues',
            ],
        ]);
    }

    public function show($id): JsonResponse
    {
        $venue = $this->venueRepository->dashboardShow($id);
        $this->authorize('view', $venue);

        return response()->json(new VenueResource($venue));
    }

    public function update(string $id, VenueStoreRequest $request): JsonResponse
    {
        $venue = $this->venueRepository->dashboardShow($id);
        $this->authorize('update', $venue);

        $venue = $this->venueRepository->update($id, $request->validated());

        return response()->json(new VenueResource($venue), 200);
    }

    public function store(VenueStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Venue::class);

        $venue = $this->venueRepository->create($request->validated());

        return response()->json(new VenueResource($venue), 201);
    }

    public function detect(VenueDetectRequest $request, Detector $detector): JsonResponse
    {
        $this->authorize('create', Venue::class);

        $payload = $request->validated();

        $result = $detector->detectVenueDetails(
            $payload['name'],
            $payload['city'],
            $payload['country'] ?? null,
        );

        if (($payload['attach_image_to_model'] ?? false) && ($result['success'] ?? false) === true) {
            $imageUrl = $result['venue_payload']['image_url'] ?? null;

            if (is_string($imageUrl) && $imageUrl !== '') {
                $modelClass = self::FILEABLE_MAP[$payload['fileable_type']];
                $model = $modelClass::findOrFail((int) $payload['fileable_id']);

                $this->authorize('update', $model);

                $storedFiles = $this->fileManager->storeRemoteForModel(
                    model: $model,
                    attachments: [[
                        'url' => $imageUrl,
                        'name' => ($result['venue_payload']['name'] ?? 'venue-image') . '.jpg',
                    ]],
                    type: FileType::IMAGE,
                    makePrimary: (bool) ($payload['make_primary_image'] ?? true),
                    meta: [
                        'source' => 'venue_detection',
                        'reference_url' => $result['venue_payload']['reference_url'] ?? null,
                    ]
                );

                $result['attached_files'] = FileResource::collection($storedFiles)->resolve();
            }
        }

        return response()->json($result);
    }

    public function destroy(string $id): JsonResponse
    {
        $venue = $this->venueRepository->dashboardShow($id);
        $this->authorize('delete', $venue);
        $this->venueRepository->delete($id);

        return response()->json(null, 204);
    }

    public function restore(string $id): JsonResponse
    {
        $this->venueRepository->dashboardShowForRestore($id);

        $venue = $this->venueRepository->restore($id);

        return response()->json(new VenueResource($venue), 200);
    }

    public function events(string $id): JsonResponse
    {
        $venue = $this->venueRepository->dashboardShow($id);
        $this->authorize('view', $venue);

        $user = request()->user();
        $canalIds = $user->canals()->pluck('canals.id');

        $events = Event::where('venue_id', $venue->id)
            ->whereIn('canal_id', $canalIds)
            ->with('canal:id,name')
            ->orderByDesc('start_at')
            ->limit(50)
            ->get(['id', 'name', 'start_at', 'end_at', 'status', 'canal_id']);

        return response()->json($events->map(fn ($ev) => [
            'id' => $ev->id,
            'name' => $ev->name,
            'start_at' => $ev->start_at,
            'end_at' => $ev->end_at,
            'status' => $ev->status,
            'canal_id' => $ev->canal_id,
            'canal_name' => $ev->canal?->name,
        ]));
    }
}
