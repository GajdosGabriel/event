<?php

namespace Tests\Feature\Events;

use PHPUnit\Framework\Attributes\Test;
use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Venue;
use Carbon\Carbon;
use Tests\TestSupport\EventSetupTest;

class DashboardEventStoreTest extends EventSetupTest
{

    #[Test]
    public function an_event_can_be_created_through_the_form(): void
    {
        $venue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $this->canalPrimary->id))
            ->firstOrFail();

        // 1. Príprava dát eventu
        $eventForm = Event::factory()->future()->make([
            'user_id' => $this->user->id,
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Draft->value,
        ])->toArray();

        // 2. Odoslanie požiadavky
        $response = $this->postJson('/api/dashboard/events', $eventForm);

        // 3. Overenie odpovede
        $response->assertStatus(201); // Laravel štandard pre "created"

        $response->assertJsonFragment([
            'name' => $eventForm['name'],
            'canal_id' => $eventForm['canal_id'],
        ]);

        $this->assertArrayHasKey('id', $response->json(), 'Odpoveď by mala obsahovať ID vytvoreného eventu.');

        // 4. Overenie ukladania do databázy
        $this->assertDatabaseHas('events', [
            'id' => $response->json('id'),
            'name' => $eventForm['name'],
            'slug' => $eventForm['slug'],
            'body' => $eventForm['body'],
            'published_at' => Carbon::parse($eventForm['published_at'])->format('Y-m-d H:i:s'),
            'status' => $eventForm['status'],
            'start_at' => Carbon::parse($eventForm['start_at'])->format('Y-m-d H:i:s'),
            'end_at' => Carbon::parse($eventForm['end_at'])->format('Y-m-d H:i:s'),
            'registration_deadline_at' => Carbon::parse($eventForm['registration_deadline_at'])->format('Y-m-d H:i:s'),
            'website' => $eventForm['website'],
            'venue_id' => $eventForm['venue_id'],
            'canal_id' => $eventForm['canal_id'],
        ]);
    }


    #[Test]
    public function an_event_can_be_created_with_a_specific_status()
    {
        $venue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $this->canalPrimary->id))
            ->firstOrFail();

        // 2. Vytvorenie dát eventu
        $eventData = Event::factory()->future()->make([
            'status' => ModelStatus::Draft->value,
            'user_id' => $this->user->id,
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
        ])->toArray();


        // 3. Formátovanie všetkých dátumových polí
        $eventData['published_at'] = $eventData['published_at'];
        $eventData['start_at'] = $eventData['start_at'];
        $eventData['end_at'] = $eventData['end_at'];
        $eventData['registration_deadline_at'] = $eventData['registration_deadline_at'];
        // 3. Odoslanie požiadavky
        $response = $this->postJson('/api/dashboard/events', $eventData);

        // 4. Overenie odpovede
        $response->assertStatus(201); // Očakávame 201 Created pre API

        // 5. Overenie v databáze
        $this->assertDatabaseHas('events', [
            'name' => $eventData['name'],
            'status' => ModelStatus::Draft->value,
            'user_id' => $this->user->id // Overenie vlastníctva
        ]);
    }

    #[Test]
    public function draft_event_can_be_created_with_only_title(): void
    {
        $eventData = [
            'name' => 'Draft only title ' . uniqid(),
        ];

        $response = $this->postJson('/api/dashboard/events', $eventData);

        $response
            ->assertStatus(201)
            ->assertJsonPath('name', $eventData['name'])
            ->assertJsonPath('status', ModelStatus::Draft->value)
            ->assertJsonPath('venue_id', null);

        $this->assertDatabaseHas('events', [
            'id' => $response->json('id'),
            'name' => $eventData['name'],
            'status' => ModelStatus::Draft->value,
            'venue_id' => null,
            'canal_id' => $this->canalPrimary->id,
            'user_id' => $this->user->id,
        ]);
    }
}
