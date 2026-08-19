<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Subscription;
use App\Notifications\EventChanged;
use App\Notifications\EventReminder;
use App\Notifications\SubscriptionConfirmed;
use App\Support\SubmissionTicket;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * „Pripomeň mi" — odber podujatia bez účtu.
 *
 * Vzniklo preto, že na bezplatnom podujatí bez lístkov sa na verejnom detaile
 * nedá spraviť vôbec nič. Sľub na tlačidle je „ozveme sa, keď sa niečo zmení
 * alebo zruší"; pripomienka pred začiatkom je až bonus. Testy sledujú oba.
 */
class EventSubscriptionTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Published]);
    }

    /** Známka, akú by si front vypýtal otvorením formulára. */
    private function ticketFor(Event $event, int $ageSeconds = 5): string
    {
        return $this->travelTo(now()->subSeconds($ageSeconds), fn () => SubmissionTicket::issue('subscription:' . $event->id));
    }

    #[Test]
    public function visitor_can_subscribe_without_an_account(): void
    {
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", [
            'email' => 'Navstevnik@Example.com',
            'ticket' => $this->ticketFor($this->futureEvent),
            'locale' => 'sk',
        ])->assertCreated();

        $subscription = Subscription::query()->firstOrFail();

        // Adresa sa normalizuje na malé písmená — inak by tá istá schránka
        // vedela odber založiť dvakrát.
        $this->assertSame('navstevnik@example.com', $subscription->email);
        $this->assertSame(Event::class, $subscription->subscribable_type);
        $this->assertSame($this->futureEvent->id, (int) $subscription->subscribable_id);
        $this->assertNotEmpty($subscription->token);

        Notification::assertSentOnDemand(SubscriptionConfirmed::class);
    }

    #[Test]
    public function subscribing_twice_does_not_create_a_second_row_or_a_second_email(): void
    {
        $payload = fn () => [
            'email' => 'navstevnik@example.com',
            'ticket' => $this->ticketFor($this->futureEvent),
        ];

        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", $payload())->assertCreated();
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", $payload())->assertCreated();

        $this->assertSame(1, Subscription::query()->count());
        Notification::assertSentOnDemandTimes(SubscriptionConfirmed::class, 1);
    }

    #[Test]
    public function submission_without_a_ticket_is_rejected(): void
    {
        // Bot, ktorý našiel adresu POSTu, známku nemá odkiaľ vziať.
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", [
            'email' => 'bot@example.com',
        ])->assertStatus(422);

        $this->assertSame(0, Subscription::query()->count());
    }

    #[Test]
    public function honeypot_field_rejects_the_submission(): void
    {
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", [
            'email' => 'bot@example.com',
            'ticket' => $this->ticketFor($this->futureEvent),
            'website' => 'https://spam.example',
        ])->assertStatus(422);

        $this->assertSame(0, Subscription::query()->count());
    }

    #[Test]
    public function draft_event_cannot_be_subscribed_to(): void
    {
        $this->futureEvent->update(['status' => ModelStatus::Draft]);

        // Rovnaká viditeľnosť ako verejný detail — cez odber sa nesmie dať
        // overiť, že koncept s daným id existuje.
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", [
            'email' => 'navstevnik@example.com',
            'ticket' => $this->ticketFor($this->futureEvent),
        ])->assertNotFound();
    }

    #[Test]
    public function unsubscribing_drops_the_address_and_is_idempotent(): void
    {
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", [
            'email' => 'navstevnik@example.com',
            'ticket' => $this->ticketFor($this->futureEvent),
        ])->assertCreated();

        $token = Subscription::query()->value('token');

        $this->deleteJson("/api/subscriptions/{$token}")
            ->assertOk()
            ->assertJsonPath('active', false);

        $subscription = Subscription::query()->firstOrFail();
        $this->assertNull($subscription->email, 'Odhlásenie musí adresu zahodiť.');
        $this->assertNotNull($subscription->unsubscribed_at);

        // Odkaz z pätičky sa dá otvoriť aj druhýkrát (klient si ho prednačíta,
        // človek ho preposiela) a nesmie z toho byť chyba.
        $this->deleteJson("/api/subscriptions/{$token}")
            ->assertOk()
            ->assertJsonPath('active', false);
    }

    #[Test]
    public function unknown_unsubscribe_token_is_a_404(): void
    {
        $this->deleteJson('/api/subscriptions/nieco-vymyslene')->assertNotFound();
    }

    #[Test]
    public function subscriber_gets_a_reminder_before_the_event(): void
    {
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", [
            'email' => 'navstevnik@example.com',
            'ticket' => $this->ticketFor($this->futureEvent),
        ])->assertCreated();

        // Predvolené okno odberateľa je 24 h. Organizátor tu `reminder_hours_before`
        // nastavené nemá — a to je celý zmysel: importované podujatia ho nemajú
        // takmer nikdy, takže bez vlastného okna by pripomienka nikdy neprišla.
        $this->futureEvent->forceFill(['start_at' => now()->addHours(3)])->save();

        $this->artisan('app:events-send-reminders')->assertSuccessful();

        Notification::assertSentOnDemand(EventReminder::class);
        $this->assertNotNull(Subscription::query()->value('notified_at'));
    }

    #[Test]
    public function reminder_is_sent_only_once(): void
    {
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", [
            'email' => 'navstevnik@example.com',
            'ticket' => $this->ticketFor($this->futureEvent),
        ])->assertCreated();

        $this->futureEvent->forceFill(['start_at' => now()->addHours(3)])->save();

        $this->artisan('app:events-send-reminders')->assertSuccessful();
        $this->artisan('app:events-send-reminders')->assertSuccessful();

        Notification::assertSentOnDemandTimes(EventReminder::class, 1);
    }

    #[Test]
    public function moving_the_event_notifies_subscribers(): void
    {
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", [
            'email' => 'navstevnik@example.com',
            'ticket' => $this->ticketFor($this->futureEvent),
        ])->assertCreated();

        $this->futureEvent->update(['start_at' => now()->addDays(20)]);

        Notification::assertSentOnDemand(EventChanged::class);
    }

    #[Test]
    public function editing_the_description_notifies_nobody(): void
    {
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", [
            'email' => 'navstevnik@example.com',
            'ticket' => $this->ticketFor($this->futureEvent),
        ])->assertCreated();

        // Organizátori podujatia upravujú často. Pár zbytočných e-mailov stačí
        // na to, aby sa odhlásili všetci — preto sa sleduje len to, čo mení
        // plán návštevníka.
        $this->futureEvent->update(['body' => '<p>Opravený preklep.</p>']);

        Notification::assertNotSentTo(new AnonymousNotifiable(), EventChanged::class);
    }

    #[Test]
    public function withdrawing_the_event_notifies_and_closes_the_subscriptions(): void
    {
        $this->postJson("/api/events/{$this->futureEvent->id}/subscription", [
            'email' => 'navstevnik@example.com',
            'ticket' => $this->ticketFor($this->futureEvent),
        ])->assertCreated();

        $this->futureEvent->update(['status' => ModelStatus::Draft]);

        Notification::assertSentOnDemand(EventChanged::class);

        // Zrušené podujatie nemá čo pripomínať — odbery naň zanikajú spolu
        // s ním, nech nám v tabuľke neležia adresy bez účelu.
        $subscription = Subscription::query()->firstOrFail();
        $this->assertNull($subscription->email);
        $this->assertNotNull($subscription->unsubscribed_at);
    }
}
