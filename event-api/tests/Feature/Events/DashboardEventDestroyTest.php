<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardEventDestroyTest extends EventSetupTest
{
    #[Test]
    public function user_can_delete_event_from_dashboard_scope(): void
    {
        $response = $this->deleteJson('/api/dashboard/events/' . $this->futureEvent->id);

        $response->assertNoContent();

        $this->assertSoftDeleted('events', [
            'id' => $this->futureEvent->id,
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $this->futureEvent->id,
            'status' => ModelStatus::Draft->value,
        ]);
    }

    #[Test]
    public function restored_dashboard_event_remains_draft_after_delete(): void
    {
        $this->deleteJson('/api/dashboard/events/' . $this->futureEvent->id)
            ->assertNoContent();

        $response = $this->postJson('/api/dashboard/events/' . $this->futureEvent->id . '/restore');

        $response->assertOk();

        $this->assertDatabaseHas('events', [
            'id' => $this->futureEvent->id,
            'status' => ModelStatus::Draft->value,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function user_cannot_delete_foreign_event(): void
    {
        $foreignCanal = Canal::factory()->create();
        $foreignVenue = Venue::factory()->create([
            'canal_id' => $foreignCanal->id,
            'village_id' => (int) $foreignCanal->municipality_id,
        ]);

        $foreignEvent = Event::factory()->create([
            'canal_id' => $foreignCanal->id,
            'venue_id' => $foreignVenue->id,
        ]);

        $response = $this->deleteJson('/api/dashboard/events/' . $foreignEvent->id);

        $response->assertStatus(404);

        $this->assertNotSoftDeleted('events', [
            'id' => $foreignEvent->id,
        ]);
    }
}
