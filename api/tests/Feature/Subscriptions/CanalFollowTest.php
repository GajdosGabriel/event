<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Subscription;
use App\Notifications\CanalFollowStarted;
use App\Notifications\CanalNewEvents;
use App\Notifications\EventChanged;
use App\Services\Canals\CanalFollowing;
use App\Support\SubmissionTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Sledovanie organizátora: tlačidlo, automatické sledovanie po rezervácii,
 * lístku a otázke, súhrn nových akcií a e-mail účastníkom pri zmene akcie.
 */
class CanalFollowTest extends EventSetupTest
{
    private Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->canal = $this->canalPrimary;
        $this->canal->forceFill(['status' => ModelStatus::Published->value])->save();
    }

    private function ticket(): string
    {
        return $this->travelTo(
            now()->subSeconds(5),
            fn () => SubmissionTicket::issue('subscription:canal:'.$this->canal->id),
        );
    }

    private function follow(string $email = 'fan@example.com'): TestResponse
    {
        return $this->postJson("/api/canals/{$this->canal->id}/subscription", [
            'email' => $email,
            'ticket' => $this->ticket(),
            'locale' => 'sk',
        ]);
    }

    private function followers(): Builder
    {
        return Subscription::query()
            ->where('subscribable_type', Canal::class)
            ->where('subscribable_id', $this->canal->id);
    }

    private function confirmedFollower(string $email = 'fan@example.com', ?\DateTimeInterface $at = null): Subscription
    {
        return Subscription::query()->forceCreate([
            'subscribable_type' => Canal::class,
            'subscribable_id' => $this->canal->id,
            'email' => $email,
            'token' => Subscription::freshToken(),
            'locale' => 'sk',
            'confirmed_at' => $at ?? now()->subDay(),
        ]);
    }

    private function publishedEvent(array $attributes = []): Event
    {
        return Event::factory()->future()->create(array_merge([
            'canal_id' => $this->canal->id,
            'user_id' => $this->user->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMinutes(30),
            'followers_notified_at' => null,
        ], $attributes));
    }

    private function reserve(string $email = 'janko@example.com'): void
    {
        $this->app['auth']->forgetGuards();
        $this->futureEvent->ticketTypes()->firstOrCreate(['name' => 'Vstupenka'], ['price_amount' => 0, 'is_active' => true]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Janko Hosť',
            'holder_email' => $email,
            'quantity' => 1,
        ])->assertCreated();
    }

    #[Test]
    public function follow_button_works_right_away_and_sends_one_welcome(): void
    {
        $this->app['auth']->forgetGuards();

        $this->follow('Fan@Example.com')->assertCreated()->assertJson(['status' => 'subscribed']);
        $this->follow('fan@example.com')->assertCreated();

        $subscription = $this->followers()->firstOrFail();
        $this->assertSame('fan@example.com', $subscription->email);
        $this->assertNotNull($subscription->confirmed_at);
        $this->assertSame(1, $this->followers()->count());

        Notification::assertSentOnDemandTimes(CanalFollowStarted::class, 1);
    }

    #[Test]
    public function follow_without_a_ticket_or_on_a_hidden_canal_is_rejected(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/canals/{$this->canal->id}/subscription", ['email' => 'fan@example.com'])
            ->assertUnprocessable();

        // Známka podujatia s rovnakým číslom kanál neotvorí.
        $this->postJson("/api/canals/{$this->canal->id}/subscription", [
            'email' => 'fan@example.com',
            'ticket' => $this->travelTo(now()->subSeconds(5), fn () => SubmissionTicket::issue('subscription:'.$this->canal->id)),
        ])->assertUnprocessable();

        $this->canal->forceFill(['status' => ModelStatus::Draft->value])->save();
        $this->getJson("/api/canals/{$this->canal->id}/subscription")->assertNotFound();
    }

    #[Test]
    public function reservation_follows_the_organiser_without_asking_or_extra_mail(): void
    {
        $this->reserve('Janko@Example.com');

        $subscription = $this->followers()->firstOrFail();
        $this->assertSame('janko@example.com', $subscription->email);
        $this->assertNotNull($subscription->confirmed_at);

        Notification::assertNotSentTo(new AnonymousNotifiable, CanalFollowStarted::class);
        Notification::assertSentOnDemandTimes(CanalFollowStarted::class, 0);
    }

    #[Test]
    public function unsubscribed_address_is_not_followed_again_automatically(): void
    {
        $this->reserve();
        $this->followers()->firstOrFail()->unsubscribe();

        // Ďalšia rezervácia u toho istého organizátora — odhlásenie platí.
        $this->reserve();
        $this->assertSame(0, $this->followers()->active()->count());

        // Tlačidlo je nové výslovné rozhodnutie — to áno.
        $this->follow('janko@example.com')->assertCreated();
        $this->assertSame(1, $this->followers()->active()->count());
    }

    #[Test]
    public function question_with_an_address_follows_the_organiser(): void
    {
        $event = $this->publishedEvent();

        app(CanalFollowing::class)->followAfterInteraction($event, 'pytam@example.com', 'sk');
        app(CanalFollowing::class)->followAfterInteraction($event, null, 'sk');
        app(CanalFollowing::class)->followAfterInteraction($event, 'nie-je-adresa', 'sk');

        $this->assertSame(['pytam@example.com'], $this->followers()->pluck('email')->all());
    }

    #[Test]
    public function followers_get_one_digest_of_new_upcoming_events(): void
    {
        $this->confirmedFollower();

        $first = $this->publishedEvent();
        $second = $this->publishedEvent();
        // Práve zverejnená — počká na ďalší beh (organizátor ju ešte môže stiahnuť).
        $fresh = $this->publishedEvent(['published_at' => now()->subMinutes(2)]);
        $past = $this->publishedEvent(['start_at' => now()->subDay(), 'end_at' => now()->subDay()->addHour()]);

        $this->artisan('app:canal-followers-notify')->assertSuccessful();

        Notification::assertSentOnDemandTimes(CanalNewEvents::class, 1);
        Notification::assertSentOnDemand(CanalNewEvents::class, function (CanalNewEvents $notification, $channels, AnonymousNotifiable $notifiable) use ($first, $second) {
            $ids = (fn () => $this->eventIds)->call($notification);
            sort($ids);

            return $notifiable->routes['mail'] === 'fan@example.com' && $ids === [$first->id, $second->id];
        });

        $this->assertNotNull($first->fresh()->followers_notified_at);
        $this->assertNotNull($past->fresh()->followers_notified_at);
        $this->assertNull($fresh->fresh()->followers_notified_at);

        // Druhý beh tie isté akcie neopakuje.
        $this->artisan('app:canal-followers-notify')->assertSuccessful();
        Notification::assertSentOnDemandTimes(CanalNewEvents::class, 1);
    }

    #[Test]
    public function follower_gets_only_events_published_after_starting_to_follow(): void
    {
        $this->confirmedFollower(at: now()->subMinutes(20));
        $this->publishedEvent(['published_at' => now()->subHour()]);

        $this->artisan('app:canal-followers-notify')->assertSuccessful();

        Notification::assertNothingSent();
    }

    #[Test]
    public function republishing_an_event_does_not_notify_twice(): void
    {
        $this->confirmedFollower();
        $event = $this->publishedEvent(['followers_notified_at' => now()->subDay()]);

        $event->update(['status' => ModelStatus::Draft->value]);
        $event->update(['status' => ModelStatus::Published->value, 'published_at' => now()->subMinutes(30)]);

        $this->artisan('app:canal-followers-notify')->assertSuccessful();

        Notification::assertNotSentTo(new AnonymousNotifiable, CanalNewEvents::class);
        Notification::assertSentOnDemandTimes(CanalNewEvents::class, 0);
    }

    #[Test]
    public function attendees_are_told_when_the_event_moves_or_is_cancelled(): void
    {
        $this->futureEvent->update(['status' => ModelStatus::Published->value]);
        $this->reserve('ucastnik@example.com');

        $this->futureEvent->update(['start_at' => now()->addDays(30)]);

        Notification::assertSentOnDemand(EventChanged::class, function (EventChanged $notification, $channels, AnonymousNotifiable $notifiable) {
            return $notifiable->routes['mail'] === 'ucastnik@example.com';
        });

        $this->futureEvent->update(['status' => ModelStatus::Draft->value]);

        Notification::assertSentOnDemandTimes(EventChanged::class, 2);
    }

    #[Test]
    public function attendee_who_also_asked_for_reminders_gets_one_change_mail(): void
    {
        $this->futureEvent->update(['status' => ModelStatus::Published->value]);
        $this->reserve('ucastnik@example.com');

        Subscription::query()->forceCreate([
            'subscribable_type' => Event::class,
            'subscribable_id' => $this->futureEvent->id,
            'email' => 'ucastnik@example.com',
            'token' => Subscription::freshToken(),
            'confirmed_at' => now(),
        ]);

        $this->futureEvent->update(['start_at' => now()->addDays(30)]);

        Notification::assertSentOnDemandTimes(EventChanged::class, 1);
    }

    #[Test]
    public function digest_mail_lists_events_and_offers_unsubscribe(): void
    {
        $follower = $this->confirmedFollower();
        $event = $this->publishedEvent(['name' => 'Koncert v parku']);

        $mail = (new CanalNewEvents($this->canal, [$event->id], $follower))->toMail(new AnonymousNotifiable);
        $html = (string) $mail->render();

        $this->assertStringContainsString('Koncert v parku', $html);
        $this->assertStringContainsString('/odhlasenie/'.$follower->token, $html);
        $this->assertStringContainsString('Koncert v parku', $mail->subject);
    }
}
