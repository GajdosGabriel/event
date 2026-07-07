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

        $this->futureEvent->update([
            'tickets_enabled' => true,
            'capacity' => 10,
            'price_amount' => null,
        ]);

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

        // Kapacita klesla o počet miest, nie o počet lístkov.
        $this->assertSame(7, $this->futureEvent->fresh()->remaining_capacity);
    }

    #[Test]
    public function guest_must_provide_name_and_email(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['tickets_enabled' => true]);

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

        $this->futureEvent->update([
            'tickets_enabled' => true,
            'capacity' => 20,
        ]);

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
    public function reservation_rejected_when_quantity_exceeds_capacity(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'tickets_enabled' => true,
            'capacity' => 2,
        ]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Skupina',
            'holder_email' => 'skupina@example.com',
            'quantity' => 5,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tickets', ['holder_email' => 'skupina@example.com']);
    }

    #[Test]
    public function reservation_rejected_after_event_ended(): void
    {
        $this->app['auth']->forgetGuards();

        $this->pastEvent->update(['tickets_enabled' => true]);

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

        $this->futureEvent->update([
            'tickets_enabled' => true,
            'registration_deadline_at' => now()->subDay(),
        ]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Po termíne',
            'holder_email' => 'potermine@example.com',
            'quantity' => 1,
        ])->assertStatus(422);
    }

    #[Test]
    public function reservation_rejected_when_tickets_disabled(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['tickets_enabled' => false]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Nepovolené',
            'holder_email' => 'nepovolene@example.com',
            'quantity' => 1,
        ])->assertStatus(422);
    }
}
