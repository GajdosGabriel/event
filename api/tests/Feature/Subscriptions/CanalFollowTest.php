<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Subscription;
use App\Notifications\CanalFollowConfirmation;
use App\Notifications\CanalNewEvents;
use App\Support\SubmissionTicket;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * „Sledovať organizátora" — dvojité potvrdenie a súhrn nových akcií.
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

    private function ticket(int $ageSeconds = 5): string
    {
        return $this->travelTo(
            now()->subSeconds($ageSeconds),
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

    #[Test]
    public function guest_follow_waits_for_confirmation_from_the_email(): void
    {
        $this->app['auth']->forgetGuards();

        $this->follow('Fan@Example.com')->assertCreated()->assertJson(['status' => 'pending']);

        $subscription = Subscription::query()->where('subscribable_type', Canal::class)->firstOrFail();
        $this->assertSame('fan@example.com', $subscription->email);
        $this->assertNull($subscription->confirmed_at);

        Notification::assertSentOnDemand(CanalFollowConfirmation::class, function ($notification, $channels, AnonymousNotifiable $notifiable) {
            return $notifiable->routes['mail'] === 'fan@example.com';
        });

        // Klik v e-maile; druhý klik (prednačítanie klientom) nič nerozbije.
        $this->postJson("/api/subscriptions/{$subscription->token}/confirm")
            ->assertOk()
            ->assertJson(['type' => 'canal', 'confirmed' => true, 'event' => $this->canal->name]);
        $this->postJson("/api/subscriptions/{$subscription->token}/confirm")->assertOk()->assertJson(['confirmed' => true]);

        $this->assertNotNull($subscription->fresh()->confirmed_at);
    }

    #[Test]
    public function repeated_form_does_not_flood_the_mailbox(): void
    {
        $this->app['auth']->forgetGuards();

        $this->follow()->assertCreated();
        $this->follow()->assertCreated()->assertJson(['status' => 'pending']);

        $this->assertSame(1, Subscription::query()->where('subscribable_type', Canal::class)->count());
        Notification::assertSentOnDemandTimes(CanalFollowConfirmation::class, 1);
    }

    #[Test]
    public function logged_in_user_following_with_verified_account_address_needs_no_confirmation(): void
    {
        $this->user->forceFill(['email_verified_at' => now()])->save();

        $this->follow($this->user->email)->assertCreated()->assertJson(['status' => 'subscribed']);

        $this->assertNotNull(Subscription::query()->where('subscribable_type', Canal::class)->value('confirmed_at'));
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
    public function confirmed_followers_get_one_digest_of_new_upcoming_events(): void
    {
        $follower = $this->confirmedFollower();
        $unconfirmed = Subscription::query()->forceCreate([
            'subscribable_type' => Canal::class,
            'subscribable_id' => $this->canal->id,
            'email' => 'unconfirmed@example.com',
            'token' => Subscription::freshToken(),
            'confirmed_at' => null,
        ]);

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
        $this->assertNull($unconfirmed->fresh()->confirmed_at);

        // Druhý beh tie isté akcie neopakuje.
        $this->artisan('app:canal-followers-notify')->assertSuccessful();
        Notification::assertSentOnDemandTimes(CanalNewEvents::class, 1);

        $this->assertTrue($follower->fresh()->isActive());
    }

    #[Test]
    public function follower_gets_only_events_published_after_confirming(): void
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

        Notification::assertNothingSent();
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
