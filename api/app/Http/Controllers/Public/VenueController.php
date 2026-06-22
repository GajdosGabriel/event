<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\Venue;
use App\Repositories\Contracts\VenueRepository;

class VenueController extends Controller
{
    public function __construct(protected VenueRepository $venueRepository)
    {}

    public function show($id)
    {
        return response()->json($this->venueRepository->publicShow($id));
    }

    public function files($id)
    {
        $venue = Venue::findOrFail($id);
        $files = $venue->files()->orderBy('sort_order')->orderBy('id')->get();
        return response()->json(FileResource::collection($files));
    }
}
