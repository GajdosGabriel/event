<?php

namespace Tests\Feature\Imports;

use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequeueAiDetectorSkippedSourcesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $detector): Event
    {
        $canal = Canal::factory()->create();
        $user = User::factory()->create(['canal_id' => $canal->id]);
        $venue = Venue::factory()->create(['canal_id' => $canal->id]);

        return Event::factory()->create([
            'canal_id' => $canal->id, 'user_id' => $user->id, 'venue_id' => $venue->id,
            'published_at' => now(), 'orginal_source' => 'https://www.vyveska.sk/put-padova.html',
            'meta' => ['import' => ['source' => 'hlascirkvi_legacy'], 'ai_detector' => $detector],
        ]);
    }

    private function migrate(): void
    {
        (require database_path('migrations/2026_09_13_100000_requeue_ai_detector_skipped_sources.php'))->up();
    }

    #[Test]
    public function missing_and_unreadable_sources_are_requeued(): void
    {
        $missing = $this->event([
            'error' => 'HTTP chyba: 404', 'source_http_status' => 404, 'attempts' => 1,
            'skipped_at' => '2026-09-11T14:03:02+00:00', 'retry_at' => null,
        ]);
        $unreadable = $this->event([
            'error' => 'Element pre hlavny obsah sa nenasiel', 'source_http_status' => null, 'attempts' => 1,
            'skipped_at' => '2026-09-11T14:03:02+00:00', 'retry_at' => null,
        ]);

        $this->migrate();

        foreach ([$missing, $unreadable] as $event) {
            $detector = $event->fresh()->meta['ai_detector'];
            $this->assertArrayNotHasKey('skipped_at', $detector);
            $this->assertArrayNotHasKey('attempts', $detector);
            // Posledná chyba ostáva pre dohľadanie.
            $this->assertArrayHasKey('error', $detector);
            $this->assertSame('hlascirkvi_legacy', $event->fresh()->meta['import']['source']);
        }
    }

    #[Test]
    public function events_given_up_after_repeated_transient_failures_stay_skipped(): void
    {
        $flaky = $this->event([
            'error' => 'HTTP chyba: 503', 'source_http_status' => 503, 'attempts' => 5,
            'skipped_at' => '2026-09-11T14:03:02+00:00', 'retry_at' => null,
        ]);
        $timeout = $this->event([
            'error' => 'cURL Error: timeout', 'source_http_status' => null, 'attempts' => 5,
            'skipped_at' => '2026-09-11T14:03:02+00:00', 'retry_at' => null,
        ]);

        $this->migrate();

        $this->assertNotNull($flaky->fresh()->meta['ai_detector']['skipped_at'] ?? null);
        $this->assertNotNull($timeout->fresh()->meta['ai_detector']['skipped_at'] ?? null);
    }
}
