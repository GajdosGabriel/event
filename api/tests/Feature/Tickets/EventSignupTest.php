<?php

namespace Tests\Feature\Tickets;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalInvitation;
use App\Models\Event;
use App\Models\PendingRegistration;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\EventInterestRecorded;
use App\Notifications\EventReservationInvite;
use App\Notifications\EventSignupAdminNotice;
use App\Notifications\EventSignupOrganizerNotice;
use App\Notifications\PendingRegistrationVerification;
use App\Notifications\TicketIssued;
use App\Services\Tickets\DefaultReservation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * „Rezervovať" / „Prihlásiť sa": registrácia s podujatím → overenie e-mailu →
 * e-mail „rezervujte si miesto" → rezervácia jedným klikom → e-mail
 * organizátorovi a super-adminom (EventSignup, DefaultReservation).
 */
class EventSignupTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
    }

    private function importedEvent(array $attributes = []): Event
    {
        $canal = Canal::factory()->create([
            'registration_source' => RegistrationSource::IMPORT->value,
            'email' => null,
        ]);

        return Event::factory()->future()->create([
            'canal_id' => $canal->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subDay(),
            'registration_deadline_at' => null,
            'price_amount' => null,
            'email' => 'farnost@example.sk',
            ...$attributes,
        ]);
    }

    private function registerAndVerify(Event $event, string $email = 'ucastnik@example.sk'): \Illuminate\Testing\TestResponse
    {
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'tajneheslo1',
            'password_confirmation' => 'tajneheslo1',
            'display_name' => 'Ján Účastník',
            'terms_accepted' => true,
            'event_id' => $event->id,
        ])->assertCreated()->assertJsonPath('event.id', $event->id);

        $token = null;
        Notification::assertSentOnDemand(
            PendingRegistrationVerification::class,
            function (PendingRegistrationVerification $n) use (&$token) {
                $token = (fn () => $this->token)->call($n);

                return true;
            },
        );

        return $this->getJson('/api/register/verify/'.$token);
    }

    #[Test]
    public function event_without_tickets_offers_reservation_without_a_ticket_type_in_the_database(): void
    {
        $event = $this->importedEvent(['price_amount' => 15]);

        $this->assertSame(0, $event->ticketTypes()->withTrashed()->count());
        $this->assertFalse($event->fresh()->tickets_enabled);
        $this->assertTrue($event->fresh()->isReservable());
        $this->assertSame('reserve', $event->fresh()->ticketCta()['kind']);

        $this->getJson("/api/events/{$event->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('data.0.id', DefaultReservation::VIRTUAL_ID)
            ->assertJsonPath('data.0.name', DefaultReservation::NAME);

        $this->assertSame(0, $event->ticketTypes()->withTrashed()->count());
    }

    #[Test]
    public function first_guest_order_creates_the_free_ticket_type(): void
    {
        Notification::fake();
        $event = $this->importedEvent();

        foreach (['prvy@example.sk', 'druhy@example.sk'] as $email) {
            $this->postJson("/api/events/{$event->id}/tickets", [
                'holder_name' => 'Hosť',
                'holder_email' => $email,
                'items' => [['ticket_type_id' => DefaultReservation::VIRTUAL_ID, 'quantity' => 2]],
            ])->assertCreated();
        }

        $this->assertSame(1, $event->ticketTypes()->count());
        $this->assertSame(DefaultReservation::NAME, $event->ticketTypes()->value('name'));
        $this->assertSame(4, $event->ticketTypes()->first()->sold_count);
        Notification::assertSentTo($this->admin, EventSignupAdminNotice::class);
    }

    #[Test]
    public function event_with_disabled_tickets_offers_nothing(): void
    {
        $event = $this->importedEvent();
        $event->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => false]);

        $this->assertFalse($event->fresh()->isReservable());
        $this->assertNull($event->fresh()->ticketCta());
        $this->getJson("/api/events/{$event->id}/ticket-types")->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function registration_remembers_the_event_and_mentions_it_in_the_verification_email(): void
    {
        Notification::fake();
        $event = $this->importedEvent();

        $this->postJson('/api/register', [
            'email' => 'novy@example.sk',
            'password' => 'tajneheslo1',
            'password_confirmation' => 'tajneheslo1',
            'display_name' => 'Nový',
            'terms_accepted' => true,
            'event_id' => $event->id,
        ])->assertCreated();

        $this->assertSame($event->id, PendingRegistration::where('email', 'novy@example.sk')->value('event_id'));

        Notification::assertSentOnDemand(PendingRegistrationVerification::class, function ($n, $channels, $notifiable) use ($event) {
            $mail = $n->toMail($notifiable);

            return str_contains($mail->subject, $event->name)
                && str_contains($mail->actionUrl, '/verify-email/');
        });
    }

    #[Test]
    public function verification_sends_an_invitation_to_reserve_but_does_not_reserve_yet(): void
    {
        Notification::fake();
        $event = $this->importedEvent();

        $this->registerAndVerify($event)
            ->assertOk()
            ->assertJsonPath('reserve_event.id', $event->id);

        Notification::assertSentOnDemand(
            EventReservationInvite::class,
            fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'ucastnik@example.sk'
                && str_contains($n->toMail($notifiable)->actionUrl, '/prihlasenie/'.$event->id),
        );
        $this->assertSame(0, Ticket::where('event_id', $event->id)->count());
        Notification::assertNothingSentTo($this->admin);
    }

    #[Test]
    public function one_click_signup_reserves_and_invites_the_organizer_of_an_imported_canal(): void
    {
        Notification::fake();
        $event = $this->importedEvent(['price_amount' => 10]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/signup")
            ->assertOk()
            ->assertJsonPath('data.status', 'reserved');

        $this->assertDatabaseHas('tickets', ['event_id' => $event->id, 'user_id' => $user->id]);
        Notification::assertSentOnDemand(TicketIssued::class);

        $invitation = CanalInvitation::where('canal_id', $event->canal_id)->firstOrFail();
        $this->assertSame('farnost@example.sk', $invitation->email);
        $this->assertSame(CanalRole::Owner, $invitation->role);

        Notification::assertSentOnDemand(EventSignupOrganizerNotice::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'farnost@example.sk');
        Notification::assertSentTo($this->admin, EventSignupAdminNotice::class);
    }

    #[Test]
    public function repeated_signups_reuse_the_same_takeover_invitation_and_are_idempotent(): void
    {
        Notification::fake();
        $event = $this->importedEvent();

        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->postJson("/api/events/{$event->id}/signup")->assertOk();
        }

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/signup")
            ->assertJsonPath('data.status', 'already_registered');

        $this->assertSame(2, Ticket::where('event_id', $event->id)->count());
        $this->assertSame(1, CanalInvitation::where('canal_id', $event->canal_id)->pending()->count());
    }

    #[Test]
    public function disabled_reservation_records_only_interest(): void
    {
        Notification::fake();
        $event = $this->importedEvent();
        $event->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => false]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/signup")
            ->assertOk()
            ->assertJsonPath('data.status', 'interest');

        $this->assertSame(0, Ticket::where('event_id', $event->id)->count());
        $this->assertSame(0, $event->ticketTypes()->where('is_active', true)->count());
        Notification::assertSentOnDemand(EventInterestRecorded::class);
        Notification::assertSentTo($this->admin, EventSignupAdminNotice::class);
    }

    #[Test]
    public function owner_of_a_managed_canal_is_notified_about_a_regular_ticket_order(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $canal = $owner->canals()->firstOrFail();
        $canal->forceFill(['registration_source' => RegistrationSource::SELF->value])->save();

        $event = Event::factory()->future()->create([
            'canal_id' => $canal->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subDay(),
            'registration_deadline_at' => null,
        ]);

        $this->postJson("/api/events/{$event->id}/tickets", [
            'holder_name' => 'Hosť',
            'holder_email' => 'host@example.sk',
            'quantity' => 1,
        ])->assertCreated();

        Notification::assertSentTo($owner, EventSignupOrganizerNotice::class);
        Notification::assertSentTo($this->admin, EventSignupAdminNotice::class);
        $this->assertSame(0, CanalInvitation::where('canal_id', $canal->id)->count());
    }

    #[Test]
    public function unknown_or_draft_event_is_ignored_during_registration(): void
    {
        Notification::fake();
        $draft = $this->importedEvent(['status' => ModelStatus::Draft->value]);

        $this->postJson('/api/register', [
            'email' => 'draft@example.sk',
            'password' => 'tajneheslo1',
            'password_confirmation' => 'tajneheslo1',
            'display_name' => 'Draft',
            'terms_accepted' => true,
            'event_id' => $draft->id,
        ])->assertCreated()->assertJsonPath('event', null);

        $this->assertNull(PendingRegistration::where('email', 'draft@example.sk')->value('event_id'));
    }
}
