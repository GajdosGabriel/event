<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\TicketType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class EventDuplicateTest extends EventSetupTest
{
    #[Test]
    public function archived_event_can_be_duplicated_into_a_draft_with_ticket_types(): void
    {
        $archived = Event::query()->create([
            'name' => 'Archived original ' . uniqid(),
            'status' => ModelStatus::Archived->value,
            'canal_id' => $this->pastEvent->canal_id,
            'venue_id' => $this->pastEvent->venue_id,
            'user_id' => $this->user->id,
            'start_at' => now()->subDays(10),
            'end_at' => now()->subDays(9),
            'published_at' => now()->subDays(10),
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $archived->id,
            'name' => 'VIP',
            'price_amount' => 1500,
            'capacity' => 20,
            'sale_starts_at' => now()->subDays(11),
            'sale_ends_at' => now()->subDays(10),
        ]);

        $response = $this->postJson('/api/dashboard/events/' . $archived->id . '/duplicate');

        $response->assertStatus(201)
            ->assertJsonPath('status', ModelStatus::Draft->value)
            ->assertJsonPath('start_at', null)
            ->assertJsonPath('end_at', null)
            ->assertJsonPath('published_at', null);

        $newId = $response->json('id');
        $this->assertNotEquals($archived->id, $newId);

        $this->assertDatabaseHas('events', [
            'id' => $newId,
            'status' => ModelStatus::Draft->value,
            'name' => $archived->name . ' (kópia)',
            'canal_id' => $archived->canal_id,
            'venue_id' => $archived->venue_id,
            'start_at' => null,
        ]);

        $this->assertDatabaseHas('ticket_types', [
            'event_id' => $newId,
            'name' => 'VIP',
            'price_amount' => 1500,
            'capacity' => 20,
            'sale_starts_at' => null,
        ]);

        // Original stays untouched.
        $this->assertDatabaseHas('events', [
            'id' => $archived->id,
            'status' => ModelStatus::Archived->value,
        ]);
        $this->assertDatabaseHas('ticket_types', [
            'id' => $ticketType->id,
            'event_id' => $archived->id,
        ]);
    }

    #[Test]
    public function archived_update_still_returns_403(): void
    {
        $archived = Event::query()->create([
            'name' => 'Archived locked ' . uniqid(),
            'status' => ModelStatus::Archived->value,
            'canal_id' => $this->pastEvent->canal_id,
            'venue_id' => $this->pastEvent->venue_id,
            'user_id' => $this->user->id,
        ]);

        $this->putJson('/api/dashboard/events/' . $archived->id, ['name' => 'Changed'])
            ->assertStatus(403);
    }

    #[Test]
    public function duplicate_of_foreign_canal_event_is_not_found(): void
    {
        // dashboardShow() scopes the query to the user's own canals, so an event from
        // another canal 404s before the duplicate policy even runs — same as update/delete.
        $this->postJson('/api/dashboard/events/' . $this->cudziEvent->id . '/duplicate')
            ->assertStatus(404);
    }
}
