<?php

namespace Tests\Feature\Events;

use App\Enums\AdmissionStatus;
use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Notifications\TicketIssued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Akcie nad objednávkou v zozname prihlásených: zrušiť ↔ obnoviť, vymazať,
 * potvrdiť rezerváciu, označiť platbu — a filtre nad tabuľkou.
 */
class DashboardTicketActionsTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Objednávka vytvorená verejným endpointom (rovnako ako v produkcii). */
    private function order(string $email, int $quantity = 2, int $price = 0): Ticket
    {
        $this->app['auth']->forgetGuards();

        $type = $this->futureEvent->ticketTypes()->firstOrCreate(
            ['name' => 'Standard'],
            ['price_amount' => $price, 'is_active' => true],
        );

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Objednávateľ',
            'holder_email' => $email,
            'items' => [['ticket_type_id' => $type->id, 'quantity' => $quantity]],
        ])->assertStatus(201);

        return Ticket::query()->where('holder_email', $email)->firstOrFail();
    }

    private function actingAsOrganizer(): void
    {
        $this->user->givePermissionTo(['ticket.view', 'ticket.update', 'ticket.checkin']);
        $this->actingAs($this->user, 'sanctum');
    }

    #[Test]
    public function a_cancelled_order_can_be_restored_and_the_holder_is_notified(): void
    {
        Notification::fake();

        $order = $this->order('obnova@example.com');
        $this->actingAsOrganizer();

        $this->postJson("/api/dashboard/tickets/{$order->id}")
            ->assertOk()
            ->assertJsonPath('status', TicketStatus::Cancelled->value);

        $this->assertSame(2, $order->admissions()->where('status', AdmissionStatus::Cancelled->value)->count());

        $this->postJson("/api/dashboard/tickets/{$order->id}/restore")
            ->assertOk()
            ->assertJsonPath('status', TicketStatus::Confirmed->value)
            ->assertJsonPath('admissions_total', 2);

        $this->assertSame(2, $order->admissions()->where('status', AdmissionStatus::Valid->value)->count());

        // Obnovenie posiela vstupenky znova; zrušenie nič neposiela.
        Notification::assertSentOnDemand(TicketIssued::class);
    }

    #[Test]
    public function restoring_an_order_leaves_seats_cancelled_individually_before_it(): void
    {
        $order = $this->order('ciastocne@example.com');
        $this->actingAsOrganizer();

        $first = $order->admissions()->orderBy('id')->firstOrFail();

        $this->postJson("/api/dashboard/admissions/{$first->id}/cancel")->assertOk();
        $this->postJson("/api/dashboard/tickets/{$order->id}")->assertOk();
        $this->postJson("/api/dashboard/tickets/{$order->id}/restore")->assertOk();

        $this->assertSame(AdmissionStatus::Cancelled, $first->fresh()->status);
        $this->assertSame(1, $order->admissions()->where('status', AdmissionStatus::Valid->value)->count());
    }

    #[Test]
    public function a_cancelled_seat_can_be_restored_and_reactivates_its_order(): void
    {
        Notification::fake();

        $order = $this->order('miesto@example.com', quantity: 1);
        $this->actingAsOrganizer();
        $admission = $order->admissions()->firstOrFail();

        $this->postJson("/api/dashboard/admissions/{$admission->id}/cancel")->assertOk();
        $this->postJson("/api/dashboard/tickets/{$order->id}")->assertOk();

        $this->postJson("/api/dashboard/admissions/{$admission->id}/restore")
            ->assertOk()
            ->assertJsonPath('status', AdmissionStatus::Valid->value);

        $this->assertSame(TicketStatus::Confirmed, $order->fresh()->status);
    }

    #[Test]
    public function a_full_ticket_type_blocks_restoring(): void
    {
        $this->app['auth']->forgetGuards();

        $type = $this->futureEvent->ticketTypes()->create([
            'name' => 'Limit', 'price_amount' => 0, 'capacity' => 1, 'is_active' => true,
        ]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Prvý', 'holder_email' => 'prvy@example.com',
            'items' => [['ticket_type_id' => $type->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $order = Ticket::query()->where('holder_email', 'prvy@example.com')->firstOrFail();

        $this->actingAsOrganizer();
        $this->postJson("/api/dashboard/tickets/{$order->id}")->assertOk();

        // Uvoľnené miesto medzitým obsadil niekto iný.
        $this->app['auth']->forgetGuards();
        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Druhý', 'holder_email' => 'druhy@example.com',
            'items' => [['ticket_type_id' => $type->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $this->actingAsOrganizer();
        $this->postJson("/api/dashboard/tickets/{$order->id}/restore")->assertStatus(422);

        $this->assertSame(TicketStatus::Cancelled, $order->fresh()->status);
    }

    #[Test]
    public function only_a_cancelled_order_can_be_deleted_and_nothing_is_sent(): void
    {
        $order = $this->order('mazanie@example.com');
        $this->actingAsOrganizer();

        // Až teraz — vydanie objednávky samo o sebe posiela vstupenky.
        Notification::fake();

        $this->deleteJson("/api/dashboard/tickets/{$order->id}")->assertStatus(422);

        $this->postJson("/api/dashboard/tickets/{$order->id}")->assertOk();
        $this->deleteJson("/api/dashboard/tickets/{$order->id}")->assertOk();

        $this->assertSoftDeleted('tickets', ['id' => $order->id]);
        $this->assertSoftDeleted('ticket_admissions', ['ticket_id' => $order->id]);

        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/tickets")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Notification::assertNothingSent();
    }

    #[Test]
    public function a_reservation_can_be_confirmed_and_marked_paid(): void
    {
        $order = $this->order('platba@example.com', quantity: 1, price: 1500);
        $this->actingAsOrganizer();

        $this->assertSame(TicketStatus::Reserved, $order->status);
        $this->assertSame(TicketPaymentStatus::Pending, $order->payment_status);

        $this->postJson("/api/dashboard/tickets/{$order->id}/confirm")
            ->assertOk()
            ->assertJsonPath('status', TicketStatus::Confirmed->value);

        // Potvrdenú objednávku už nemá zmysel potvrdzovať znova.
        $this->postJson("/api/dashboard/tickets/{$order->id}/confirm")->assertStatus(422);

        $this->postJson("/api/dashboard/tickets/{$order->id}/paid")
            ->assertOk()
            ->assertJsonPath('payment_status', TicketPaymentStatus::Paid->value);

        $this->postJson("/api/dashboard/tickets/{$order->id}/paid")->assertStatus(422);
    }

    #[Test]
    public function the_attendee_list_can_be_filtered(): void
    {
        $live = $this->order('ziva@example.com', quantity: 1);
        $cancelled = $this->order('zrusena@example.com', quantity: 1);

        $this->actingAsOrganizer();
        $this->postJson("/api/dashboard/tickets/{$cancelled->id}")->assertOk();

        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/tickets?status=cancelled")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.holder_email', 'zrusena@example.com');

        // Nikto zatiaľ neprešiel vchodom.
        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/tickets?checkin=arrived")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $admission = $live->admissions()->firstOrFail();
        $this->postJson('/api/dashboard/tickets/checkin/manual', ['admission_id' => $admission->id])->assertOk();

        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/tickets?checkin=arrived")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.holder_email', 'ziva@example.com');

        $typeId = $admission->ticket_type_id;
        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/tickets?ticket_type_id={$typeId}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function the_attendee_summary_reports_the_door_and_the_types(): void
    {
        $order = $this->order('prehlad@example.com', quantity: 3);
        $this->actingAsOrganizer();

        $admission = $order->admissions()->orderBy('id')->firstOrFail();
        $this->postJson('/api/dashboard/tickets/checkin/manual', ['admission_id' => $admission->id])->assertOk();

        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/attendee-stats")
            ->assertOk()
            ->assertJsonPath('admissions.total', 3)
            ->assertJsonPath('admissions.arrived', 1)
            ->assertJsonPath('admissions.remaining', 2)
            ->assertJsonPath('orders.confirmed', 1)
            ->assertJsonPath('types.0.sold', 3)
            ->assertJsonPath('types.0.arrived', 1);
    }
}
