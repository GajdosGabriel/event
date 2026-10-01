<?php

namespace App\Http\Controllers\Public;

use App\Enums\TagGroup;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Repositories\Contracts\EventRepository;
use App\Support\PublicEventFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TagController extends Controller
{
    public function __construct(private readonly EventRepository $eventRepository) {}

    /**
     * Číselník štítkov zoskupený podľa facetu, s počtom podujatí vo výbere,
     * ktorý má návštevník pred sebou (obdobie, obec, už zvolené štítky,
     * hľadanie). Počty slúžia na to, aby filter neponúkal štítky, pod ktorými
     * nič nie je, a aby číslo sedelo s výpisom — rovnaká logika ako pri
     * obecnom facete.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = PublicEventFilters::fromRequest($request);

        // Cache je krátka: počty sa menia s každým publikovaním aj s tým, ako
        // podujatia prirodzene odchádzajú z „nadchádzajúcich". Kľúč nesie
        // filtre, inak by sa počty z jedného výberu ukazovali v inom.
        $counts = Cache::remember(
            'tags:counts:'.md5(json_encode($filters)),
            now()->addMinutes(15),
            fn () => DB::table('event_tag')
                ->whereIn('event_id', $this->eventRepository->publicFacetQuery($filters))
                ->groupBy('tag_id')
                // Aliasy, nie DB::raw priamo v pluck() — pluck si z raw výrazu
                // nevie odvodiť názov stĺpca.
                ->selectRaw('tag_id, COUNT(DISTINCT event_id) as events_count')
                ->pluck('events_count', 'tag_id'),
        );

        $onlyUsed = $request->boolean('only_used');
        // Zvolený štítok ostáva v ponuke aj pri nulovom počte — inak by pri
        // prázdnom výsledku nebolo čo odkliknúť.
        $selected = $filters['tags'] ?? [];

        $groups = Tag::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'slug' => $tag->slug,
                'name' => $tag->name,
                'group' => $tag->group?->value,
                'emoji' => $tag->emoji,
                'events_count' => (int) ($counts[$tag->id] ?? 0),
            ])
            ->when($onlyUsed, fn ($tags) => $tags->filter(
                fn (array $tag) => $tag['events_count'] > 0 || in_array($tag['slug'], $selected, true)
            ))
            ->groupBy('group');

        return response()->json([
            'data' => $groups
                ->map(fn ($tags, $group) => [
                    'group' => $group,
                    'label' => TagGroup::tryFrom((string) $group)?->label() ?? $group,
                    'tags' => $tags->values(),
                ])
                ->values(),
        ]);
    }
}
