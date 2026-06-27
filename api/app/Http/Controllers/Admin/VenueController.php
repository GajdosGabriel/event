<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FileType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexFilterRequest;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Http\Requests\VenueDetectRequest;
use App\Http\Resources\FileResource;
use Illuminate\Http\JsonResponse; // Good practice to import JsonResponse
use App\Http\Resources\VenueResource;
use App\Repositories\Contracts\VenueRepository;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use App\Services\Files\FileManager;
use App\Services\OpenAI\Detector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;


class VenueController extends Controller
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
        $data = $this->venueRepository->adminIndexWithFilters($filters['per_page'], $filters);

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
            'data' => $this->venueRepository->adminMunicipalityOverview(),
            'meta' => [
                'resource' => 'venues',
            ],
        ]);
    }

    public function show($id): JsonResponse
    {
        $venue = $this->venueRepository->adminShow($id);

        return response()->json(new VenueResource($venue));
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

    public function restore(string $id): JsonResponse
    {
        $venue = $this->venueRepository->adminShow($id);
        $this->authorize('restore', $venue);

        $venue = $this->venueRepository->restore($id);

        return response()->json(new VenueResource($venue), 200);
    }

    public function events(string $id): JsonResponse
    {
        $venue = $this->venueRepository->adminShow($id);
        $this->authorize('view', $venue);

        $events = Event::where('venue_id', $venue->id)
            ->with('canal:id,name')
            ->orderByDesc('start_at')
            ->limit(100)
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
