<?php

namespace Tests\Feature\Canal;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Notifications\CanalEventPublishedOffer;
use App\Services\Canals\CanalEmails;
use App\Services\Canals\CanalEventOffer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Import zakladá kanál a akciu skôr, než dohľadá e-mail. Ponuka prevzatia
 * (CanalEventOffer) preto odíde aj vtedy, keď kanál alebo akcia kontakt
 * dostanú neskôr, a jednorazovo príkazom `app:canal-event-offer-backfill`.
 */
class CanalContactOfferTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create();
        Notification::fake();
    }

    private function canalWithoutContact(array $attributes = []): Canal
    {
        return Canal::factory()->create([
            'registration_source' => RegistrationSource::IMPORT->value,
            'email' => null,
            'email_verified_at' => null,
            ...$attributes,
        ]);
    }

    private function publish(Canal $canal, array $attributes = []): Event
    {
        return Event::factory()->future()->create([
            'canal_id' => $canal->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'email' => null,
            ...$attributes,
        ]);
    }

    private function assertOfferTo(string $email, int $times = 1): void
    {
        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, $times);
        Notification::assertSentOnDemand(
            CanalEventPublishedOffer::class,
            fn ($n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === $email,
        );
    }

    #[Test]
    public function canal_email_added_after_the_event_sends_the_offer(): void
    {
        $canal = $this->canalWithoutContact();
        $event = $this->publish($canal);

        Notification::assertNothingSent();

        $canal->update(['email' => 'zbor@ecav.test']);

        $this->assertOfferTo('zbor@ecav.test');
        $this->assertNotNull($event->fresh()->takeover_notified_at);
        // Kanál zo včerajšieho importu: e-mail hovorí aj o vytvorení kanála.
        Notification::assertSentOnDemand(CanalEventPublishedOffer::class, fn (CanalEventPublishedOffer $n) => $n->isNewCanal());
    }

    #[Test]
    public function email_discovered_on_the_website_sends_the_offer(): void
    {
        $canal = $this->canalWithoutContact();
        $this->publish($canal);

        app(CanalEmails::class)->discover($canal, [['email' => 'farar@zbor.test', 'found_on' => 'https://zbor.test/kontakt']], 'test');

        $this->assertSame('farar@zbor.test', $canal->fresh()->email);
        $this->assertOfferTo('farar@zbor.test');
    }

    #[Test]
    public function event_email_added_later_sends_the_offer(): void
    {
        $canal = $this->canalWithoutContact();
        $event = $this->publish($canal);

        $event->update(['email' => 'organizator@akcia.test']);

        $this->assertOfferTo('organizator@akcia.test');
    }

    #[Test]
    public function offer_goes_with_the_nearest_upcoming_event(): void
    {
        $canal = $this->canalWithoutContact();
        $later = $this->publish($canal, ['start_at' => now()->addDays(20), 'end_at' => now()->addDays(20)->addHours(2)]);
        $sooner = $this->publish($canal, ['start_at' => now()->addDays(3), 'end_at' => now()->addDays(3)->addHours(2)]);
        $this->publish($canal, ['start_at' => now()->subDays(3), 'end_at' => now()->subDays(3)->addHours(2)]);
        $this->publish($canal, ['status' => ModelStatus::Draft->value, 'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHour()]);

        $canal->update(['email' => 'zbor@ecav.test']);

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 1);
        $this->assertNotNull($sooner->fresh()->takeover_notified_at);
        $this->assertNull($later->fresh()->takeover_notified_at);
    }

    #[Test]
    public function changed_contact_does_not_repeat_a_recent_offer(): void
    {
        $canal = $this->canalWithoutContact(['email' => 'stary@zbor.test', 'created_at' => now()->subMonth()]);
        $this->publish($canal);
        $this->publish($canal);

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 1);

        $this->travel(2)->days();
        $canal->update(['email' => 'novy@zbor.test']);

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 1);

        // Po tichej lehote nový kontakt ponuku k ďalšej akcii dostane.
        $this->travel(CanalEventOffer::CONTACT_CHANGE_QUIET_DAYS)->days();
        $this->publish($canal, ['start_at' => now()->addDays(5), 'end_at' => now()->addDays(5)->addHour()]);
        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 2);
    }

    #[Test]
    public function contact_change_does_not_mail_managed_or_switched_off_canals_or_canals_without_events(): void
    {
        $off = $this->canalWithoutContact(['event_offer_disabled' => true]);
        $this->publish($off);
        $off->update(['email' => 'vypnuty@kanal.test']);

        $empty = $this->canalWithoutContact();
        $empty->update(['email' => 'bez-akcii@kanal.test']);

        $past = $this->canalWithoutContact();
        $this->publish($past, ['start_at' => now()->subDays(2), 'end_at' => now()->subDays(2)->addHour()]);
        $past->update(['email' => 'po-akcii@kanal.test']);

        Notification::assertNothingSent();
    }

    #[Test]
    public function backfill_mails_only_canals_that_never_got_an_offer(): void
    {
        // Kanál, ktorému e-mail pribudol mimo Eloquentu (stav pred opravou).
        $missed = $this->canalWithoutContact();
        $event = $this->publish($missed);
        Canal::query()->whereKey($missed->id)->toBase()->update(['email' => 'zmeskany@zbor.test']);

        $offered = $this->canalWithoutContact(['email' => 'uz-dostal@zbor.test']);
        $this->publish($offered);
        $this->publish($offered);

        $none = $this->canalWithoutContact();
        $this->publish($none);

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 1);

        $this->artisan('app:canal-event-offer-backfill', ['--dry-run' => true])
            ->expectsOutputToContain('zmeskany@zbor.test')
            ->expectsOutputToContain('Odišlo by: 1')
            ->assertSuccessful();

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 1);
        $this->assertNull($event->fresh()->takeover_notified_at);

        $this->artisan('app:canal-event-offer-backfill')
            ->expectsOutputToContain('Odoslaných: 1')
            ->assertSuccessful();

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 2);
        $this->assertNotNull($event->fresh()->takeover_notified_at);

        // Druhé spustenie už nikomu nepíše.
        $this->artisan('app:canal-event-offer-backfill')
            ->expectsOutputToContain('Odoslaných: 0')
            ->assertSuccessful();
    }
}
