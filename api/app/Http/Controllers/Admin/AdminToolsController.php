<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AdminToolsController extends Controller
{
    public function runImportEvents(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Event::class);

        $validated = $request->validate([
            'urls' => 'sometimes|array',
            'urls.*' => 'url',
            'pages' => 'sometimes|integer|min:1|max:20',
            'limit' => 'sometimes|integer|min:0|max:100',
        ]);

        $options = ['--pages' => $validated['pages'] ?? 1, '--limit' => $validated['limit'] ?? 0];
        foreach ($validated['urls'] ?? [] as $url) {
            $options['--url'][] = $url;
        }

        Artisan::call('app:import-event-sources', $options);
        $output = Artisan::output();

        return response()->json(['success' => true, 'output' => $output]);
    }

    public function runAiDetector(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Event::class);

        Artisan::call('app:ai-detector');
        $output = Artisan::output();

        return response()->json(['success' => true, 'output' => $output]);
    }

    public function runArchiveEvents(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Event::class);

        Artisan::call('app:archive-finished-events');
        $output = Artisan::output();

        return response()->json(['success' => true, 'output' => $output]);
    }
}
