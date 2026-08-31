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
            ->whereNull('body_rewritten_at')
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

        // Podujatie je spracované (claim `body_rewritten_at IS NULL`), nech sa
        // ďalší beh posunie na staršie. Tvrdé zlyhania OpenAI sem nedôjdu —
        // rieši ich `$result['success']` vyššie a tie sa skúšajú znova.
        $payload = ['meta' => $meta, 'body_rewritten_at' => now()];

        // Popis prepíšeme len keď máme skutočný copywriter HTML. Surový extrakt
        // je jeden zlepený odstavec bez formátovania a bez „Odkazov" — horší než
        // to, čo už v `body` je z importu.
        $rewritten = $this->pickString($result['corrected_text'] ?? null);
        if ($rewritten !== null) {
            $raw = (string) ($event->body ?? '');
            if ($raw !== '' && empty($meta['imported_raw_body'])) {
                $meta['imported_raw_body'] = $raw;
                $payload['meta'] = $meta;
            }
            $payload['body'] = $rewritten;
        }

        $event->update($payload);

        $this->info('AiDetector processed event id ' . $event->id . '.');

        return self::SUCCESS;
    }

    private function pickString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
