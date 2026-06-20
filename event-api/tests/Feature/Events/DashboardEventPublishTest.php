<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Venue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardEventPublishTest extends EventSetupTest
{
    #[Test]
    public function draft_event_publish_returns_validation_errors_for_missing_required_fields(): void
    {
        $event = Event::query()->create([
            'name' => 'Publish validation draft ' . uniqid(),
            'status' => ModelStatus::Draft->value,
            'canal_id' => $this->canalPrimary->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/dashboard/events/' . $event->id . '/publish');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_at', 'end_at', 'venue_id']);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => ModelStatus::Draft->value,
            'published_at' => null,
        ]);
    }

    #[Test]
    public function draft_event_can_be_published_when_required_fields_are_present(): void
    {
        $venue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $this->canalPrimary->id))
            ->firstOrFail();

        $event = Event::query()->create([
            'name' => 'Publishable draft ' . uniqid(),
            'status' => ModelStatus::Draft->value,
            'canal_id' => $this->canalPrimary->id,
            'user_id' => $this->user->id,
            'venue_id' => $venue->id,
            'start_at' => now()->addDays(3)->startOfHour(),
            'end_at' => now()->addDays(3)->addHours(2)->startOfHour(),
        ]);

        $response = $this->postJson('/api/dashboard/events/' . $event->id . '/publish');

        $response
            ->assertOk()
            ->assertJsonPath('id', $event->id)
            ->assertJsonPath('status', ModelStatus::Published->value);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => ModelStatus::Published->value,
            'venue_id' => $venue->id,
        ]);

        $this->assertNotNull($event->fresh()->published_at);
    }
}
