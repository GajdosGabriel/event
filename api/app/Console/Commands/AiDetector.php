<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\OpenAI\Detector;

class AiDetector extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ai-detector';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and process AI content for events';

    /**
     * Execute the console command.
     */
    public function handle(Detector $detector): int
    {
        $event = Event::query()
            ->whereNotNull('published_at')
            ->whereNotNull('orginal_source')
            ->whereNull('body_ai')
            ->orderByDesc('created_at')
            ->first();

        if (! $event instanceof Event) {
            $this->info('AiDetector: no eligible event found.');

            return self::SUCCESS;
        }

        $result = $detector->detectFromUrl((string) $event->orginal_source);

        if (! ($result['success'] ?? false)) {
            Log::warning('AiDetector failed for event.', [
                'event_id' => $event->id,
                'source_url' => $event->orginal_source,
                'error' => $result['error'] ?? 'Unknown detector error',
            ]);

            $this->warn('AiDetector failed for event id ' . $event->id . '.');

            return self::FAILURE;
        }

        $meta = is_array($event->meta) ? $event->meta : [];
        $meta['ai_detector'] = [
            'processed_at' => now()->toIso8601String(),
            'source_url' => $event->orginal_source,
            'links' => $result['links'] ?? [],
            'attachments' => $result['attachments'] ?? [],
            'event_payload' => $result['event_payload'] ?? null,
        ];

        $event->update([
            'body_ai' => is_string($result['extracted_text'] ?? null) && trim((string) $result['extracted_text']) !== ''
                ? trim((string) $result['extracted_text'])
                : null,
            'meta' => $meta,
        ]);

        $this->info('AiDetector processed event id ' . $event->id . '.');

        return self::SUCCESS;
    }
}
