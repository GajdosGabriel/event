<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\FileResource;
use App\Models\Event;
use App\Models\Municipality;
use App\Repositories\Contracts\EventRepository;
use App\Services\Calendar\IcsGenerator;
use App\Services\Views\ViewRecorder;
use App\Support\EventTimeframe;
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
        $perPage = max(1, min((int) $request->integer('per_page') ?: 15, 100));
        $search = trim((string) $request->input('search', '')) ?: null;
        $list = $request->input('list');
        // `past` je archív — uplynulé podujatia od najnovšieho. Ich detaily
        // ostávajú verejné navždy (odkazy z Googlu a zo zdieľaní musia fungovať
        // aj o rok), takže potrebujú aj výpis, z ktorého sa na ne dá dostať.
        $list = in_array($list, ['upcoming', 'ongoing', 'all', 'past'], true) ? $list : 'upcoming';

        [$dateFrom, $dateTo] = $this->range($request);

        $events = $this->eventRepository->publicIndexWithFilters($perPage, [
            'municipality' => $this->municipalityId($request),
            'search' => $search,
            'list' => $list,
            'tags' => $this->tagSlugs($request),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        return EventResource::collection($events);
    }

    /**
     * Obec chodí ako id (dashboardové filtre) alebo ako slug (landing stránka
     * `/podujatia/mesto/{slug}`). Neznámy slug zámerne nekončí 422, ale
     * prázdnym výsledkom — rovnako ako pri štítkoch je to pre zastaralý odkaz
     * správnejšie správanie než chyba.
     */
    private function municipalityId(Request $request): ?int
    {
        $raw = trim((string) $request->input('municipality', ''));

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        return Municipality::query()->where('slug', $raw)->value('id');
    }

    /**
     * Pomenované časové okno pre landing stránky. Dnes jediné: `weekend`.
     * Výpočet drží [EventTimeframe], aby SPA aj bot-render vrstva ukazovali
     * ten istý zoznam.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function range(Request $request): array
    {
        if ($request->input('range') !== 'weekend') {
            return [null, null];
        }

        [$from, $to] = EventTimeframe::thisWeekend();

        return [$from->toDateString(), $to->toDateString()];
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
