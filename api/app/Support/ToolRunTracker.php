<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Lightweight status store for background admin-tool runs. Backed by the cache
 * (database store), so the web request that starts a run and the queue worker
 * that executes it can share progress through a short-lived run id.
 */
class ToolRunTracker
{
    private const TTL_SECONDS = 3600;

    public static function start(string $runId, string $tool): void
    {
        self::put($runId, [
            'run_id' => $runId,
            'tool' => $tool,
            'status' => 'queued',
            'output' => '',
            'queued_at' => now()->toDateTimeString(),
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    public static function markRunning(string $runId): void
    {
        self::merge($runId, [
            'status' => 'running',
            'started_at' => now()->toDateTimeString(),
        ]);
    }

    public static function markDone(string $runId, string $output): void
    {
        self::merge($runId, [
            'status' => 'done',
            'output' => $output,
            'finished_at' => now()->toDateTimeString(),
        ]);
    }

    public static function markFailed(string $runId, string $output): void
    {
        self::merge($runId, [
            'status' => 'failed',
            'output' => $output,
            'finished_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $runId): ?array
    {
        return Cache::get(self::key($runId));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function put(string $runId, array $data): void
    {
        Cache::put(self::key($runId), $data, self::TTL_SECONDS);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private static function merge(string $runId, array $changes): void
    {
        $current = self::get($runId) ?? ['run_id' => $runId];
        self::put($runId, array_merge($current, $changes));
    }

    private static function key(string $runId): string
    {
        return "tool_run:{$runId}";
    }
}
