<?php

namespace App\Http\Controllers\Public;

use App\Enums\AnnouncementPlacement;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Aktívne oznamy pre verejný layout. Volá sa na každej stránke, preto vracia
 * len to, čo sa naozaj zobrazuje — filtrovanie podľa umiestnenia rieši `?placement=`.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'placement' => ['nullable', Rule::in(AnnouncementPlacement::values())],
        ]);

        $announcements = Announcement::query()
            ->activeForPublic()
            ->when(
                isset($validated['placement']),
                fn ($query) => $query->where('placement', $validated['placement'])
            )
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return AnnouncementResource::collection($announcements);
    }
}
