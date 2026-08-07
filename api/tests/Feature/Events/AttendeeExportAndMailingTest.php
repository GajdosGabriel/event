<?php

namespace Tests\Feature\Events;

use App\Enums\AdmissionStatus;
use App\Enums\ModelStatus;
use App\Enums\TicketStatus;
use App\Models\Admission;
use App\Models\Ticket;
use App\Notifications\EventAnnouncement;
use App\Notifications\EventReminder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Export účastníkov, hromadný e-mail a pripomienka (roadmap 3.5).
 */
class AttendeeExportAndMailingTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user->givePermissionTo('ticket.view');
        $this->user->forceFill(['email_verified_at' => now()])->save();

        // EventFactory losuje stav; archivované podujatie sa upravovať nedá
        // (DeniesArchivedUpdate), takže by hromadný e-mail padal podľa hodu kockou.
        $this->futureEvent->update(['status' => ModelStatus::Published->value]);
    }

    /** Objednávka s jednou vstupenkou. */
    private function order(array $ticket = [], array $admission = []): Admission
    {
        /** @var Ticket $order */
        $order = Ticket::query()->create(array_merge([
            'event_id' => $this->futureEvent->id,
            'holder_name' => 'Jana Nováková',
            'holder_email' => 'jana@example.com',
            'holder_phone' => '0900123456',
            'quantity' => 1,
            'status' => TicketStatus::Confirmed->value,
            'price_amount' => 1500,
            'price_currency' => 'EUR',
        ], $ticket));

        return Admission::query()->create(array_merge([
            'ticket_id' => $order->id,
            'event_id' => $this->futureEvent->id,
            'status' => AdmissionStatus::Valid->value,
        ], $admission));
    }

    #[Test]
    public function csv_export_contains_the_attendee_rows(): void
    {
        $this->order();

        $response = $this->get("/api/dashboard/events/{$this->futureEvent->id}/attendees/export");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        // BOM a bodkočiarka — bez nich to slovenský Excel otvorí ako jeden stĺpec
        // a s rozsypanou diakritikou.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Objednávateľ;', $csv);
        $this->assertStringContainsString('Jana Nováková', $csv);
        $this->assertStringContainsString('jana@example.com', $csv);
        $this->assertStringContainsString('15,00 EUR', $csv);
    }

    #[Test]
    public function bulk_email_reaches_every_attendee_exactly_once(): void
    {
        Notification::fake();

        // Dva lístky tej istej objednávky s vlastnými účastníkmi + jeden bez
        // vlastného e-mailu (ten dostane e-mail objednávateľ).
        $admission = $this->order();
        Admission::query()->create([
            'ticket_id' => $admission->ticket_id,
            'event_id' => $this->futureEvent->id,
            'status' => AdmissionStatus::Valid->value,
            'attendee_name' => 'Peter',
            'attendee_email' => 'peter@example.com',
        ]);

        // Zrušená vstupenka aj zrušená objednávka sú mimo hry.
        $this->order(['holder_email' => 'zrusena@example.com', 'status' => TicketStatus::Cancelled->value]);
        $this->order(
            ['holder_email' => 'zruseny-listok@example.com'],
            ['status' => AdmissionStatus::Cancelled->value],
        );

        $response = $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/attendees/email", [
            'subject' => 'Zmena parkovania',
            'body' => 'Parkovať sa dá len na hornom parkovisku pri škole.',
        ]);

        $response->assertOk()->assertJsonPath('recipients', 2);

        Notification::assertSentOnDemandTimes(EventAnnouncement::class, 2);
    }

    #[Test]
    public function bulk_email_requires_at_least_one_attendee(): void
    {
        $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/attendees/email", [
            'subject' => 'Nič',
            'body' => 'Nikomu to neodíde, lebo nikto nie je prihlásený.',
        ])->assertStatus(422);
    }

    #[Test]
    public function reminder_goes_out_once_inside_the_configured_window(): void
    {
        Notification::fake();

        $this->order();

        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => now()->addHours(20),
            'end_at' => now()->addHours(22),
            'reminder_hours_before' => 24,
            'reminder_sent_at' => null,
        ]);

        $this->artisan('app:events-send-reminders')->assertSuccessful();

        Notification::assertSentOnDemandTimes(EventReminder::class, 1);
        $this->assertNotNull($this->futureEvent->fresh()->reminder_sent_at);

        // Druhý beh už neposiela nič — inak by účastník dostal pripomienku
        // každých desať minút až do začiatku akcie.
        $this->artisan('app:events-send-reminders')->assertSuccessful();

        Notification::assertSentOnDemandTimes(EventReminder::class, 1);
    }

    #[Test]
    public function reminder_waits_until_the_window_opens(): void
    {
        Notification::fake();

        $this->order();

        $this->futureEvent->update([
            'status' => ModelStatus::Published->value,
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(2),
            'reminder_hours_before' => 24,
            'reminder_sent_at' => null,
        ]);

        $this->artisan('app:events-send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($this->futureEvent->fresh()->reminder_sent_at);
    }

    #[Test]
    public function reminder_is_skipped_for_unpublished_events(): void
    {
        Notification::fake();

        $this->order();

        $this->futureEvent->update([
            'status' => ModelStatus::Draft->value,
            'start_at' => now()->addHours(5),
            'end_at' => now()->addHours(7),
            'reminder_hours_before' => 24,
            'reminder_sent_at' => null,
        ]);

        $this->artisan('app:events-send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    #[Test]
    public function attendee_endpoints_are_closed_to_foreign_events(): void
    {
        $this->get("/api/dashboard/events/{$this->cudziEvent->id}/attendees/export")->assertStatus(403);

        $this->postJson("/api/dashboard/events/{$this->cudziEvent->id}/attendees/email", [
            'subject' => 'Cudzie podujatie',
            'body' => 'Toto by nemalo prejsť ani náhodou.',
        ])->assertStatus(403);
    }
}
