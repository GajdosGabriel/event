<?php

namespace Tests\Feature\Tickets;

use App\Enums\AdmissionStatus;
use App\Enums\AttendeeConfirmationStatus;
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

    /**
     * Vstupenku objednal niekto iný a účastníka uviedol e-mailom. Účastník ju
     * vidí, ale len svoje miesto — `uuid` objednávky by mu cez /tickets/{uuid}
     * otvorilo QR kódy objednávateľa.
     */
    #[Test]
    public function attendee_sees_only_own_seat_of_an_order_made_by_someone_else(): void
    {
        $ticket = $this->ticketFor($this->futureEvent, ['user_id' => null, 'quantity' => 2, 'price_amount' => 2000]);
        $holderSeat = $ticket->admissions()->create(['event_id' => $this->futureEvent->id, 'status' => AdmissionStatus::Valid->value]);
        $ownSeat = $ticket->admissions()->create([
            'event_id' => $this->futureEvent->id,
            'status' => AdmissionStatus::Valid->value,
            'attendee_name' => 'Peter Pozvaný',
            'attendee_email' => mb_strtoupper($this->user->email),
            'confirmation_status' => AttendeeConfirmationStatus::Pending->value,
            'confirmation_token' => 'rsvp-token',
        ]);

        $response = $this->getJson('/api/me/tickets')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attendee_only', true)
            ->assertJsonPath('data.0.uuid', $ownSeat->uuid)
            ->assertJsonPath('data.0.rsvp_token', 'rsvp-token')
            ->assertJsonPath('data.0.price_amount', null)
            ->assertJsonCount(1, 'data.0.admissions')
            ->assertJsonPath('data.0.admissions.0.uuid', $ownSeat->uuid);

        $this->assertStringNotContainsString($ticket->uuid, $response->getContent());
        $this->assertStringNotContainsString($holderSeat->uuid, $response->getContent());
    }

    #[Test]
    public function declined_seat_of_a_foreign_order_moves_to_history(): void
    {
        $ticket = $this->ticketFor($this->futureEvent, ['user_id' => null]);
        $ticket->admissions()->create([
            'event_id' => $this->futureEvent->id,
            'status' => AdmissionStatus::Cancelled->value,
            'attendee_email' => $this->user->email,
        ]);

        $this->getJson('/api/me/tickets')->assertJsonCount(0, 'data');
        $this->getJson('/api/me/tickets?list=past')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attendee_only', true);
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

        $this->deleteJson('/api/me/subscriptions/'.$subscription->id)->assertOk();

        // Odhlásenie zahodí adresu a riadok nechá — druhý pokus už nič nenájde.
        $this->assertNull($subscription->fresh()->email);
        $this->deleteJson('/api/me/subscriptions/'.$subscription->id)->assertStatus(404);
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

        $this->deleteJson('/api/me/subscriptions/'.$subscription->id)->assertStatus(404);

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
