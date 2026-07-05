<?php

namespace App\Jobs;

use App\Support\ToolRunTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs the event-source import on the queue so the admin request returns
 * immediately. Without a limit the import can process many detail pages
 * (each doing network fetches, PDF conversion and AI detection), which would
 * otherwise blow past the HTTP request's execution time.
 */
class ImportEventSourcesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;
    public int $tries = 1;

    /**
     * @param array<string, mixed> $options Artisan options for app:import-event-sources
     */
    public function __construct(
        public readonly string $runId,
        public readonly array $options,
    ) {}

    public function handle(): void
    {
        ToolRunTracker::markRunning($this->runId);

        $exitCode = Artisan::call('app:import-event-sources', $this->options);
        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            ToolRunTracker::markDone($this->runId, $output !== '' ? $output : '(bez výstupu)');
        } else {
            ToolRunTracker::markFailed($this->runId, $output !== '' ? $output : 'Import skončil s chybovým kódom ' . $exitCode . '.');
        }
    }

    public function failed(\Throwable $exception): void
    {
        ToolRunTracker::markFailed($this->runId, $exception->getMessage());
    }
}
