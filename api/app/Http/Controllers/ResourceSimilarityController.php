<?php

namespace App\Http\Controllers;

use App\Models\{Canal, Venue, Organization};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ResourceSimilarityController extends Controller
{
    public function __invoke(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:250'],
            'municipality' => ['nullable', 'integer', 'min:1'],
            'exclude_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $kind = $request->route('resource');
        $class = ['canals' => Canal::class, 'venues' => Venue::class, 'organizations' => Organization::class][$kind];
        Gate::authorize('viewAny', $class);
        $query = $class::query();
        if (! $request->routeIs('admin.*')) {
            $ids = $request->user()->dashboardCanalIds();
            match ($kind) {
                'canals' => $query->whereIn('id', $ids),
                'venues' => $query->whereHas('canals', fn ($q) => $q->whereIn('canals.id', $ids)->where('canal_venue.status', 'published')),
                'organizations' => $query->whereIn('id', $request->user()->organizationIds()),
            };
        }
        $needle = self::normalize($input['name']);
        if (mb_strlen($needle) < 2) return response()->json(['data' => []]);
        $municipality = $kind === 'canals' ? 'municipality_id' : 'village_id';
        $label = $kind === 'organizations' ? 'title' : 'name';
        $columns = ['id', $label];
        if ($municipality) $columns[] = $municipality;
        $matches = $query->where('id', '!=', $input['exclude_id'] ?? 0)->select($columns)->get()
            ->filter(fn ($row) => str_contains(self::normalize($row->$label), $needle) && Gate::allows('view', $row))
            ->sortBy(fn ($row) => [
                self::normalize($row->$label) === $needle ? 0 : 1,
                $municipality && isset($input['municipality']) && $row->$municipality == $input['municipality'] ? 0 : 1,
                $row->id,
            ])->take(5)->map(fn ($row) => ['id' => $row->id, 'name' => $row->$label,
                'municipality_id' => $municipality ? $row->$municipality : null])->values();
        return response()->json(['data' => $matches]);
    }

    public static function normalize(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(Str::ascii($name))));
    }
}
