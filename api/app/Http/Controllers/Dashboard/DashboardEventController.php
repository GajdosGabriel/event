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
use App\Services\Events\EventSeriesManager;
use App\Support\EventDateRange;
use App\Services\OpenAI\Detector;
use App\Services\Publishing\EventDependencyPublisher;
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

    /** Podujatie sa dá naplánovať na neskôr — viď ModelStatus::allowedForEvent(). */
    protected function allowedStatuses(Request $request): array
    {
        return ModelStatus::allowedForEvent($request->user());
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

    public function publish(string $id, EventPublishRequest $request, EventDependencyPublisher $dependencies): JsonResponse
    {
        $event = $this->eventRepository->dashboardShow($id);
        $this->authorize($request->shouldPublish() ? 'publish' : 'unpublish', $event);

        $request->validated();

        // Stiahnutie z webu závislosti nerieši — dole musí ísť podujatie aj
        // vtedy, keď mu medzitým vypadlo miesto.
        if ($request->shouldPublish()) {
            $request->shouldPublishDependencies()
                ? $dependencies->publishAll($event, $request->user())
                : $dependencies->assertPublishable($event);
        }

        $event = $this->eventRepository->publish($id, $request->shouldPublish());

        return response()->json(new EventResource($event), 200);
    }

    /**
     * Odomkne archivované podujatie späť do konceptu — viď EventPolicy::unarchive().
     * Ide o presný opak publikovania, preto repozitár nedostáva vlastnú metódu:
     * publish(false) robí presne to, čo treba (koncept + zhodené published_at
     * aj publish_at).
     */
    public function unarchive(string $id): JsonResponse
    {
        $event = $this->eventRepository->dashboardShow($id);
        $this->authorize('unarchive', $event);

        $event = $this->eventRepository->publish($id, false);

        return response()->json(new EventResource($event), 200);
    }

    public function duplicate(string $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->dashboardShow($id);
        $this->authorize('duplicate', $event);

        $copy = $this->eventRepository->duplicateForUser($request->user(), $event);

        return response()->json(new EventResource($copy), 201);
    }

    /**
     * Termíny série, do ktorej podujatie patrí — vrátane neho samého.
     *
     * Vracia holý zoznam (id, názov, termín, stav), nie EventResource: panel
     * z toho vykreslí riadky s odkazom a celý resource by na každý termín
     * pridal desiatky polí a niekoľko dotazov.
     */
    public function occurrences(string $id, EventSeriesManager $series): JsonResponse
    {
        $event = $this->eventRepository->dashboardShow($id);
        $this->authorize('view', $event);

        if ($event->series_id === null) {
            return response()->json(['data' => []]);
        }

        $occurrences = $series->siblings($event)
            ->push($event)
            ->sortBy([
                fn (Event $a, Event $b) => ($a->start_at === null) <=> ($b->start_at === null),
                fn (Event $a, Event $b) => $a->start_at <=> $b->start_at,
            ])
            ->values()
            ->map(fn (Event $occurrence) => [
                'id' => $occurrence->id,
                'name' => $occurrence->name,
                'start_at' => $occurrence->start_at,
                'end_at' => $occurrence->end_at,
                'date_range_label' => EventDateRange::label($occurrence->start_at, $occurrence->end_at),
                'status' => $occurrence->status,
                'is_current' => $occurrence->id === $event->id,
            ]);

        return response()->json(['data' => $occurrences]);
    }

    /**
     * Pridá k podujatiu ďalší termín tej istej série.
     *
     * Právo je `duplicate` — vzniká tým nové podujatie v tom istom kanáli, čiže
     * presne to isté oprávnenie ako pri „Duplikovať". Vlastnú položku v policy
     * by to potrebovalo len vtedy, keby sme chceli, aby niekto smel duplikovať
     * a nesmel pridávať termíny; taký rozdiel nedáva zmysel.
     */
    public function addOccurrence(string $id, Request $request, EventSeriesManager $series): JsonResponse
    {
        $event = $this->eventRepository->dashboardShow($id);
        $this->authorize('duplicate', $event);

        $validated = $request->validate([
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
        ]);

        $occurrence = $series->addOccurrence(
            $request->user(),
            $event,
            $validated['start_at'] ?? null,
            $validated['end_at'] ?? null,
        );

        return response()->json(new EventResource($occurrence), 201);
    }

    /**
     * Vyradí termín zo série. Podujatie zostáva, len prestane patriť k programu.
     */
    public function detachFromSeries(string $id, EventSeriesManager $series): JsonResponse
    {
        $event = $this->eventRepository->dashboardShow($id);
        $this->authorize('update', $event);

        $series->detach($event);

        return response()->json(new EventResource($event->fresh(['files', 'tags'])), 200);
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

}
