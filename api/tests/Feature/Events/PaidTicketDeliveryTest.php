<?php

namespace Tests\Feature\Events;

use App\Models\Ticket;
use App\Notifications\TicketIssued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Platená objednávka: najprv výzva na úhradu bez QR, vstupenky až po
 * označení ako uhradené; check-in na neuhradenú objednávku upozorní.
 */
class PaidTicketDeliveryTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    private function order(int $price): Ticket
    {
        $this->app['auth']->forgetGuards();

        $type = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => $price, 'is_active' => true]);

        $uuid = $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Janko',
            'holder_email' => 'janko@example.com',
            'items' => [['ticket_type_id' => $type->id, 'quantity' => 1]],
        ])->assertStatus(201)->json('uuid');

        return Ticket::query()->where('uuid', $uuid)->firstOrFail();
    }

    private function actingAsOrganizer(): void
    {
        $this->user->givePermissionTo(['ticket.view', 'ticket.update', 'ticket.checkin']);
        $this->actingAs($this->user, 'sanctum');
    }

    #[Test]
    public function paid_order_gets_payment_request_without_qr(): void
    {
        $ticket = $this->order(1500);

        $this->assertTrue($ticket->awaitsPayment());
        Notification::assertSentOnDemand(TicketIssued::class, function (TicketIssued $n) use ($ticket) {
            return $n->toMail($ticket)->markdown === 'mail.ticket-payment-requested';
        });

        $this->getJson("/api/tickets/{$ticket->uuid}")->assertOk()->assertJsonPath('awaits_payment', true);
        $this->get('/api/admissions/'.$ticket->admissions()->first()->uuid.'/qr')->assertNotFound();
    }

    #[Test]
    public function free_order_gets_qr_right_away(): void
    {
        $ticket = $this->order(0);

        $this->assertFalse($ticket->awaitsPayment());
        Notification::assertSentOnDemand(TicketIssued::class, fn (TicketIssued $n) => $n->toMail($ticket)->markdown === 'mail.ticket-issued');
        $this->get('/api/admissions/'.$ticket->admissions()->first()->uuid.'/qr')->assertOk();
    }

    #[Test]
    public function marking_paid_sends_tickets_with_qr(): void
    {
        $ticket = $this->order(1500);

        $this->actingAsOrganizer();
        $this->postJson("/api/dashboard/tickets/{$ticket->id}/paid")->assertOk()->assertJsonPath('awaits_payment', false);

        $ticket->refresh();
        Notification::assertSentOnDemandTimes(TicketIssued::class, 2);
        Notification::assertSentOnDemand(TicketIssued::class, fn (TicketIssued $n) => $n->toMail($ticket)->markdown === 'mail.ticket-issued');
        $this->get('/api/admissions/'.$ticket->admissions()->first()->uuid.'/qr')->assertOk();
    }

    #[Test]
    public function checkin_warns_about_unpaid_order(): void
    {
        $ticket = $this->order(1500);
        $admission = $ticket->admissions()->firstOrFail();

        $this->actingAsOrganizer();
        $this->postJson('/api/dashboard/tickets/checkin/manual', ['admission_id' => $admission->id])
            ->assertOk()
            ->assertJsonPath('status', 'checked_in')
            ->assertJsonPath('payment_due.amount', 1500);
    }
}
