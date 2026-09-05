<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * „Moje lístky" — výpis vstupeniek a odberov prihláseného účtu.
 */
class MyTicketsTest extends EventSetupTest
{
    #[Test]
    public function upcoming_list_shows_ticket_bought_with_the_account(): void
    {
        $ticket = $this->ticketFor($this->futureEvent, ['user_id' => $this->user->id]);

        $response = $this->getJson('/api/me/tickets');

        $response->assertOk();
        $response->assertJsonPath('data.0.uuid', $ticket->uuid);
    }

    /**
     * Objednať sa dá bez účtu — vtedy má lístok len `holder_email`. Kto si
     * účet založil až potom, musí svoje staré lístky nájsť tiež, inak z „Mojich
     * lístkov" zmizne presne to, kvôli čomu na stránku prišiel.
     */
    #[Test]
    public function upcoming_list_shows_guest_ticket_matched_by_email(): void
    {
        $ticket = $this->ticketFor($this->futureEvent, [
            'user_id' => null,
            // Iná veľkosť písmen než na účte: adresa prišla z formulára.
            'holder_email' => mb_strtoupper($this->user->email),
        ]);

        $response = $this->getJson('/api/me/tickets');

        $response->assertOk();
        $response->assertJsonPath('data.0.uuid', $ticket->uuid);
    }

    #[Test]
    public function list_does_not_leak_tickets_of_other_people(): void
    {
        $stranger = User::factory()->create();

        $this->ticketFor($this->futureEvent, [
            'user_id' => $stranger->id,
            'holder_email' => $stranger->email,
        ]);

        $response = $this->getJson('/api/me/tickets');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    /** Zrušená objednávka nepatrí medzi nadchádzajúce, ale do histórie. */
    #[Test]
    public function cancelled_ticket_moves_from_upcoming_to_history(): void
    {
        $ticket = $this->ticketFor($this->futureEvent, [
            'user_id' => $this->user->id,
            'status' => TicketStatus::Cancelled->value,
        ]);

        $this->getJson('/api/me/tickets')->assertJsonCount(0, 'data');

        $this->getJson('/api/me/tickets?list=past')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $ticket->uuid);
    }

    #[Test]
    public function past_event_is_in_history_only(): void
    {
        $ticket = $this->ticketFor($this->pastEvent, ['user_id' => $this->user->id]);

        $this->getJson('/api/me/tickets')->assertJsonCount(0, 'data');
        $this->getJson('/api/me/tickets?list=past')->assertJsonPath('data.0.uuid', $ticket->uuid);
    }

    #[Test]
    public function list_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/me/tickets')->assertStatus(401);
    }

    #[Test]
    public function subscriptions_are_matched_by_email_and_can_be_cancelled(): void
    {
        $subscription = Subscription::create([
            'subscribable_type' => \App\Models\Event::class,
            'subscribable_id' => $this->futureEvent->id,
            'email' => mb_strtoupper($this->user->email),
            'token' => Subscription::freshToken(),
        ]);

        $this->getJson('/api/me/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $subscription->id)
            ->assertJsonPath('data.0.type', 'event');

        $this->deleteJson('/api/me/subscriptions/' . $subscription->id)->assertOk();

        // Odhlásenie zahodí adresu a riadok nechá — druhý pokus už nič nenájde.
        $this->assertNull($subscription->fresh()->email);
        $this->deleteJson('/api/me/subscriptions/' . $subscription->id)->assertStatus(404);
    }

    #[Test]
    public function subscription_of_another_person_cannot_be_cancelled(): void
    {
        $stranger = User::factory()->create();

        $subscription = Subscription::create([
            'subscribable_type' => \App\Models\Event::class,
            'subscribable_id' => $this->futureEvent->id,
            'email' => $stranger->email,
            'token' => Subscription::freshToken(),
        ]);

        $this->deleteJson('/api/me/subscriptions/' . $subscription->id)->assertStatus(404);

        $this->assertNotNull($subscription->fresh()->email);
    }

    private function ticketFor($event, array $attributes = []): Ticket
    {
        return Ticket::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'holder_name' => 'Janko Hosť',
            'holder_email' => 'janko@example.test',
            'status' => TicketStatus::Confirmed->value,
        ], $attributes));
    }
}
