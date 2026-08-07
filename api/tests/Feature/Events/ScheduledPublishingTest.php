<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Naplánované publikovanie (roadmap 3.6).
 *
 * `published_at` je čas prvého zverejnenia, `publish_at` je plánovaný termín —
 * testy strážia hlavne to, aby sa tie dva stĺpce nezamieňali a aby naplánované
 * podujatie nebolo verejné skôr, než mu nastane čas.
 */
class ScheduledPublishingTest extends EventSetupTest
{
    /** Payload formulára s prepísateľnými poliami. */
    private function payload(array $overrides = []): array
    {
        return array_merge($this->futureEvent->toArray(), [
            'name' => 'Naplánované podujatie - ' . Str::random(5),
            'start_at' => now()->addDays(10),
            'end_at' => now()->addDays(10)->addHours(2),
            'registration_deadline_at' => null,
            'published_at' => null,
        ], $overrides);
    }

    #[Test]
    public function event_can_be_scheduled_for_a_future_publish_date(): void
    {
        $this->futureEvent->update(['status' => ModelStatus::Draft->value, 'published_at' => null]);

        $publishAt = now()->addDays(2)->startOfHour();

        $response = $this->putJson("/api/dashboard/events/{$this->futureEvent->id}", $this->payload([
            'status' => ModelStatus::Scheduled->value,
            'publish_at' => $publishAt->toDateTimeString(),
        ]));

        $response->assertStatus(200);

        $event = $this->futureEvent->fresh();

        $this->assertSame(ModelStatus::Scheduled, $event->status);
        $this->assertSame($publishAt->toDateTimeString(), $event->publish_at?->toDateTimeString());
        $this->assertNull($event->published_at, 'Naplánované podujatie ešte nebolo zverejnené.');
    }

    #[Test]
    public function scheduled_status_requires_a_future_publish_date(): void
    {
        $this->futureEvent->update(['status' => ModelStatus::Draft->value, 'published_at' => null]);

        $this->putJson("/api/dashboard/events/{$this->futureEvent->id}", $this->payload([
            'status' => ModelStatus::Scheduled->value,
            'publish_at' => null,
        ]))->assertStatus(422)->assertJsonValidationErrors('publish_at');

        $this->putJson("/api/dashboard/events/{$this->futureEvent->id}", $this->payload([
            'status' => ModelStatus::Scheduled->value,
            'publish_at' => now()->subHour()->toDateTimeString(),
        ]))->assertStatus(422)->assertJsonValidationErrors('publish_at');
    }

    #[Test]
    public function scheduled_event_is_not_publicly_visible_before_its_time(): void
    {
        $this->futureEvent->update([
            'status' => ModelStatus::Scheduled->value,
            'published_at' => null,
            'publish_at' => now()->addDay(),
        ]);

        $this->getJson("/api/events/{$this->futureEvent->id}")->assertStatus(404);
    }

    #[Test]
    public function command_publishes_events_whose_time_has_come(): void
    {
        $publishAt = now()->subMinutes(3);

        $this->futureEvent->update([
            'status' => ModelStatus::Scheduled->value,
            'published_at' => null,
            'publish_at' => $publishAt,
        ]);

        // Termín ešte nenastal — tento musí ostať nedotknutý.
        $notYet = Event::query()->find($this->pastEvent->id);
        $notYet->update([
            'status' => ModelStatus::Scheduled->value,
            'published_at' => null,
            'publish_at' => now()->addDay(),
        ]);

        $this->artisan('app:events-publish-scheduled')->assertSuccessful();

        $published = $this->futureEvent->fresh();

        $this->assertSame(ModelStatus::Published, $published->status);
        $this->assertNull($published->publish_at, 'Po zverejnení už nie je na čo čakať.');
        $this->assertSame(
            $publishAt->toDateTimeString(),
            $published->published_at?->toDateTimeString(),
            'published_at je plánovaný čas, nie čas behu príkazu.'
        );

        $this->assertSame(ModelStatus::Scheduled, $notYet->fresh()->status);
    }

    #[Test]
    public function command_keeps_the_original_first_publish_time(): void
    {
        $firstPublished = now()->subMonth()->startOfHour();

        $this->futureEvent->update([
            'status' => ModelStatus::Scheduled->value,
            'published_at' => $firstPublished,
            'publish_at' => now()->subMinute(),
        ]);

        $this->artisan('app:events-publish-scheduled')->assertSuccessful();

        $this->assertSame(
            $firstPublished->toDateTimeString(),
            $this->futureEvent->fresh()->published_at?->toDateTimeString()
        );
    }

    #[Test]
    public function leaving_the_scheduled_status_drops_the_planned_date(): void
    {
        $this->futureEvent->update([
            'status' => ModelStatus::Scheduled->value,
            'published_at' => null,
            'publish_at' => now()->addWeek(),
        ]);

        $this->putJson("/api/dashboard/events/{$this->futureEvent->id}", $this->payload([
            'status' => ModelStatus::Draft->value,
        ]))->assertStatus(200);

        $this->assertNull(
            $this->futureEvent->fresh()->publish_at,
            'Koncept nesmie ostať v čakárni príkazu app:events-publish-scheduled.'
        );
    }

    #[Test]
    public function scheduled_event_can_be_published_manually_ahead_of_time(): void
    {
        $this->futureEvent->update([
            'status' => ModelStatus::Scheduled->value,
            'published_at' => null,
            'publish_at' => now()->addWeek(),
        ]);

        $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/publish", ['published' => true])
            ->assertStatus(200);

        $event = $this->futureEvent->fresh();

        $this->assertSame(ModelStatus::Published, $event->status);
        $this->assertNotNull($event->published_at);
        $this->assertNull($event->publish_at);
    }
}
