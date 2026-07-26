<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Stats\OverviewStats;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /** Prehľad za celý systém — bez obmedzenia na kanály. */
    public function index(): JsonResponse
    {
        return response()->json(
            OverviewStats::forSystem()->build()
        );
    }
}
