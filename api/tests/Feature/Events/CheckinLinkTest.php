<?php

namespace Tests\Feature\Events;

use App\Enums\AdmissionStatus;
use App\Enums\ModelStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketTypeKind;
use App\Models\Admission;
use App\Models\CheckinLink;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Odkaz na skener pre dobrovoľníkov pri vchode — bez účtu, len pre jedno
 * podujatie, s koncom platnosti a možnosťou zrušenia.
 */
class CheckinLinkTest extends EventSetupTest
{
    #[Test]
    public function organizer_creates_a_link_and_a_volunteer_checks_in_without_an_account(): void
    {
        $admission = $this->admission($this->futureEvent);

        $created = $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/checkin-links", ['label' => 'Vchod A'])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Vchod A');
        $url = $created->json('data.url');
        $this->assertStringContainsString('/vstup/', $url);
        $token = basename($url);

        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/checkin-links")
            ->assertOk()->assertJsonCount(1, 'data');

        // Dobrovoľník: žiadne prihlásenie.
        auth()->forgetGuards();
        $this->app['auth']->guard('sanctum')->forgetUser();

        $this->getJson("/api/checkin/{$token}")
            ->assertOk()
            ->assertJsonPath('event.id', $this->futureEvent->id)
            ->assertJsonPath('label', 'Vchod A')
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.arrived', 0);

        $scan = $this->postJson("/api/checkin/{$token}/scan", ['qr_token' => $admission->qr_token])
            ->assertOk()
            ->assertJsonPath('status', 'checked_in')
            ->assertJsonPath('admission.holder_name', 'Janko Hosť')
            ->assertJsonPath('admission.checked_in_link.label', 'Vchod A');
        // Dobrovoľník nedostane kontakty ani odkaz na QR kód.
        $this->assertArrayNotHasKey('qr_url', $scan->json('admission'));
        $this->assertStringNotContainsString('janko@example.test', $scan->getContent());

        $fresh = $admission->fresh();
        $this->assertNotNull($fresh->checked_in_at);
        $this->assertNull($fresh->checked_in_by);
        $this->assertNotNull($fresh->checked_in_link_id);

        // Druhý sken je idempotentný a povie, ktorý odkaz pustil.
        $this->postJson("/api/checkin/{$token}/scan", ['qr_token' => $admission->qr_token])
            ->assertJsonPath('status', 'already_checked_in')
            ->assertJsonPath('admission.checked_in_link.label', 'Vchod A');

        $this->getJson("/api/checkin/{$token}/stats")->assertJsonPath('arrived', 1);
    }

    #[Test]
    public function link_only_works_for_its_own_event(): void
    {
        $link = $this->link($this->futureEvent);
        $other = $this->admission($this->pastEvent);

        $this->postJson("/api/checkin/{$link->token}/scan", ['qr_token' => $other->qr_token])
            ->assertOk()
            ->assertJsonPath('status', 'invalid')
            ->assertJsonPath('reason', 'not_found')
            ->assertJsonPath('admission', null);

        $this->assertNull($other->fresh()->checked_in_at);
    }

    #[Test]
    public function revoked_and_expired_links_stop_working(): void
    {
        $admission = $this->admission($this->futureEvent);
        $revoked = $this->link($this->futureEvent);
        $expired = $this->link($this->futureEvent, ['expires_at' => now()->subMinute()]);

        $this->deleteJson("/api/dashboard/events/{$this->futureEvent->id}/checkin-links/{$revoked->id}")->assertNoContent();

        foreach ([$revoked, $expired] as $link) {
            $this->getJson("/api/checkin/{$link->token}")->assertNotFound();
            $this->postJson("/api/checkin/{$link->token}/scan", ['qr_token' => $admission->qr_token])->assertNotFound();
        }
        $this->getJson('/api/checkin/neexistuje')->assertNotFound();

        $this->assertNull($admission->fresh()->checked_in_at);
        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/checkin-links")->assertJsonCount(0, 'data');
    }

    #[Test]
    public function organizer_of_another_canal_cannot_manage_links(): void
    {
        $this->postJson("/api/dashboard/events/{$this->cudziEvent->id}/checkin-links")->assertStatus(404);

        $foreign = $this->link($this->cudziEvent);
        $this->getJson("/api/dashboard/events/{$this->cudziEvent->id}/checkin-links")->assertStatus(404);
        $this->deleteJson("/api/dashboard/events/{$this->cudziEvent->id}/checkin-links/{$foreign->id}")->assertStatus(404);
        $this->assertNull($foreign->fresh()->revoked_at);
    }

    #[Test]
    public function link_expires_a_day_after_the_event(): void
    {
        $event = $this->futureEvent;
        $event->update(['start_at' => Carbon::parse('2030-05-01 18:00'), 'end_at' => Carbon::parse('2030-05-01 22:00')]);

        $this->assertTrue(Carbon::parse('2030-05-02 22:00')->equalTo(CheckinLink::expiryFor($event->fresh())));
    }

    #[Test]
    public function unconfirmed_reservation_is_not_admitted_through_the_link(): void
    {
        $link = $this->link($this->futureEvent);
        $admission = $this->admission($this->futureEvent);
        $admission->update(['status' => AdmissionStatus::Cancelled->value]);

        $this->postJson("/api/checkin/{$link->token}/scan", ['qr_token' => $admission->qr_token])
            ->assertJsonPath('status', 'invalid')
            ->assertJsonPath('reason', 'cancelled');
    }

    /** @param  array<string, mixed>  $attributes */
    private function link(Event $event, array $attributes = []): CheckinLink
    {
        return CheckinLink::query()->create([
            'event_id' => $event->id,
            'token' => CheckinLink::newToken(),
            'expires_at' => now()->addDay(),
            ...$attributes,
        ]);
    }

    private function admission(Event $event): Admission
    {
        $event->update(['status' => ModelStatus::Published->value]);

        $type = TicketType::create([
            'event_id' => $event->id,
            'name' => 'Vstupenka',
            'kind' => TicketTypeKind::Ticket->value,
            'capacity' => 100,
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'user_id' => $this->user->id,
            'holder_name' => 'Janko Hosť',
            'holder_email' => 'janko@example.test',
            'status' => TicketStatus::Confirmed->value,
        ]);

        return Admission::create([
            'uuid' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
            'qr_token' => Str::random(40),
            'status' => AdmissionStatus::Valid->value,
        ]);
    }
}
