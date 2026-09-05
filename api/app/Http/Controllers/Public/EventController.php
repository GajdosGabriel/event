<?php

namespace App\Http\Controllers\Public;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\FileResource;
use App\Models\Event;
use App\Models\Municipality;
use App\Repositories\Contracts\EventRepository;
use App\Services\Calendar\IcsGenerator;
use App\Services\Views\ViewRecorder;
use App\Support\EventDateRange;
use App\Support\EventTimeframe;
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
        [$latitude, $longitude, $radiusKm] = $this->nearby($request);

        $events = $this->eventRepository->publicIndexWithFilters($perPage, [
            'municipality' => $this->municipalityId($request),
            'search' => $search,
            'list' => $list,
            'tags' => $this->tagSlugs($request),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_km' => $radiusKm,
        ]);

        return EventResource::collection($events);
    }

    /**
     * „V mojom okolí" — bod z prehliadača a okruh v kilometroch.
     *
     * Všetky tri hodnoty musia dávať zmysel spolu, inak sa filter ticho vypne
     * a vráti sa bežný výpis. Neplatná poloha nie je dôvod na chybu: prichádza
     * z `navigator.geolocation`, teda z prostredia, ktoré nemáme pod kontrolou,
     * a prázdny zoznam s hláškou 422 by vyzeral ako porucha portálu.
     *
     * Okruh je zhora obmedzený — nad 200 km už „okolie" nie je filter, ale celá
     * krajina, a databáze by zostalo len počítanie funkcie nad všetkým.
     *
     * @return array{0: ?float, 1: ?float, 2: ?float}
     */
    private function nearby(Request $request): array
    {
        if (! $request->filled(['latitude', 'longitude', 'radius_km'])) {
            return [null, null, null];
        }

        $latitude = (float) $request->input('latitude');
        $longitude = (float) $request->input('longitude');
        $radiusKm = (float) $request->input('radius_km');

        $valid = $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180
            && $radiusKm > 0;

        if (! $valid) {
            return [null, null, null];
        }

        return [$latitude, $longitude, min($radiusKm, 200.0)];
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

        return response()->json([
            'data' => $this->eventRepository->publicMunicipalityOverview($scope),
            'meta' => ['scope' => $scope],
        ]);
    }
}
