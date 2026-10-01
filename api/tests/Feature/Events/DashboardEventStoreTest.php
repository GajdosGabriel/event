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

    #[Test]
    public function selected_organizer_is_used_instead_of_active_organizer(): void
    {
        $canal = \App\Models\Canal::factory()->active()->create();
        $this->user->canals()->attach($canal->id, [
            'role' => 'editor', 'is_owner' => false, 'status' => 'published',
        ]);
        $this->user->forgetCanalRoles();
        $this->postJson('/api/dashboard/events', [
            'name' => 'Selected organizer event', 'canal_id' => $canal->id, 'status' => 'draft',
        ])->assertCreated()->assertJsonPath('canal_id', $canal->id);
    }

    #[Test]
    public function selected_organizer_requires_event_creation_permission(): void
    {
        $canal = \App\Models\Canal::factory()->active()->create();
        $payload = ['name' => 'Forbidden organizer event', 'canal_id' => $canal->id, 'status' => 'draft'];
        $this->postJson('/api/dashboard/events', $payload)->assertForbidden();
        $this->user->canals()->attach($canal->id, [
            'role' => 'checkin', 'is_owner' => false, 'status' => 'published',
        ]);
        $this->user->forgetCanalRoles();
        $this->postJson('/api/dashboard/events', $payload)->assertForbidden();
        $this->assertDatabaseMissing('events', ['name' => $payload['name']]);
    }

    #[Test]
    public function form_times_without_zone_are_stored_as_bratislava_local_time(): void
    {
        $start = Carbon::now('Europe/Bratislava')->addMonths(3)->setTime(18, 0);

        $response = $this->postJson('/api/dashboard/events', [
            'name' => 'Časová zóna ' . uniqid(),
            'start_at' => $start->format('Y-m-d\TH:i'),
            'end_at' => $start->copy()->addHours(2)->format('Y-m-d\TH:i'),
        ])->assertCreated();

        $event = Event::query()->findOrFail($response->json('id'));

        $this->assertTrue($event->start_at->equalTo($start));
        $this->assertStringEndsWith('18:00 - 20:00', $response->json('date_range_label'));
    }

    #[Test]
    public function event_created_directly_as_published_gets_published_at(): void
    {
        $response = $this->postJson('/api/dashboard/events', [
            'name' => 'Rovno publikované ' . uniqid(),
            'status' => ModelStatus::Published->value,
        ])->assertCreated();

        $this->assertNotNull($response->json('published_at'));
        $this->assertNotNull(Event::query()->findOrFail($response->json('id'))->published_at);
    }
}
