<?php

namespace App\Http\Controllers\Public;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\Event;
use App\Models\Venue;
use App\Repositories\Contracts\VenueRepository;
use Illuminate\Http\JsonResponse;

class VenueController extends Controller
{
    public function __construct(protected VenueRepository $venueRepository)
    {}

    public function show($id)
    {
        $venue = $this->venueRepository->publicShow($id);

        if (! $venue) {
            abort(404);
        }

        $data = $venue->toArray();
        // Kontaktovateľné len ak má miesto vlastnícky kanál s aktívnym majiteľom
        // (a návštevník ním nie je sám).
        $data['contactable'] = $venue->isContactableBy(auth('sanctum')->user());

        return response()->json($data);
    }

    public function files($id)
    {
        $venue = Venue::findOrFail($id);
        $files = $venue->files()->orderBy('sort_order')->orderBy('id')->get();
        return response()->json(FileResource::collection($files));
    }

    public function events($id): JsonResponse
    {
        $venue = Venue::findOrFail($id);

        $events = Event::where('venue_id', $venue->id)
            ->where('status', ModelStatus::Published->value)
            ->with('canal:id,name')
            ->orderByDesc('start_at')
            ->limit(100)
            ->get(['id', 'name', 'start_at', 'end_at', 'status', 'canal_id']);

        return response()->json($events->map(fn ($ev) => [
            'id' => $ev->id,
            'name' => $ev->name,
            'start_at' => $ev->start_at,
            'end_at' => $ev->end_at,
            'status' => $ev->status,
            'canal_id' => $ev->canal_id,
            'canal_name' => $ev->canal?->name,
        ]));
    }
}
