<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CanalRepository;
use App\Repositories\Contracts\VenueRepository;
use App\Services\Insights\RecordInsights;
use Illuminate\Http\JsonResponse;

/**
 * Údaje a časová os záznamu pre pravý stĺpec detailu v administrácii.
 * Prístup k záznamu rozhoduje repozitár (adminShow).
 */
class RecordInsightsController extends Controller
{
    public function canal(string $id, CanalRepository $canals, RecordInsights $insights): JsonResponse
    {
        return response()->json(['data' => $insights->for($canals->adminShow($id))]);
    }

    public function venue(string $id, VenueRepository $venues, RecordInsights $insights): JsonResponse
    {
        return response()->json(['data' => $insights->for($venues->adminShow($id))]);
    }
}
