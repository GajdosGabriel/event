<?php

namespace App\Http\Controllers\Public;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\FileResource;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use App\Services\Calendar\IcsGenerator;
use App\Services\Views\ViewRecorder;
use App\Support\EventDateRange;
use App\Support\PublicEventFilters;
use App\Support\PublicUrl;
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

    /** Strop bodov na mape — nad ním už treba zúžiť filter, nie ťahať všetko. */
    private const MAP_LIMIT = 2000;

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min((int) $request->integer('per_page') ?: 15, 100));

        $events = $this->eventRepository->publicIndexWithFilters($perPage, $this->filters($request));

        return EventResource::collection($events);
    }

    /**
     * Ten istý výber ako `index`, ale celý a len s tým, čo treba na mapu.
     * Stránkovaný výpis by na mape ukázal jednu stranu.
     */
    public function map(Request $request): JsonResponse
    {
        $result = $this->eventRepository->publicMapPoints($this->filters($request), self::MAP_LIMIT);

        return response()->json([
            'data' => $result['points'],
            'meta' => ['total' => $result['total'], 'limit' => self::MAP_LIMIT],
        ]);
    }

    /**
     * Filtre verejného výpisu z query — spoločné pre zoznam aj mapu, aby obe
     * zobrazenia ukazovali presne ten istý výber.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return PublicEventFilters::fromRequest($request);
    }

    public function show($id, Request $request, ViewRecorder $viewRecorder, IcsGenerator $calendar)
    {
        $event = $this->eventRepository->publicShow($id);

        if (! $event) {
            abort(404);
        }

        $viewRecorder->record($event, $request);

        $data = $event->toArray();

        // „Pridať do kalendára" — súbor `.ics` aj odkazy do webových kalendárov.
        // Skladá ich backend, aby termín, miesto aj popis boli všade rovnaké
        // ako v `.ics` a v e-maile. Bez termínu je to null a front sekciu skryje.
        $data['calendar_links'] = $calendar->links($event);

        // Návštevník môže organizátorovi poslať správu, len ak má podujatie
        // aktívneho vlastníka (a nie je importované, ani jeho vlastné). Samotný
        // e-mail verejne NEvystavujeme — front dostane len tento boolean.
        $data['contactable'] = $event->isContactableBy(auth('sanctum')->user());

        // Ostatné termíny série — „toto isté hráme aj vo štvrtok". Len
        // publikované a len tie, ktoré ešte len budú: uplynulý termín
        // návštevníkovi neponúkne nič, na čo by sa dal kúpiť lístok.
        $data['series_occurrences'] = $this->seriesOccurrences($event);

        return response()->json($data);
    }

    /**
     * Nadchádzajúce publikované termíny tej istej série, bez tohto.
     *
     * Vracia holý zoznam (id, názov, termín, adresa), nie celé EventResource:
     * na detaile z toho je pár riadkov s odkazom a celý resource by pridal
     * desiatky polí a niekoľko dotazov na každý termín.
     *
     * @return array<int, array<string, mixed>>
     */
    private function seriesOccurrences(Event $event): array
    {
        if ($event->series_id === null) {
            return [];
        }

        return Event::query()
            ->where('series_id', $event->series_id)
            ->whereKeyNot($event->getKey())
            ->whereIn('status', ModelStatus::publiclyReadableValues())
            ->whereNotNull('start_at')
            ->where('start_at', '>=', now()->startOfDay())
            ->orderBy('start_at')
            ->limit(24)
            ->get(['id', 'name', 'slug', 'start_at', 'end_at'])
            ->map(fn (Event $occurrence) => [
                'id' => $occurrence->id,
                'name' => $occurrence->name,
                'slug' => $occurrence->slug,
                'start_at' => $occurrence->start_at,
                'end_at' => $occurrence->end_at,
                'date_range_label' => EventDateRange::label($occurrence->start_at, $occurrence->end_at),
                'url' => PublicUrl::event($occurrence),
            ])
            ->all();
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

        // Počty obcí sa rátajú z rovnakého výberu ako výpis (obdobie, štítky,
        // hľadanie…) — bez vlastného filtra obce, inak by ostatné obce zmizli.
        $filters = PublicEventFilters::fromRequest($request);
        $filters['municipality'] = null;

        return response()->json([
            'data' => $this->eventRepository->publicMunicipalityOverview($scope, $filters),
            'meta' => ['scope' => $scope],
        ]);
    }
}
