<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\EventRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    protected $eventRepository;

    public function __construct(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    public function index(Request $request)
    {
        $municipality = $request->integer('municipality') ?: null;

        return response()->json(
            $this->eventRepository->publicIndexWithFilters(15, ['municipality' => $municipality])
        );
    }

    public function show($id)
    {
        return response()->json($this->eventRepository->publicShow($id));
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
