<?php

namespace Tests\Feature\Imports;

use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequeueFlatAiEventBodiesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attributes): Event
    {
        $canal = Canal::factory()->create();
        $user = User::factory()->create(['canal_id' => $canal->id]);
        $venue = Venue::factory()->create(['canal_id' => $canal->id]);

        return Event::factory()->create([
            'canal_id' => $canal->id, 'user_id' => $user->id, 'venue_id' => $venue->id,
            'published_at' => now(), 'orginal_source' => 'https://example.test/e',
            ...$attributes,
        ]);
    }

    private function migrate(): void
    {
        (require database_path('migrations/2026_09_11_100000_requeue_flat_ai_event_bodies.php'))->up();
    }

    #[Test]
    public function flat_extract_is_replaced_by_the_raw_body_and_requeued(): void
    {
        $raw = '<p>Prvý odsek.</p><ul><li>Program</li></ul>';
        $event = $this->event([
            'body' => '<p>Zlepený extrakt celej stránky bez odsekov</p>',
            'body_rewritten_at' => now()->subWeek(),
            'meta' => ['imported_raw_body' => $raw, 'ai_detector' => ['processed_at' => 'x', 'retry_at' => 'y']],
        ]);

        $this->migrate();

        $event->refresh();
        $this->assertNull($event->body_rewritten_at);
        $this->assertStringContainsString('<ul>', (string) $event->body);
        $this->assertArrayNotHasKey('retry_at', $event->meta['ai_detector']);
        $this->assertSame($raw, $event->meta['imported_raw_body']);
    }

    #[Test]
    public function copywriter_html_and_events_without_raw_body_stay_untouched(): void
    {
        $rich = $this->event([
            'body' => '<h3>Program</h3><p>Text.</p>',
            'body_rewritten_at' => now()->subWeek(),
            'meta' => ['imported_raw_body' => '<p>Surový.</p>'],
        ]);
        $noRaw = $this->event([
            'body' => '<p>Jeden odsek.</p>',
            'body_rewritten_at' => now()->subWeek(),
            'meta' => ['import' => ['source' => 'hlascirkvi_legacy']],
        ]);

        $this->migrate();

        $this->assertNotNull($rich->fresh()->body_rewritten_at);
        $this->assertNotNull($noRaw->fresh()->body_rewritten_at);
        $this->assertSame('<p>Jeden odsek.</p>', $noRaw->fresh()->body);
    }
}
