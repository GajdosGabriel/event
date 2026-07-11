<?php

namespace Tests\Feature\Events;

use App\Enums\AdmissionStatus;
use App\Enums\TicketTypeKind;
use App\Models\Admission;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\WorkshopSeatGranted;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class WorkshopRegistrationTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app['auth']->forgetGuards();
    }

    private function createTypes(): array
    {
        $main = $this->futureEvent->ticketTypes()->create([
            'name' => 'Vstupenka',
            'price_amount' => 0,
            'is_active' => true,
        ]);

        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Keramika',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'capacity' => 5,
            'is_active' => true,
        ]);

        return [$main, $workshop];
    }

    #[Test]
    public function workshop_only_order_without_registration_is_rejected(): void
    {
        [, $workshop] = $this->createTypes();

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Cudzí',
            'holder_email' => 'cudzi@example.com',
            'items' => [['ticket_type_id' => $workshop->id, 'quantity' => 1]],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tickets', ['holder_email' => 'cudzi@example.com']);
    }

    #[Test]
    public function standalone_workshop_without_main_type_can_be_ordered_directly(): void
    {
        // Podujatie bez hlavného typu vstupenky — workshop je samostatná
        // registrácia a hosť sa naň má vedieť prihlásiť priamo.
        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Seminár',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'capacity' => 5,
            'is_active' => true,
        ]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Samostatný',
            'holder_email' => 'sam@example.com',
            'items' => [['ticket_type_id' => $workshop->id, 'quantity' => 1]],
        ])->assertStatus(201)->assertJsonPath('admissions_total', 1);

        $this->assertDatabaseHas('tickets', ['holder_email' => 'sam@example.com']);
    }

    #[Test]
    public function open_workshop_can_be_ordered_without_main_ticket(): void
    {
        // Podujatie MÁ hlavný typ, ale workshop je označený ako otvorený
        // (open_to_public) → hosť ho vie objednať aj bez hlavnej vstupenky.
        $this->createTypes(); // vytvorí aj hlavný typ „Vstupenka"
        $open = $this->futureEvent->ticketTypes()->create([
            'name' => 'Otvorený seminár',
            'kind' => TicketTypeKind::Workshop->value,
            'open_to_public' => true,
            'price_amount' => 0,
            'capacity' => 5,
            'is_active' => true,
        ]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Nezaregistrovaný',
            'holder_email' => 'open@example.com',
            'items' => [['ticket_type_id' => $open->id, 'quantity' => 1]],
        ])->assertStatus(201)->assertJsonPath('admissions_total', 1);

        $this->assertDatabaseHas('tickets', ['holder_email' => 'open@example.com']);
    }

    #[Test]
    public function non_open_workshop_still_requires_main_ticket(): void
    {
        // Kontrolný test: bežný (nie otvorený) workshop naďalej vyžaduje registráciu.
        [, $workshop] = $this->createTypes();

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Cudzí',
            'holder_email' => 'cudzi2@example.com',
            'items' => [['ticket_type_id' => $workshop->id, 'quantity' => 1]],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tickets', ['holder_email' => 'cudzi2@example.com']);
    }

    #[Test]
    public function workshop_can_be_ordered_together_with_main_ticket(): void
    {
        [$main, $workshop] = $this->createTypes();

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Spolu',
            'holder_email' => 'spolu@example.com',
            'items' => [
                ['ticket_type_id' => $main->id, 'quantity' => 1],
                ['ticket_type_id' => $workshop->id, 'quantity' => 1],
            ],
        ])->assertStatus(201)->assertJsonPath('admissions_total', 2);
    }

    #[Test]
    public function registered_email_can_add_workshop_later(): void
    {
        [$main, $workshop] = $this->createTypes();

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Dodatočne',
            'holder_email' => 'neskor@example.com',
            'items' => [['ticket_type_id' => $main->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Dodatočne',
            'holder_email' => 'NESKOR@example.com', // case-insensitive zhoda
            'items' => [['ticket_type_id' => $workshop->id, 'quantity' => 1]],
        ])->assertStatus(201)->assertJsonPath('admissions_total', 1);
    }

    #[Test]
    public function workshop_seats_are_limited_by_main_ticket_count(): void
    {
        [$main, $workshop] = $this->createTypes();

        // 1 vstupenka, ale 2 miesta na tom istom workshope → 422.
        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Chamtivý',
            'holder_email' => 'chamtivy@example.com',
            'items' => [
                ['ticket_type_id' => $main->id, 'quantity' => 1],
                ['ticket_type_id' => $workshop->id, 'quantity' => 2],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tickets', ['holder_email' => 'chamtivy@example.com']);
    }

    #[Test]
    public function one_ticket_allows_multiple_different_workshops(): void
    {
        [$main, $workshopA] = $this->createTypes();
        $workshopB = $this->futureEvent->ticketTypes()->create([
            'name' => 'Maľovanie',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'is_active' => true,
        ]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Aktívny',
            'holder_email' => 'aktivny@example.com',
            'items' => [
                ['ticket_type_id' => $main->id, 'quantity' => 1],
                ['ticket_type_id' => $workshopA->id, 'quantity' => 1],
                ['ticket_type_id' => $workshopB->id, 'quantity' => 1],
            ],
        ])->assertStatus(201)->assertJsonPath('admissions_total', 3);
    }

    #[Test]
    public function public_ticket_types_report_viewer_registration(): void
    {
        [$main] = $this->createTypes();

        // Hosť: viewer_registered = false.
        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('meta.viewer_registered', false);

        // Prihlásený s vstupenkou: true.
        $this->actingAs($this->user, 'sanctum');

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'items' => [['ticket_type_id' => $main->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('meta.viewer_registered', true);
    }

    #[Test]
    public function logged_in_attendee_can_join_and_leave_a_workshop(): void
    {
        [$main, $workshop] = $this->createTypes();
        $this->actingAs($this->user, 'sanctum');

        // Bez vstupenky na podujatie sa na workshop prihlásiť nedá.
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")
            ->assertStatus(422);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'items' => [['ticket_type_id' => $main->id, 'quantity' => 1]],
        ])->assertStatus(201);

        // Prihlásenie na workshop jedným klikom.
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")
            ->assertStatus(201)
            ->assertJsonPath('ticket_type.name', 'Keramika');

        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('meta.workshop_changes_locked', false)
            ->assertJsonPath('data.1.viewer_joined', true)
            // Hlavná vstupenka nie je workshop — viewer_joined tam musí ostať false.
            ->assertJsonPath('data.0.viewer_joined', false);

        // Druhé prihlásenie na ten istý workshop je odmietnuté.
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")
            ->assertStatus(422);

        // Odhlásenie.
        $this->deleteJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")
            ->assertOk();

        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('data.1.viewer_joined', false);

        // Odhlásenie bez prihlásenia je odmietnuté.
        $this->deleteJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")
            ->assertStatus(422);
    }

    #[Test]
    public function leaving_a_workshop_frees_its_capacity_but_keeps_the_main_ticket(): void
    {
        [$main, $workshop] = $this->createTypes();
        $this->actingAs($this->user, 'sanctum');

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'items' => [['ticket_type_id' => $main->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);
        $this->assertSame(4, $workshop->fresh()->remaining_capacity);

        $this->deleteJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertOk();

        // Miesto na workshope sa uvoľnilo…
        $this->assertSame(5, $workshop->fresh()->remaining_capacity);
        // …ale hlavná vstupenka na podujatie ostáva platná.
        $this->assertSame(1, $main->fresh()->sold_count);
    }

    #[Test]
    public function workshop_changes_are_locked_once_the_event_starts(): void
    {
        [$main, $workshop] = $this->createTypes();
        $this->actingAs($this->user, 'sanctum');

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'items' => [['ticket_type_id' => $main->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        // Podujatie práve začalo (stále prebieha).
        $this->futureEvent->update([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'workshop_lock_on_start' => true,
        ]);

        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('meta.workshop_changes_locked', true);

        $this->deleteJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(422);

        // Organizátor zámok vypne → zmeny sú znova možné.
        $this->futureEvent->update(['workshop_lock_on_start' => false]);

        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('meta.workshop_changes_locked', false);

        $this->deleteJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertOk();
    }

    #[Test]
    public function guest_cannot_join_a_workshop(): void
    {
        [, $workshop] = $this->createTypes();

        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")
            ->assertStatus(401);
    }

    /** Vytvorí ďalšieho účastníka s platnou vstupenkou na podujatie. */
    private function attendeeWithTicket(TicketType $main, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);

        $this->actingAs($user, 'sanctum');
        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'items' => [['ticket_type_id' => $main->id, 'quantity' => 1]],
        ])->assertStatus(201);

        return $user;
    }

    #[Test]
    public function full_workshop_puts_the_attendee_on_the_waitlist(): void
    {
        $main = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);
        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Keramika',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'capacity' => 1,
            'is_active' => true,
        ]);

        // Prvý zaberie jediné miesto.
        $first = $this->attendeeWithTicket($main, 'prvy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")
            ->assertStatus(201)
            ->assertJsonPath('status', 'valid');

        // Druhý ide na čakačku.
        $second = $this->attendeeWithTicket($main, 'druhy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")
            ->assertStatus(201)
            ->assertJsonPath('status', 'waitlisted');

        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('data.1.viewer_waitlisted', true)
            ->assertJsonPath('data.1.viewer_joined', false)
            ->assertJsonPath('data.1.viewer_waitlist_position', 1)
            ->assertJsonPath('data.1.waitlist_count', 1);

        // Náhradník nezaberá kapacitu workshopu ani podujatia.
        $this->assertSame(1, $workshop->fresh()->sold_count);
        $this->assertSame(0, $workshop->fresh()->remaining_capacity);
    }

    #[Test]
    public function leaving_promotes_the_first_person_from_the_waitlist(): void
    {
        Notification::fake();

        $main = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);
        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Keramika',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'capacity' => 1,
            'is_active' => true,
        ]);

        $first = $this->attendeeWithTicket($main, 'prvy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        // Dvaja náhradníci — poradie podľa času prihlásenia.
        $second = $this->attendeeWithTicket($main, 'druhy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        $third = $this->attendeeWithTicket($main, 'treti@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        // Prvý sa odhlási → miesto dostane druhý, nie tretí.
        $this->actingAs($first, 'sanctum');
        $this->deleteJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertOk();

        $this->actingAs($second, 'sanctum');
        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('data.1.viewer_joined', true)
            ->assertJsonPath('data.1.viewer_waitlisted', false);

        $this->actingAs($third, 'sanctum');
        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('data.1.viewer_waitlisted', true)
            ->assertJsonPath('data.1.viewer_waitlist_position', 1);

        // Workshop je stále plný — miesto len zmenilo majiteľa.
        $this->assertSame(1, $workshop->fresh()->sold_count);

        Notification::assertSentOnDemand(WorkshopSeatGranted::class);
    }

    #[Test]
    public function leaving_the_waitlist_does_not_promote_anyone(): void
    {
        $main = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);
        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Keramika',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'capacity' => 1,
            'is_active' => true,
        ]);

        $this->attendeeWithTicket($main, 'prvy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        $second = $this->attendeeWithTicket($main, 'druhy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        $third = $this->attendeeWithTicket($main, 'treti@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        // Druhý opustí čakačku — miesto sa neuvoľnilo, tretí sa neposúva na miesto,
        // ale posunie sa v poradí čakačky.
        $this->actingAs($second, 'sanctum');
        $this->deleteJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertOk();

        $this->actingAs($third, 'sanctum');
        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('data.1.viewer_joined', false)
            ->assertJsonPath('data.1.viewer_waitlisted', true)
            ->assertJsonPath('data.1.viewer_waitlist_position', 1);

        $this->assertSame(1, $workshop->fresh()->sold_count);
    }

    #[Test]
    public function organizer_cancelling_an_admission_promotes_the_waitlist(): void
    {
        $main = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);
        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Keramika',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'capacity' => 1,
            'is_active' => true,
        ]);

        $first = $this->attendeeWithTicket($main, 'prvy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        $second = $this->attendeeWithTicket($main, 'druhy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        $seat = Admission::query()
            ->where('ticket_type_id', $workshop->id)
            ->where('status', AdmissionStatus::Valid->value)
            ->firstOrFail();

        // Organizátor zruší prvému lístok z dashboardu.
        $this->user->givePermissionTo(['ticket.view', 'ticket.update']);
        $this->actingAs($this->user, 'sanctum');
        $this->postJson("/api/dashboard/admissions/{$seat->id}/cancel")->assertOk();

        // Náhradník miesto dostal.
        $this->actingAs($second, 'sanctum');
        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('data.1.viewer_joined', true);
    }

    #[Test]
    public function attendee_can_cancel_their_own_registration(): void
    {
        [$main] = $this->createTypes();
        $this->actingAs($this->user, 'sanctum');

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'items' => [['ticket_type_id' => $main->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertJsonPath('meta.viewer_registered', true);

        // Samoobslužné zrušenie registrácie.
        $this->deleteJson("/api/events/{$this->futureEvent->id}/registration")->assertOk();

        // Miesto sa uvoľnilo a už nie je registrovaný.
        $this->assertSame(0, $main->fresh()->sold_count);
        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertJsonPath('meta.viewer_registered', false);
    }

    #[Test]
    public function cancelling_registration_without_one_is_rejected(): void
    {
        $this->createTypes();
        $this->actingAs($this->user, 'sanctum');

        $this->deleteJson("/api/events/{$this->futureEvent->id}/registration")->assertStatus(422);
    }

    #[Test]
    public function guest_cannot_cancel_a_registration(): void
    {
        $this->createTypes();

        $this->deleteJson("/api/events/{$this->futureEvent->id}/registration")->assertStatus(401);
    }

    #[Test]
    public function cancelling_registration_frees_workshops_and_promotes_the_waitlist(): void
    {
        Notification::fake();

        $main = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);
        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Keramika',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'capacity' => 1,
            'is_active' => true,
        ]);

        // Prvý má hlavnú vstupenku aj miesto na workshope.
        $first = $this->attendeeWithTicket($main, 'prvy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        // Druhý je na workshope náhradníkom.
        $second = $this->attendeeWithTicket($main, 'druhy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")
            ->assertJsonPath('status', 'waitlisted');

        // Prvý zruší celú registráciu na podujatie → padne aj jeho workshop,
        // miesto dostane druhý (FIFO).
        $this->actingAs($first, 'sanctum');
        $this->deleteJson("/api/events/{$this->futureEvent->id}/registration")->assertOk();

        $this->actingAs($second, 'sanctum');
        $this->getJson("/api/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('data.1.viewer_joined', true)
            ->assertJsonPath('data.1.viewer_waitlisted', false);

        Notification::assertSentOnDemand(WorkshopSeatGranted::class);
    }

    #[Test]
    public function waitlisted_admission_cannot_be_checked_in(): void
    {
        $main = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);
        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Keramika',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'capacity' => 1,
            'is_active' => true,
        ]);

        $this->attendeeWithTicket($main, 'prvy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        $this->attendeeWithTicket($main, 'druhy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        $waitlisted = Admission::query()
            ->where('ticket_type_id', $workshop->id)
            ->where('status', AdmissionStatus::Waitlisted->value)
            ->firstOrFail();

        $this->user->givePermissionTo(['ticket.view', 'ticket.checkin']);
        $this->actingAs($this->user, 'sanctum');

        $this->postJson('/api/dashboard/tickets/checkin', ['qr_token' => $waitlisted->qr_token])
            ->assertOk()
            ->assertJsonPath('status', 'invalid')
            ->assertJsonPath('reason', 'waitlisted');

        // Ani QR obrázok mu nevydáme.
        $this->getJson("/api/admissions/{$waitlisted->uuid}/qr")->assertStatus(404);
    }

    #[Test]
    public function waitlist_is_not_promoted_after_the_event_starts(): void
    {
        $main = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);
        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Keramika',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'capacity' => 1,
            'is_active' => true,
        ]);

        $first = $this->attendeeWithTicket($main, 'prvy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        $second = $this->attendeeWithTicket($main, 'druhy@example.com');
        $this->postJson("/api/events/{$this->futureEvent->id}/workshops/{$workshop->id}")->assertStatus(201);

        $seat = Admission::query()
            ->where('ticket_type_id', $workshop->id)
            ->where('status', AdmissionStatus::Valid->value)
            ->firstOrFail();

        $this->futureEvent->update([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'workshop_lock_on_start' => true,
        ]);

        // Organizátor zruší miesto po začiatku → čakačka sa neposúva.
        $this->user->givePermissionTo(['ticket.view', 'ticket.update']);
        $this->actingAs($this->user, 'sanctum');
        $this->postJson("/api/dashboard/admissions/{$seat->id}/cancel")->assertOk();

        $this->assertSame(
            AdmissionStatus::Waitlisted,
            Admission::query()->where('ticket_type_id', $workshop->id)
                ->where('status', AdmissionStatus::Waitlisted->value)->firstOrFail()->status,
        );
        $this->assertSame(0, $workshop->fresh()->sold_count);
    }

    #[Test]
    public function workshop_admissions_do_not_consume_main_type_capacity(): void
    {
        // Hlavný typ má vlastnú kapacitu 2, workshop svoju vlastnú (5).
        $main = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'capacity' => 2, 'is_active' => true]);
        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Keramika',
            'kind' => TicketTypeKind::Workshop->value,
            'price_amount' => 0,
            'capacity' => 5,
            'is_active' => true,
        ]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Kapacita',
            'holder_email' => 'kapacita@example.com',
            'items' => [
                ['ticket_type_id' => $main->id, 'quantity' => 2],
                ['ticket_type_id' => $workshop->id, 'quantity' => 2],
            ],
        ])->assertStatus(201);

        // Hlavný typ je plný (2/2), ale workshop miesta jeho kapacitu nezožrali.
        $this->assertSame(0, $main->fresh()->remaining_capacity);
        $this->assertSame(3, $workshop->fresh()->remaining_capacity);

        $order = Ticket::query()->where('holder_email', 'kapacita@example.com')->firstOrFail();
        $this->assertSame(4, $order->admissions()->count());
    }
}
