<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Stats\OverviewStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardHomeController extends Controller
{
    /** Prehľad za kanály, ku ktorým má prihlásený používateľ prístup. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            OverviewStats::forUser($request->user())->build()
        );
    }
}
