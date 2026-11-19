<?php

namespace Tests\Feature\Questions;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\EmailSuppression;
use App\Models\User;
use App\Notifications\QuestionRelayed;
use App\Support\SubmissionTicket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestSupport\EventSetupTest;

/**
 * Otázka na podujatie importovaného kanála sa doručí e-mailom (QuestionRelay).
 * Strážia sa hlavne brzdy: len s kontaktom (neoverenému anonymne), len prihlásený s overeným účtom
 * a žiadne ukladanie ani zakladanie nástenky.
 */
class QuestionRelayTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();
        Notification::fake();
        Cache::flush();

        // Bez e-mailu podujatia — ten by inak nastúpil ako záložný kontakt.
        $this->futureEvent->update(['status' => ModelStatus::Published, 'email' => null]);
        // Fixtúra je osobný kanál používateľa s overenou adresou účtu — test
        // potrebuje importovaný kanál, ktorého overenie príde s jeho kontaktom.
        // Ponuka prevzatia (CanalEventOffer) k tejto akcii už odišla — nový kontakt
        // importovaného kanála by ju inak poslal a test rieši len otázky.
        $this->futureEvent->forceFill(['takeover_notified_at' => now()])->saveQuietly();
        $this->futureEvent->canal->emails()->delete();
        $this->futureEvent->canal->forceFill(['email_verified_at' => null])->saveQuietly();
        $this->futureEvent->canal->forceFill([
            // Pevné meno: náhodné z factory môže vyzerať ako doména, a to je zberný kanál.
            'name' => 'Organizátor',
            'registration_source' => RegistrationSource::IMPORT,
            'claimed_at' => null,
            'email' => 'Info@Organizator.sk',
            'email_verified_at' => now(),
        ])->save();
    }

    private function ask(string $body = 'Je vstup naozaj zadarmo?'): TestResponse
    {
        $ticket = $this->travelTo(
            now()->subSeconds(10),
            fn () => SubmissionTicket::issue('question:event:'.$this->futureEvent->id),
        );

        return $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => $body,
            'ticket' => $ticket,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        Role::findOrCreate('super-admin', 'web');
        $admin->assignRole('super-admin');

        return $admin;
    }

    #[Test]
    public function the_button_is_offered_for_any_contact_with_a_warning_when_unverified(): void
    {
        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()->assertJsonPath('relay', true)->assertJsonPath('unconfirmed', false);

        $this->futureEvent->canal->forceFill(['email_verified_at' => null])->save();

        // Neoverená adresa: otázka odíde, návštevník však vie, že odpoveď nemusí prísť.
        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()->assertJsonPath('relay', true)->assertJsonPath('unconfirmed', true);
    }

    #[Test]
    public function without_any_contact_there_is_nothing_to_ask(): void
    {
        $this->futureEvent->canal->forceFill(['email' => null, 'email_verified_at' => null])->save();
        $this->actingAs($this->user, 'sanctum');

        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()->assertJsonPath('available', false);
        $this->ask()->assertStatus(422);

        Notification::assertNothingSent();
    }

    #[Test]
    public function the_events_own_e_mail_stands_in_for_a_missing_canal_contact(): void
    {
        $this->futureEvent->update(['email' => 'akcia@organizator.sk']);
        $this->futureEvent->canal->forceFill(['email' => null, 'email_verified_at' => null])->save();
        $this->actingAs($this->user, 'sanctum');

        $this->ask()->assertCreated();

        Notification::assertSentOnDemand(QuestionRelayed::class,
            fn (QuestionRelayed $n, array $channels, $notifiable) => $notifiable->routes['mail'] === 'akcia@organizator.sk');
    }

    #[Test]
    public function an_unverified_contact_sees_neither_the_e_mail_nor_the_full_name(): void
    {
        $this->futureEvent->canal->forceFill(['email_verified_at' => null])->save();
        $sender = User::factory()->create(['email' => 'pisatel@example.sk', 'email_verified_at' => now()]);
        $admin = $this->admin();
        $this->actingAs($sender, 'sanctum');

        $this->ask()->assertCreated();

        Notification::assertSentOnDemand(QuestionRelayed::class, function (QuestionRelayed $n, array $channels, $notifiable) use ($sender) {
            $mail = $n->toMail($notifiable);
            $text = implode("\n", [...$mail->introLines, ...$mail->outroLines]);

            return $n->audience === QuestionRelayed::ORGANIZER
                && $mail->replyTo === []
                && ! str_contains($text, 'pisatel@example.sk')
                && str_contains($text, $n::shortName($sender->displayName()));
        });

        // Správca portálu vidí pisateľa celého.
        Notification::assertSentTo($admin, QuestionRelayed::class, fn (QuestionRelayed $n) => str_contains(
            implode("\n", $n->toMail($admin)->introLines), 'pisatel@example.sk',
        ));
    }

    #[Test]
    public function a_verified_contact_gets_the_reply_to(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $this->ask()->assertCreated();

        Notification::assertSentOnDemand(QuestionRelayed::class, fn (QuestionRelayed $n, array $channels, $notifiable) => $n->audience === QuestionRelayed::ORGANIZER
            && $n->toMail($notifiable)->replyTo !== []);
    }

    #[Test]
    public function the_short_name_keeps_only_the_initial_of_the_surname(): void
    {
        $this->assertSame('Ján N.', QuestionRelayed::shortName('Ján Novák'));
        $this->assertSame('Mária K.', QuestionRelayed::shortName('  Mária Anna  kováčová '));
        $this->assertSame('g•••o', QuestionRelayed::shortName('g•••o'));
    }

    #[Test]
    public function an_ended_event_takes_no_more_questions(): void
    {
        $this->futureEvent->update(['start_at' => now()->subDays(3), 'end_at' => now()->subDays(2)]);
        $this->actingAs($this->user, 'sanctum');

        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()->assertJsonPath('available', false);
        $this->ask()->assertStatus(422);
    }

    #[Test]
    public function the_public_detail_warns_only_when_the_organizer_is_unconfirmed(): void
    {
        $flag = fn () => $this->getJson("/api/events/{$this->futureEvent->id}")
            ->assertOk()->json('organizer_unconfirmed');

        $this->assertFalse($flag());

        $this->futureEvent->canal->forceFill(['email_verified_at' => null])->save();
        $this->assertTrue($flag());

        $this->futureEvent->canal->forceFill(['claimed_at' => now()])->save();
        $this->assertFalse($flag());
    }

    #[Test]
    public function an_unsubscribed_or_claimed_channel_gets_no_relay(): void
    {
        EmailSuppression::add('info@organizator.sk', 'bounced');
        $this->getJson("/api/events/{$this->futureEvent->id}/questions")->assertJsonPath('available', false);

        EmailSuppression::query()->delete();
        $this->futureEvent->canal->forceFill(['claimed_at' => now()])->save();
        $this->getJson("/api/events/{$this->futureEvent->id}/questions")->assertJsonPath('available', false);
    }

    #[Test]
    public function a_guest_cannot_relay(): void
    {
        $this->ask()->assertUnauthorized();

        Notification::assertNothingSent();
    }

    #[Test]
    public function an_unverified_account_cannot_relay(): void
    {
        $this->actingAs(User::factory()->unverified()->create(), 'sanctum');

        $this->ask()->assertForbidden();
    }

    #[Test]
    public function a_verified_question_reaches_the_admin_too(): void
    {
        $admin = $this->admin();
        $this->actingAs($this->user, 'sanctum');

        $this->ask()->assertCreated()->assertJsonPath('relayed', true);

        Notification::assertSentTo($admin, QuestionRelayed::class,
            fn (QuestionRelayed $n) => $n->audience === QuestionRelayed::ADMIN && $n->askerEmail === $this->user->email);

        // Nič sa neukladá a nástenka sa nezakladá.
        $this->assertNull($this->futureEvent->questionBoard()->first());
    }

    #[Test]
    public function the_organizer_mail_is_sent_to_the_canal_contact(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $this->ask()->assertCreated();

        Notification::assertSentOnDemand(QuestionRelayed::class,
            fn (QuestionRelayed $n, array $channels, $notifiable) => $notifiable->routes['mail'] === 'info@organizator.sk'
                && $n->audience === QuestionRelayed::ORGANIZER);
    }

    #[Test]
    public function the_same_question_twice_is_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $this->ask('Je vstup naozaj zadarmo?')->assertCreated();
        $this->ask('Je vstup naozaj zadarmo?')->assertStatus(422);
    }

    #[Test]
    public function one_person_cannot_flood_a_mailbox(): void
    {
        $this->actingAs($this->user, 'sanctum');

        foreach (range(1, 5) as $i) {
            $this->ask("Otázka číslo $i, dlhšia ako tri znaky")->assertCreated();
        }

        $this->ask('Šiesta otázka, dlhšia ako tri znaky')->assertStatus(429);
    }
}
