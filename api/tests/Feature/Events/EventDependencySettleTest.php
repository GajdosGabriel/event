<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Services\OpenAI\Detector;
use App\Services\Imports\ImportedVenueManager;
use App\Services\Publishing\EventDependencyPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventDependencySettleTest extends TestCase
{
    use RefreshDatabase;

    private function event(Venue $venue, ModelStatus $status, string $startAt): Event
    {
        $user = User::factory()->create(['canal_id' => $venue->canal_id]);

        return Event::factory()->create([
            'canal_id' => $venue->canal_id, 'user_id' => $user->id, 'venue_id' => $venue->id,
            'status' => $status->value, 'start_at' => $startAt, 'end_at' => $startAt,
            'published_at' => now(),
        ]);
    }

    private function draftVenue(): Venue
    {
        $canal = Canal::factory()->create(['status' => ModelStatus::Published->value]);

        return Venue::factory()->create(['canal_id' => $canal->id, 'status' => ModelStatus::Draft->value]);
    }

    #[Test]
    public function archived_event_moves_its_draft_venue_to_the_archive(): void
    {
        $venue = $this->draftVenue();
        $event = $this->event($venue, ModelStatus::Archived, now()->subYear()->toDateTimeString());

        app(EventDependencyPublisher::class)->settle($event);

        $this->assertSame(ModelStatus::Archived, $venue->fresh()->status);
    }

    #[Test]
    public function draft_venue_with_an_upcoming_event_is_left_alone(): void
    {
        $venue = $this->draftVenue();
        $archived = $this->event($venue, ModelStatus::Archived, now()->subYear()->toDateTimeString());
        $this->event($venue, ModelStatus::Draft, now()->addMonth()->toDateTimeString());

        app(EventDependencyPublisher::class)->settle($archived);

        $this->assertSame(ModelStatus::Draft, $venue->fresh()->status);
    }

    #[Test]
    public function published_event_publishes_its_draft_venue(): void
    {
        $venue = $this->draftVenue();
        $event = $this->event($venue, ModelStatus::Published, now()->addMonth()->toDateTimeString());

        app(EventDependencyPublisher::class)->settle($event);

        $this->assertSame(ModelStatus::Published, $venue->fresh()->status);
    }

    #[Test]
    public function ai_detector_archives_the_venue_it_created_for_an_archived_event(): void
    {
        $canal = Canal::factory()->create(['status' => ModelStatus::Published->value]);
        $fallback = Venue::factory()->create(['canal_id' => $canal->id, 'category' => 'fallback', 'slug' => 'cele-slovensko', 'status' => ModelStatus::Published->value]);
        $detected = Venue::factory()->create(['canal_id' => $canal->id, 'name' => 'Kultúrny dom', 'status' => ModelStatus::Draft->value]);
        $user = User::factory()->create(['canal_id' => $canal->id]);
        $event = Event::factory()->create([
            'canal_id' => $canal->id, 'user_id' => $user->id, 'venue_id' => $fallback->id,
            'status' => ModelStatus::Archived->value,
            'start_at' => now()->subYears(2), 'end_at' => now()->subYears(2),
            'published_at' => now()->subYears(2), 'orginal_source' => 'https://example.test/ples', 'body_rewritten_at' => null,
        ]);

        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromUrl')->once()->andReturn([
            'success' => true,
            'corrected_text' => null,
            'event_payload' => ['venue' => ['name' => 'Kultúrny dom', 'city' => 'Trnava']],
        ]);
        $this->app->instance(Detector::class, $detector);

        $venueManager = Mockery::mock(ImportedVenueManager::class);
        $venueManager->shouldReceive('resolveOrDetect')->once()->andReturn($detected);
        $this->app->instance(ImportedVenueManager::class, $venueManager);

        $this->artisan('app:ai-detector')->assertSuccessful();

        $this->assertSame($detected->id, $event->fresh()->venue_id);
        $this->assertSame(ModelStatus::Archived, $detected->fresh()->status);
    }

    #[Test]
    public function migration_retires_draft_venues_used_by_events(): void
    {
        $past = $this->draftVenue();
        $this->event($past, ModelStatus::Archived, now()->subYear()->toDateTimeString());
        $upcoming = $this->draftVenue();
        $this->event($upcoming, ModelStatus::Published, now()->addMonth()->toDateTimeString());
        $unused = $this->draftVenue();

        (require database_path('migrations/2026_09_14_100000_retire_draft_records_used_by_events_again.php'))->up();

        $this->assertSame(ModelStatus::Archived, $past->fresh()->status);
        $this->assertSame(ModelStatus::Published, $upcoming->fresh()->status);
        $this->assertSame(ModelStatus::Draft, $unused->fresh()->status);
    }
}
