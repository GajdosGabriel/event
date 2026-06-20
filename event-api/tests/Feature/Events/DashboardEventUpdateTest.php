<?php

namespace Tests\Feature\Events;


use PHPUnit\Framework\Attributes\Test;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Venue;
use Illuminate\Support\Str; // For generating random strings
use Tests\TestSupport\EventSetupTest;

class DashboardEventUpdateTest extends EventSetupTest
{
    protected $formEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->futureEvent->update([
            'status' => ModelStatus::Draft->value,
        ]);

        $this->formEvent = array_merge($this->futureEvent->toArray(), [
            'name' => 'Updated Event Name - ' . Str::random(5),
            'body' => 'Updated event body content - ' . Str::random(30),
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(3)->addHours(2),
            'registration_deadline_at' => now()->addDays(2)->addHours(19),
            'published_at' => now(),
            'status' => ModelStatus::Draft->value,
        ]);
    }

    #[Test]
    public function a_event_can_be_update_through_the_form()
    {
        // Odošleme PUT požiadavku na API
        $response = $this->putJson("/api/dashboard/events/{$this->futureEvent->id}", $this->formEvent);

        // Overenie, či bola odpoveď úspešná (200 OK)
        $response->assertStatus(200);

        // dump($response->getContent());

        // Overenie štruktúry pre jednotlivý event (nie pole)
        $response->assertJsonStructure([
            'id',
            'name',
            'body',
            'start_at',
            'end_at',
            'registration_deadline_at',
            'user_id',
            'created_at',
            'updated_at'
        ]);

        // Overíme, či bola odpoveď úspešná (201 Created)
        $response->assertStatus(200);

        // Overenie konkrétnych hodnôt
        $response->assertJsonFragment([
            'name' => $this->formEvent['name'],
            'body' => $this->formEvent['body']
        ]);

        // Overíme, či sa údaje v databáze zmenili
        $this->assertDatabaseHas('events', [
            'id' => $this->futureEvent->id,
            'name' => $this->formEvent['name'],
            'body' => $this->formEvent['body'],
        ]);

        $this->assertNotNull($this->futureEvent->fresh()?->registration_deadline_at);
    }

    #[Test]
    public function a_soft_deleted_event_can_be_update_through_the_form()
    {
        $this->futureEvent->delete();

        $response = $this->putJson("/api/dashboard/events/{$this->futureEvent->id}", $this->formEvent);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->futureEvent->id,
            'name' => $this->formEvent['name'],
            'body' => $this->formEvent['body'],
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $this->futureEvent->id,
            'name' => $this->formEvent['name'],
            'body' => $this->formEvent['body'],
        ]);

        $this->assertSoftDeleted('events', [
            'id' => $this->futureEvent->id,
        ]);
    }

    #[Test]
    public function event_update_aligns_canal_with_selected_venue(): void
    {
        $secondaryCanal = Canal::factory()->create();
        $secondaryVenue = Venue::factory()->create([
            'canal_id' => $secondaryCanal->id,
            'village_id' => (int) $secondaryCanal->municipality_id,
        ]);

        $this->user->canals()->attach($secondaryCanal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Draft->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = array_merge($this->formEvent, [
            'canal_id' => $this->futureEvent->canal_id,
            'venue_id' => $secondaryVenue->id,
        ]);

        $response = $this->putJson("/api/dashboard/events/{$this->futureEvent->id}", $payload);

        $response
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $this->futureEvent->id,
                'venue_id' => $secondaryVenue->id,
                'canal_id' => $secondaryCanal->id,
            ]);

        $this->assertDatabaseHas('events', [
            'id' => $this->futureEvent->id,
            'venue_id' => $secondaryVenue->id,
            'canal_id' => $secondaryCanal->id,
        ]);
    }
}

