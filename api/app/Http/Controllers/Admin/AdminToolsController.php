<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportEventSourcesJob;
use App\Support\ToolRunTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

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
            'force' => 'sometimes|boolean',
        ]);

        $options = ['--pages' => $validated['pages'] ?? 1, '--limit' => $validated['limit'] ?? 0];
        if ($validated['force'] ?? false) {
            $options['--force'] = true;
        }
        foreach ($validated['urls'] ?? [] as $url) {
            $options['--url'][] = $url;
        }

        // Run on the queue: an unbounded import (limit = 0) can process many detail
        // pages and would exceed the HTTP request's execution time. Force the database
        // connection so it is queued even when the default connection is 'sync'.
        $runId = (string) Str::uuid();
        ToolRunTracker::start($runId, 'import-events');

        ImportEventSourcesJob::dispatch($runId, $options)
            ->onConnection('database')
            ->onQueue('imports');

        return response()->json([
            'success' => true,
            'run_id' => $runId,
            'status' => 'queued',
        ], 202);
    }

    public function importRunStatus(string $runId): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Event::class);

        $run = ToolRunTracker::get($runId);

        if ($run === null) {
            return response()->json(['message' => 'Beh sa nenašiel alebo vypršal.'], 404);
        }

        return response()->json($run);
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

        Artisan::call('app:events-archive-finished');
        $output = Artisan::output();

        return response()->json(['success' => true, 'output' => $output]);
    }
}
