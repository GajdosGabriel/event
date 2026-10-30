<?php

namespace Tests\Feature\Events;

use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Models\EventMessage;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Notifications\EventAnnouncement;
use App\Notifications\TicketCancelledByOrganizer;
use App\Notifications\TicketIssued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Práca organizátora s prihlásenými: adresné správy, hromadné akcie, oprava
 * kontaktu, vrátenie platby a ručné pridanie účastníka.
 */
class AttendeeWorkspaceTest extends EventSetupTest
{
    private TicketType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user->givePermissionTo(['ticket.view', 'ticket.update', 'event.update']);
        $this->user->forceFill(['email_verified_at' => now()])->save();
        $this->futureEvent->update(['status' => 'published', 'registration_deadline_at' => null]);
        $this->type = $this->futureEvent->ticketTypes()->create(['name' => 'Štandard', 'is_active' => true]);
        $this->actingAs($this->user, 'sanctum');
    }

    private function order(string $email, array $attributes = []): Ticket
    {
        $order = Ticket::query()->create([
            'event_id' => $this->futureEvent->id,
            'holder_name' => 'Jana Nováková',
            'holder_email' => $email,
            'quantity' => 1,
            'status' => TicketStatus::Confirmed->value,
            ...$attributes,
        ]);
        $order->admissions()->create([
            'event_id' => $this->futureEvent->id,
            'ticket_type_id' => $this->type->id,
            'status' => 'valid',
        ]);

        return $order;
    }

    private function url(string $path = ''): string
    {
        return "/api/dashboard/events/{$this->futureEvent->id}{$path}";
    }

    #[Test]
    public function a_message_can_go_to_one_person_and_is_logged(): void
    {
        Notification::fake();
        $picked = $this->order('vybrana@example.com');
        $this->order('ina@example.com');

        $this->postJson($this->url('/attendees/email'), [
            'subject' => 'Osobná správa',
            'body' => 'Dobrý deň, ozývam sa kvôli vašej objednávke.',
            'ticket_ids' => [$picked->id],
        ])->assertOk()->assertJsonPath('recipients', 1);

        Notification::assertSentOnDemandTimes(EventAnnouncement::class, 1);
        Notification::assertSentOnDemand(EventAnnouncement::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'vybrana@example.com');

        $this->getJson($this->url('/attendees/messages'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.audience', 'selected')
            ->assertJsonPath('data.0.recipients.0', 'vybrana@example.com');
    }

    #[Test]
    public function selected_ids_from_another_event_reach_nobody(): void
    {
        Notification::fake();
        $foreign = Ticket::query()->create([
            'event_id' => $this->cudziEvent->id,
            'holder_name' => 'Cudzí',
            'holder_email' => 'cudzi@example.com',
            'quantity' => 1,
            'status' => TicketStatus::Confirmed->value,
        ]);

        $this->postJson($this->url('/attendees/email'), [
            'subject' => 'Pokus o cudzích',
            'body' => 'Toto nesmie odísť cudziemu účastníkovi.',
            'ticket_ids' => [$foreign->id],
        ])->assertStatus(422);

        Notification::assertNothingSent();
    }

    #[Test]
    public function audience_narrows_recipients_to_unpaid_orders(): void
    {
        $this->order('dlzi@example.com', ['payment_status' => TicketPaymentStatus::Pending->value, 'price_amount' => 500]);
        $this->order('zaplatil@example.com', ['payment_status' => TicketPaymentStatus::Paid->value, 'price_amount' => 500]);

        $this->getJson($this->url('/attendees/recipients?audience=unpaid'))->assertJsonPath('recipients', 1);
        $this->getJson($this->url('/attendees/recipients'))->assertJsonPath('recipients', 2);
    }

    #[Test]
    public function cancelling_emails_the_attendee_only_when_asked(): void
    {
        Notification::fake();
        $silent = $this->order('ticho@example.com');
        $told = $this->order('povedane@example.com');

        $this->postJson("/api/dashboard/tickets/{$silent->id}")->assertOk();
        Notification::assertNothingSent();

        $this->postJson("/api/dashboard/tickets/{$told->id}", ['notify' => true, 'message' => 'Kapacita sály sa znížila.'])->assertOk();

        Notification::assertSentOnDemand(TicketCancelledByOrganizer::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'povedane@example.com');
        $this->assertSame(TicketStatus::Cancelled, $told->fresh()->status);
        $this->assertSame(1, EventMessage::query()->where('event_id', $this->futureEvent->id)->where('kind', 'cancellation')->count());
    }

    #[Test]
    public function bulk_cancel_skips_what_is_already_cancelled_and_foreign_orders(): void
    {
        Notification::fake();
        $active = $this->order('aktivna@example.com');
        $cancelled = $this->order('zrusena@example.com', ['status' => TicketStatus::Cancelled->value]);
        $foreign = Ticket::query()->create([
            'event_id' => $this->cudziEvent->id,
            'holder_name' => 'Cudzí',
            'holder_email' => 'cudzi@example.com',
            'quantity' => 1,
            'status' => TicketStatus::Confirmed->value,
        ]);

        $this->postJson($this->url('/tickets/bulk'), [
            'action' => 'cancel',
            'ticket_ids' => [$active->id, $cancelled->id, $foreign->id],
            'notify' => true,
        ])->assertOk()->assertJsonPath('done', 1)->assertJsonPath('skipped', 2);

        $this->assertSame(TicketStatus::Cancelled, $active->fresh()->status);
        $this->assertSame(TicketStatus::Confirmed, $foreign->fresh()->status);
        Notification::assertSentOnDemandTimes(TicketCancelledByOrganizer::class, 1);
    }

    #[Test]
    public function bulk_mark_paid_delivers_tickets(): void
    {
        Notification::fake();
        $unpaid = $this->order('dlzi@example.com', [
            'status' => TicketStatus::Reserved->value,
            'payment_status' => TicketPaymentStatus::Pending->value,
            'price_amount' => 500,
        ]);

        $this->postJson($this->url('/tickets/bulk'), ['action' => 'mark_paid', 'ticket_ids' => [$unpaid->id]])
            ->assertOk()->assertJsonPath('done', 1);

        $this->assertSame(TicketPaymentStatus::Paid, $unpaid->fresh()->payment_status);
        Notification::assertSentOnDemand(TicketIssued::class);
    }

    #[Test]
    public function only_a_paid_order_can_be_marked_refunded(): void
    {
        $free = $this->order('zdarma@example.com');
        $paid = $this->order('platil@example.com', ['payment_status' => TicketPaymentStatus::Paid->value, 'price_amount' => 500]);

        $this->postJson("/api/dashboard/tickets/{$free->id}/refunded")->assertStatus(422);
        $this->postJson("/api/dashboard/tickets/{$paid->id}/refunded")->assertOk();

        $this->assertSame(TicketPaymentStatus::Refunded, $paid->fresh()->payment_status);
    }

    #[Test]
    public function the_contact_can_be_corrected(): void
    {
        $order = $this->order('preklep@exmaple.com');

        $this->putJson("/api/dashboard/tickets/{$order->id}/contact", [
            'holder_name' => 'Jana Nová',
            'holder_email' => 'jana@example.com',
            'holder_phone' => '0900111222',
        ])->assertOk()->assertJsonPath('holder_email', 'jana@example.com');

        $this->assertSame('Jana Nová', $order->fresh()->holder_name);
    }

    #[Test]
    public function an_organizer_can_add_an_attendee_with_or_without_email(): void
    {
        Notification::fake();

        $this->postJson($this->url('/attendees'), [
            'holder_name' => 'Hosť Bez E-mailu',
            'holder_email' => 'host@example.com',
            'ticket_type_id' => $this->type->id,
            'send_email' => false,
        ])->assertCreated();
        Notification::assertNothingSent();

        $this->postJson($this->url('/attendees'), [
            'holder_name' => 'Hosť S E-mailom',
            'holder_email' => 'host2@example.com',
            'ticket_type_id' => $this->type->id,
            'quantity' => 2,
        ])->assertCreated()->assertJsonPath('quantity', 2);
        Notification::assertSentOnDemand(TicketIssued::class);
    }

    #[Test]
    public function the_new_endpoints_are_closed_to_foreign_events(): void
    {
        Notification::fake();
        $url = "/api/dashboard/events/{$this->cudziEvent->id}";

        $this->getJson("$url/attendees/messages")->assertForbidden();
        $this->postJson("$url/attendees", [
            'holder_name' => 'Cudzí',
            'holder_email' => 'cudzi@example.com',
            'ticket_type_id' => $this->type->id,
        ])->assertForbidden();

        $foreign = Ticket::query()->create([
            'event_id' => $this->cudziEvent->id,
            'holder_name' => 'Cudzí',
            'holder_email' => 'cudzi@example.com',
            'quantity' => 1,
            'status' => TicketStatus::Confirmed->value,
        ]);
        $this->postJson("$url/tickets/bulk", ['action' => 'cancel', 'ticket_ids' => [$foreign->id]])->assertForbidden();
        $this->putJson("/api/dashboard/tickets/{$foreign->id}/contact", [
            'holder_name' => 'X',
            'holder_email' => 'x@example.com',
        ])->assertForbidden();
        $this->assertSame(TicketStatus::Confirmed, $foreign->fresh()->status);
    }
}
