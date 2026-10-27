<?php

namespace Tests\Feature\Tickets;

use App\Enums\AdmissionStatus;
use App\Enums\TicketPaymentStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Notifications\TicketIssued;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Pokyny na úhradu platenej objednávky: účet organizácie kanála z Accountu
 * v e-maile s rekapituláciou, na stránke lístka a v „Mojich objednávkach".
 */
class PaymentInstructionsTest extends EventSetupTest
{
    private const ACCOUNT_UUID = '11111111-2222-3333-4444-555555555555';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('account.url', 'https://account.test');
        config()->set('account.token', 'acc_test');
        Notification::fake();

        Http::fake([
            'https://account.test/api/v1/organizations/'.self::ACCOUNT_UUID => Http::response(['data' => [
                'name' => 'Farnosť',
                'legal_name' => 'Rímskokatolícka farnosť Hronský Beňadik',
                'bank' => ['name' => 'Tatra banka', 'iban' => 'sk3111000000002612345678', 'swift' => 'TATRSKBX'],
            ]]),
        ]);
    }

    private function linkOrganization(): void
    {
        $organization = Organization::query()->create([
            'title' => 'Farnosť',
            'status' => 'draft',
            'account_uuid' => self::ACCOUNT_UUID,
        ]);
        $this->futureEvent->canal->update(['organization_id' => $organization->id]);
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

    #[Test]
    public function payment_email_and_ticket_page_show_organization_bank_account(): void
    {
        $this->linkOrganization();
        $ticket = $this->order(1500);

        Notification::assertSentOnDemand(TicketIssued::class, function (TicketIssued $notification) use ($ticket) {
            $payment = $notification->toMail($ticket)->viewData['payment'];

            return $payment['iban'] === 'SK31 1100 0000 0026 1234 5678'
                && $payment['recipient'] === 'Rímskokatolícka farnosť Hronský Beňadik'
                && $payment['swift'] === 'TATRSKBX'
                && $payment['variable_symbol'] === (string) $ticket->id;
        });

        $this->getJson("/api/tickets/{$ticket->uuid}")
            ->assertOk()
            ->assertJsonPath('payment_instructions.iban', 'SK31 1100 0000 0026 1234 5678')
            ->assertJsonPath('payment_instructions.amount', 1500)
            ->assertJsonPath('payment_instructions.variable_symbol', (string) $ticket->id);
    }

    #[Test]
    public function rendered_email_lists_iban_and_variable_symbol(): void
    {
        $this->linkOrganization();
        $ticket = $this->order(1500);

        $html = (string) (new TicketIssued($ticket))->toMail($ticket)->render();

        $this->assertStringContainsString('SK31 1100 0000 0026 1234 5678', $html);
        $this->assertStringContainsString('TATRSKBX', $html);
        $this->assertStringContainsString((string) $ticket->id, $html);
    }

    #[Test]
    public function without_organization_there_are_no_instructions(): void
    {
        $ticket = $this->order(1500);

        $this->getJson("/api/tickets/{$ticket->uuid}")->assertOk()->assertJsonPath('payment_instructions', null);
        Notification::assertSentOnDemand(TicketIssued::class, fn (TicketIssued $n) => $n->toMail($ticket)->viewData['payment'] === null);
    }

    #[Test]
    public function paid_order_has_no_instructions(): void
    {
        $this->linkOrganization();
        $ticket = $this->order(1500);
        $ticket->update(['payment_status' => TicketPaymentStatus::Paid->value]);

        $this->getJson("/api/tickets/{$ticket->uuid}")->assertOk()->assertJsonPath('payment_instructions', null);
    }

    #[Test]
    public function attendee_in_foreign_order_does_not_get_instructions(): void
    {
        $this->linkOrganization();
        $ticket = $this->order(1500);
        $ticket->admissions()->create([
            'event_id' => $this->futureEvent->id,
            'status' => AdmissionStatus::Valid->value,
            'attendee_email' => $this->user->email,
        ]);

        $this->actingAs($this->user, 'sanctum');
        $this->getJson('/api/me/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.attendee_only', true)
            ->assertJsonPath('data.0.payment_instructions', null);
    }

    #[Test]
    public function ticket_settings_warn_about_paid_questionnaire_options_too(): void
    {
        $this->futureEvent->ticketTypes()->create(['name' => 'Rezervácia', 'price_amount' => 0, 'is_active' => true]);
        $this->user->givePermissionTo('event.view');
        $this->actingAs($this->user, 'sanctum');

        $url = "/api/dashboard/events/{$this->futureEvent->id}/ticket-types";
        $this->getJson($url)->assertOk()->assertJsonPath('meta.payment_account_ready', null);

        $question = $this->futureEvent->questions()->create(['scope' => 'attendee', 'type' => 'single_choice', 'label' => 'Ubytovanie']);
        $question->options()->create(['label' => 'Áno', 'price_amount' => 3000]);

        $this->getJson($url)->assertOk()->assertJsonPath('meta.payment_account_ready', false);
    }

    #[Test]
    public function ticket_settings_warn_when_paid_types_have_no_bank_account(): void
    {
        $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 1000, 'is_active' => true]);
        $this->user->givePermissionTo('event.view');
        $this->actingAs($this->user, 'sanctum');

        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('meta.payment_account_ready', false);

        $this->linkOrganization();

        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('meta.payment_account_ready', true);
    }
}
