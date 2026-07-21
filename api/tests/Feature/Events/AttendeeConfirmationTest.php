<?php

namespace Tests\Feature\Events;

use App\Enums\AdmissionStatus;
use App\Enums\AttendeeConfirmationStatus;
use App\Models\Admission;
use App\Models\TicketType;
use App\Notifications\AttendeeConfirmationRequest;
use App\Notifications\AttendeeConfirmed;
use App\Notifications\AttendeeDeclined;
use App\Notifications\AttendeeTicketIssued;
use App\Notifications\TicketIssued;
use App\Services\Tickets\AttendeeConfirmation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class AttendeeConfirmationTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app['auth']->forgetGuards();
    }

    private function mainType(): TicketType
    {
        return $this->futureEvent->ticketTypes()->create([
            'name' => 'Vstupenka',
            'price_amount' => 0,
            'is_active' => true,
        ]);
    }

    /** Gabriel objedná 2 miesta — jedno pre seba, jedno pre kamaráta. */
    private function orderForFriend(TicketType $main, string $friendEmail = 'kamarat@example.com'): Admission
    {
        $this->actingAs($this->user, 'sanctum');

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'items' => [[
                'ticket_type_id' => $main->id,
                'quantity' => 2,
                'attendees' => [
                    ['name' => null, 'email' => null],
                    ['name' => 'Kamarát', 'email' => $friendEmail],
                ],
            ]],
        ])->assertStatus(201);

        return Admission::query()->where('attendee_email', $friendEmail)->firstOrFail();
    }

    #[Test]
    public function extra_attendee_gets_a_confirmation_request_not_a_ticket(): void
    {
        Notification::fake();
        $main = $this->mainType();

        $pending = $this->orderForFriend($main);

        $this->assertSame(AttendeeConfirmationStatus::Pending, $pending->confirmation_status);
        $this->assertNotNull($pending->confirmation_token);
        $this->assertNotNull($pending->confirmation_deadline_at);
        // Miesto je držané (počíta sa do kapacity), no vstupenka je stále „valid".
        $this->assertSame(AdmissionStatus::Valid, $pending->status);

        // Objednávateľ dostal svoj lístok, kamarát žiadosť o potvrdenie — nie lístok.
        Notification::assertSentOnDemand(TicketIssued::class);
        Notification::assertSentOnDemand(AttendeeConfirmationRequest::class);
        Notification::assertSentOnDemandTimes(AttendeeTicketIssued::class, 0);
    }

    #[Test]
    public function confirming_issues_the_ticket_and_notifies_the_orderer(): void
    {
        $main = $this->mainType();
        $pending = $this->orderForFriend($main);

        Notification::fake();

        $this->postJson("/api/rsvp/{$pending->confirmation_token}/confirm")
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $pending->refresh();
        $this->assertSame(AttendeeConfirmationStatus::Confirmed, $pending->confirmation_status);
        $this->assertNotNull($pending->confirmed_at);

        Notification::assertSentOnDemand(AttendeeTicketIssued::class);
        Notification::assertSentOnDemand(AttendeeConfirmed::class);
    }

    #[Test]
    public function declining_frees_the_seat_and_notifies_the_orderer(): void
    {
        $main = $this->mainType();
        $pending = $this->orderForFriend($main);

        $this->assertSame(2, $main->fresh()->sold_count);

        Notification::fake();

        $this->postJson("/api/rsvp/{$pending->confirmation_token}/decline")
            ->assertOk()
            ->assertJsonPath('status', 'declined');

        $pending->refresh();
        $this->assertSame(AttendeeConfirmationStatus::Declined, $pending->confirmation_status);
        $this->assertSame(AdmissionStatus::Cancelled, $pending->status);

        // Miesto sa uvoľnilo (ostala len Gabrielova vstupenka).
        $this->assertSame(1, $main->fresh()->sold_count);

        Notification::assertSentOnDemand(AttendeeDeclined::class);
        Notification::assertSentOnDemandTimes(AttendeeConfirmed::class, 0);
    }

    #[Test]
    public function a_confirmed_free_ticket_can_still_be_cancelled(): void
    {
        $main = $this->mainType();
        $pending = $this->orderForFriend($main);

        $this->postJson("/api/rsvp/{$pending->confirmation_token}/confirm")->assertOk();

        // Práve tento príznak zapína odkaz „Zrušiť vstupenku" v e-maile aj na RSVP stránke.
        $this->getJson("/api/rsvp/{$pending->confirmation_token}")
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('can_cancel', true);

        Notification::fake();

        $this->postJson("/api/rsvp/{$pending->confirmation_token}/decline")
            ->assertOk()
            ->assertJsonPath('status', 'declined')
            ->assertJsonPath('can_cancel', false);

        $pending->refresh();
        $this->assertSame(AdmissionStatus::Cancelled, $pending->status);
        $this->assertSame(1, $main->fresh()->sold_count);

        Notification::assertSentOnDemand(AttendeeDeclined::class);
    }

    #[Test]
    public function a_confirmed_paid_ticket_cannot_be_cancelled_by_the_attendee(): void
    {
        $main = $this->mainType();
        $pending = $this->orderForFriend($main);

        $this->postJson("/api/rsvp/{$pending->confirmation_token}/confirm")->assertOk();

        // Platená objednávka — zrušenie by znamenalo vrátenie peňazí, to rieši organizátor.
        $pending->ticket->update(['price_amount' => 1000]);

        $this->postJson("/api/rsvp/{$pending->confirmation_token}/decline")
            ->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('can_cancel', false);

        $this->assertSame(AdmissionStatus::Valid, $pending->fresh()->status);
    }

    #[Test]
    public function unconfirmed_reservations_expire_after_the_deadline(): void
    {
        $main = $this->mainType();
        $pending = $this->orderForFriend($main);

        // Posunieme lehotu do minulosti.
        $pending->update(['confirmation_deadline_at' => now()->subMinute()]);

        Notification::fake();

        $released = app(AttendeeConfirmation::class)->expirePending();

        $this->assertSame(1, $released);
        $pending->refresh();
        $this->assertSame(AttendeeConfirmationStatus::Expired, $pending->confirmation_status);
        $this->assertSame(AdmissionStatus::Cancelled, $pending->status);
        $this->assertSame(1, $main->fresh()->sold_count);

        Notification::assertSentOnDemand(AttendeeDeclined::class);
    }

    #[Test]
    public function a_confirmed_reservation_does_not_expire(): void
    {
        $main = $this->mainType();
        $pending = $this->orderForFriend($main);

        app(AttendeeConfirmation::class)->confirm(
            app(AttendeeConfirmation::class)->groupForToken($pending->confirmation_token),
        );

        $pending->update(['confirmation_deadline_at' => now()->subMinute()]);

        $released = app(AttendeeConfirmation::class)->expirePending();

        $this->assertSame(0, $released);
        $this->assertSame(AttendeeConfirmationStatus::Confirmed, $pending->fresh()->confirmation_status);
    }

    #[Test]
    public function a_pending_admission_cannot_be_checked_in_or_expose_a_qr(): void
    {
        $main = $this->mainType();
        $pending = $this->orderForFriend($main);

        // QR kód nepotvrdenej vstupenky nevydáme.
        $this->getJson("/api/admissions/{$pending->uuid}/qr")->assertStatus(404);

        // Ani pri vchode neprejde.
        $this->user->givePermissionTo(['ticket.view', 'ticket.checkin']);
        $this->actingAs($this->user, 'sanctum');
        $this->postJson('/api/dashboard/tickets/checkin', ['qr_token' => $pending->qr_token])
            ->assertOk()
            ->assertJsonPath('status', 'invalid')
            ->assertJsonPath('reason', 'unconfirmed');
    }

    #[Test]
    public function the_rsvp_endpoint_returns_the_reservation_summary(): void
    {
        $main = $this->mainType();
        $pending = $this->orderForFriend($main);

        $this->getJson("/api/rsvp/{$pending->confirmation_token}")
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('attendee_name', 'Kamarát')
            ->assertJsonPath('holder_name', strtok($this->user->email, '@')) // one-click: meno z e-mailu účtu
            ->assertJsonCount(1, 'seats');
    }

    #[Test]
    public function an_invalid_token_returns_404(): void
    {
        $this->getJson('/api/rsvp/nonsense-token')->assertStatus(404);
    }

    /** Bez Notification::fake() — overí, že všetky e-mailové šablóny sa vykreslia bez chyby. */
    #[Test]
    public function every_confirmation_email_renders(): void
    {
        $main = $this->mainType();
        $this->actingAs($this->user, 'sanctum');

        // Objednávka pre 2 kamarátov — vykreslí TicketIssued (objednávateľ)
        // aj AttendeeConfirmationRequest (obom kamarátom).
        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'items' => [[
                'ticket_type_id' => $main->id,
                'quantity' => 3,
                'attendees' => [
                    ['name' => null, 'email' => null],
                    ['name' => 'Prvý', 'email' => 'prvy@example.com'],
                    ['name' => 'Druhý', 'email' => 'druhy@example.com'],
                ],
            ]],
        ])->assertStatus(201);

        $svc = app(AttendeeConfirmation::class);
        $a1 = Admission::query()->where('attendee_email', 'prvy@example.com')->firstOrFail();
        $a2 = Admission::query()->where('attendee_email', 'druhy@example.com')->firstOrFail();

        // Potvrdenie vykreslí AttendeeTicketIssued + AttendeeConfirmed.
        $svc->confirm($svc->groupForToken($a1->confirmation_token));
        // Odmietnutie vykreslí AttendeeDeclined.
        $svc->decline($svc->groupForToken($a2->confirmation_token));

        $this->assertSame(AttendeeConfirmationStatus::Confirmed, $a1->fresh()->confirmation_status);
        $this->assertSame(AttendeeConfirmationStatus::Declined, $a2->fresh()->confirmation_status);
    }
}
