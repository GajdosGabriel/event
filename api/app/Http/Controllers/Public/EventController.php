<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\FileResource;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use App\Services\Views\ViewRecorder;
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
            'tags' => $this->tagSlugs($request),
        ]);

        return EventResource::collection($events);
    }

    /**
     * Štítky chodia ako ?tags=koncert,folklor. Slugy sa nevalidujú proti
     * číselníku — neznámy slug jednoducho nič nenájde, čo je pre filter
     * správnejšie než 422 pri zastaralom odkaze.
     *
     * @return array<int, string>|null
     */
    private function tagSlugs(Request $request): ?array
    {
        $raw = $request->input('tags');
        $raw = is_array($raw) ? $raw : explode(',', (string) $raw);

        $slugs = array_values(array_filter(
            array_map(static fn ($slug) => trim((string) $slug), $raw),
            static fn (string $slug) => $slug !== '',
        ));

        return $slugs !== [] ? array_slice(array_unique($slugs), 0, 10) : null;
    }

    public function show($id, Request $request, ViewRecorder $viewRecorder)
    {
        $event = $this->eventRepository->publicShow($id);

        if (! $event) {
            abort(404);
        }

        $viewRecorder->record($event, $request);

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
