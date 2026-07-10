<?php

namespace Tests\Feature\Events;

use App\Models\PendingProfile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class PublicTicketReservationTest extends EventSetupTest
{
    #[Test]
    public function guest_can_reserve_multiple_seats(): void
    {
        $this->app['auth']->forgetGuards(); // ako neprihlásený hosť

        $this->futureEvent->update(['price_amount' => null]);
        $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);

        $response = $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Janko Hosť',
            'holder_email' => 'janko@example.com',
            'quantity' => 3,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('holder_name', 'Janko Hosť')
            ->assertJsonPath('quantity', 3);

        $this->assertDatabaseHas('tickets', [
            'event_id' => $this->futureEvent->id,
            'holder_email' => 'janko@example.com',
            'quantity' => 3,
            'user_id' => null,
        ]);
    }

    #[Test]
    public function guest_must_provide_name_and_email(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", ['quantity' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['holder_name', 'holder_email']);
    }

    #[Test]
    public function logged_in_user_reserves_with_account_details(): void
    {
        // $this->user je prihlásený cez sanctum (EventSetupTest).
        PendingProfile::create([
            'user_id' => $this->user->id,
            'display_name' => 'Gabriel Testovací',
        ]);

        $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);

        // One-click: pošleme len množstvo, meno a e-mail doplní backend z účtu.
        $response = $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'quantity' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('holder_name', 'Gabriel Testovací')
            ->assertJsonPath('quantity', 2);

        $this->assertDatabaseHas('tickets', [
            'event_id' => $this->futureEvent->id,
            'user_id' => $this->user->id,
            'holder_email' => $this->user->email,
            'holder_name' => 'Gabriel Testovací',
            'quantity' => 2,
        ]);
    }

    #[Test]
    public function reservation_rejected_when_quantity_exceeds_type_capacity(): void
    {
        // Kapacita je per typ lístka (event-level kapacita už neexistuje).
        $this->app['auth']->forgetGuards();

        $type = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'capacity' => 2, 'is_active' => true]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Skupina',
            'holder_email' => 'skupina@example.com',
            'items' => [['ticket_type_id' => $type->id, 'quantity' => 5]],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tickets', ['holder_email' => 'skupina@example.com']);
    }

    #[Test]
    public function reservation_rejected_after_event_ended(): void
    {
        $this->app['auth']->forgetGuards();

        $this->pastEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);

        $this->postJson("/api/events/{$this->pastEvent->id}/tickets", [
            'holder_name' => 'Neskorý',
            'holder_email' => 'neskoro@example.com',
            'quantity' => 1,
        ])->assertStatus(422);
    }

    #[Test]
    public function reservation_rejected_after_registration_deadline(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['registration_deadline_at' => now()->subDay()]);
        $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Po termíne',
            'holder_email' => 'potermine@example.com',
            'quantity' => 1,
        ])->assertStatus(422);
    }

    #[Test]
    public function reservation_rejected_when_no_active_ticket_type(): void
    {
        // Registrácia je dostupná len ak má podujatie aspoň jeden aktívny typ
        // lístka. Podujatie bez typov (ani neaktívne) žiadosť odmietne.
        $this->app['auth']->forgetGuards();

        $this->futureEvent->ticketTypes()->create(['name' => 'Skrytý', 'price_amount' => 0, 'is_active' => false]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Nepovolené',
            'holder_email' => 'nepovolene@example.com',
            'quantity' => 1,
        ])->assertStatus(422);
    }
}
