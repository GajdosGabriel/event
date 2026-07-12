<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\FileResource;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    protected $eventRepository;

    public function __construct(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $municipality = $request->integer('municipality') ?: null;
        $perPage = max(1, min((int) $request->integer('per_page') ?: 15, 100));
        $search = trim((string) $request->input('search', '')) ?: null;
        $list = $request->input('list');
        $list = in_array($list, ['upcoming', 'ongoing', 'all'], true) ? $list : 'upcoming';

        $events = $this->eventRepository->publicIndexWithFilters($perPage, [
            'municipality' => $municipality,
            'search' => $search,
            'list' => $list,
        ]);

        return EventResource::collection($events);
    }

    public function show($id)
    {
        $event = $this->eventRepository->publicShow($id);

        if (! $event) {
            abort(404);
        }

        $data = $event->toArray();

        // Návštevník môže organizátorovi poslať správu, len ak má podujatie
        // aktívneho vlastníka (a nie je importované, ani jeho vlastné). Samotný
        // e-mail verejne NEvystavujeme — front dostane len tento boolean.
        $data['contactable'] = $event->isContactableBy(auth('sanctum')->user());

        return response()->json($data);
    }

    public function files($id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $files = $event->files()->orderBy('sort_order')->orderBy('id')->get();
        return response()->json(FileResource::collection($files));
    }

    public function municipalitiesOverview(Request $request): JsonResponse
    {
        $scope = $request->validate([
            'scope' => ['nullable', 'in:all,planned'],
        ])['scope'] ?? 'all';

        return response()->json([
            'data' => $this->eventRepository->publicMunicipalityOverview($scope),
            'meta' => ['scope' => $scope],
        ]);
    }
}
