<?php

namespace App\Http\Controllers\Public;

use App\Enums\ModelStatus;
use App\Enums\TagGroup;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TagController extends Controller
{
    /**
     * Číselník štítkov zoskupený podľa facetu, s počtom nadchádzajúcich
     * podujatí. Počty slúžia na to, aby filter neponúkal štítky, pod ktorými
     * nič nie je — rovnaká logika ako pri obecnom facete.
     */
    public function index(Request $request): JsonResponse
    {
        // Cache je krátka: počty sa menia s každým publikovaním aj s tým, ako
        // podujatia prirodzene odchádzajú z „nadchádzajúcich".
        $counts = Cache::remember('tags:counts:upcoming', now()->addMinutes(15), function () {
            return DB::table('event_tag')
                ->join('events', 'events.id', '=', 'event_tag.event_id')
                ->where('events.status', ModelStatus::Published->value)
                ->whereNull('events.deleted_at')
                ->where(function ($query) {
                    $query->where('events.end_at', '>=', now())
                        ->orWhere(function ($inner) {
                            $inner->whereNull('events.end_at')
                                ->where('events.start_at', '>=', now()->startOfDay());
                        });
                })
                ->groupBy('event_tag.tag_id')
                // Aliasy, nie DB::raw priamo v pluck() — pluck si z raw výrazu
                // nevie odvodiť názov stĺpca.
                ->selectRaw('event_tag.tag_id as tag_id, COUNT(DISTINCT events.id) as events_count')
                ->pluck('events_count', 'tag_id');
        });

        $onlyUsed = $request->boolean('only_used');

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
            ->when($onlyUsed, fn ($tags) => $tags->filter(fn (array $tag) => $tag['events_count'] > 0))
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
