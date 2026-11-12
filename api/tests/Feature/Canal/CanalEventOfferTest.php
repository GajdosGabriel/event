<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\EmailSuppression;
use App\Models\Event;
use App\Models\User;
use App\Notifications\CanalEventPublishedOffer;
use App\Services\Canals\CanalMembership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Zverejnená akcia nespravovaného kanála → technický e-mail na jeho kontakt
 * s ponukou prevzatia (CanalEventOffer), bez ohľadu na overenie adresy.
 */
class CanalEventOfferTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // EventFactory berie autora z existujúcich používateľov.
        User::factory()->create();
        Notification::fake();
        config(['canals.simulate_takeover_mail' => false]);
    }

    private function importedCanal(array $attributes = []): Canal
    {
        return Canal::factory()->create([
            'registration_source' => RegistrationSource::IMPORT->value,
            'email' => 'kontakt@farnost.test',
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

    #[Test]
    public function every_published_event_of_an_unmanaged_canal_mails_its_unverified_contact(): void
    {
        $canal = $this->importedCanal(['created_at' => now()->subMonth()]);

        $this->publish($canal);
        $this->publish($canal);

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 2);
        Notification::assertSentOnDemand(
            CanalEventPublishedOffer::class,
            fn (CanalEventPublishedOffer $n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'kontakt@farnost.test' && ! $n->isNewCanal(),
        );
    }

    #[Test]
    public function first_event_of_a_new_canal_says_the_canal_was_created(): void
    {
        $canal = $this->importedCanal();

        $first = $this->publish($canal);
        $this->publish($canal);

        $sent = collect();
        Notification::assertSentOnDemand(CanalEventPublishedOffer::class, function (CanalEventPublishedOffer $n) use ($sent) {
            $sent->push($n->isNewCanal());

            return true;
        });

        $this->assertSame([true, false], $sent->all());
        $this->assertNotNull($first->fresh()->takeover_notified_at);
    }

    #[Test]
    public function republishing_does_not_repeat_the_mail(): void
    {
        $event = $this->publish($this->importedCanal());

        $event->update(['status' => ModelStatus::Draft->value]);
        $event->update(['status' => ModelStatus::Published->value]);

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 1);
    }

    #[Test]
    public function draft_published_later_mails_on_publication(): void
    {
        $event = $this->publish($this->importedCanal(), ['status' => ModelStatus::Draft->value, 'published_at' => null]);

        Notification::assertNothingSentTo(new AnonymousNotifiable);

        $event->update(['status' => ModelStatus::Published->value, 'published_at' => now()]);

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 1);
    }

    #[Test]
    public function suppressed_contact_falls_back_to_event_email_or_gets_nothing(): void
    {
        EmailSuppression::add('kontakt@farnost.test', 'unsubscribed');
        $canal = $this->importedCanal();

        $this->publish($canal);
        Notification::assertNotSentTo(new AnonymousNotifiable, CanalEventPublishedOffer::class);

        $this->publish($canal, ['email' => 'akcia@farnost.test']);
        Notification::assertSentOnDemand(
            CanalEventPublishedOffer::class,
            fn ($n, $c, AnonymousNotifiable $to) => $to->routes['mail'] === 'akcia@farnost.test',
        );
    }

    #[Test]
    public function managed_canal_and_past_event_get_nothing(): void
    {
        $claimed = $this->importedCanal(['claimed_at' => now()]);
        $this->publish($claimed);

        $self = $this->importedCanal(['registration_source' => RegistrationSource::SELF->value]);
        $this->publish($self);

        $this->publish($this->importedCanal(), ['start_at' => now()->subDays(3), 'end_at' => now()->subDays(2)]);

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 0);
    }

    #[Test]
    public function import_sources_with_many_events_get_nothing(): void
    {
        $this->publish($this->importedCanal(['email' => 'reporter@tkkbs.sk']));
        $this->publish($this->importedCanal(['email' => 'media@ecav.sk']));
        $this->publish($this->importedCanal(['email' => 'info@vyveska.sk']));
        $this->publish($this->importedCanal(['email' => 'zbor@modra.ecav.sk']));

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 0);

        // Podobná, ale iná doména sa nevylučuje.
        $this->publish($this->importedCanal(['email' => 'sekretariat@vdecav.sk']));

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 1);
    }

    #[Test]
    public function contact_already_in_the_team_gets_nothing(): void
    {
        $canal = $this->importedCanal();
        $member = User::factory()->create(['email' => 'kontakt@farnost.test']);
        app(CanalMembership::class)->attach($canal, $member, CanalRole::Editor);

        $this->publish($canal);

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 0);
    }

    #[Test]
    public function simulation_switch_keeps_the_mail_in_the_log(): void
    {
        config(['canals.simulate_takeover_mail' => true]);

        $this->publish($this->importedCanal());

        Notification::assertSentOnDemandTimes(CanalEventPublishedOffer::class, 0);
    }

    #[Test]
    public function mail_offers_takeover_with_unsubscribe(): void
    {
        $canal = $this->importedCanal(['name' => 'Farnosť Lamač']);
        $this->publish($canal, ['name' => 'Adventný koncert']);

        Notification::assertSentOnDemand(CanalEventPublishedOffer::class, function (CanalEventPublishedOffer $n, $c, $to) {
            $mail = $n->toMail($to);
            $text = implode("\n", [...$mail->introLines, ...$mail->outroLines]);

            $this->assertStringContainsString('Vytvorili sme váš kanál', (string) $mail->subject);
            $this->assertStringContainsString('Farnosť Lamač', $text);
            $this->assertStringContainsString('/pozvanka/', (string) $mail->actionUrl);
            $this->assertStringContainsString('odhlásiť', $text);

            return true;
        });
    }
}
