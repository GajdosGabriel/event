<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TagSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Prehľad výrazov, ktoré AI chýbali v číselníku štítkov.
 *
 * Slúži ako podklad na doplnenie database/seeders/TagSeeder.php — číselník sa
 * needituje cez API, lebo AI ho dostáva ako uzavretý enum a rozšírenie musí ísť
 * spolu s preštítkovaním (to zabezpečí verzia číselníka v ai_tags_hash).
 */
class TagSuggestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => ['nullable', 'in:promoted,rejected,unresolved'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $resolution = $validated['resolution'] ?? 'unresolved';

        $query = TagSuggestion::query()
            ->when($resolution === 'unresolved', fn ($q) => $q->unresolved())
            ->when($resolution !== 'unresolved', fn ($q) => $q->where('resolution', $resolution))
            ->orderByDesc('occurrences')
            ->orderByDesc('last_seen_at');

        return response()->json(
            $query->paginate($validated['per_page'] ?? 50)
        );
    }

    /**
     * Označí návrh ako vybavený. Samotný štítok sa zakladá v seedri — tu sa len
     * návrh odloží, aby v zozname neprekážal.
     */
    public function update(Request $request, TagSuggestion $tagSuggestion): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', 'in:promoted,rejected'],
        ]);

        $tagSuggestion->update(['resolution' => $validated['resolution']]);

        return response()->json($tagSuggestion);
    }
}
