<?php

namespace Tests\Feature\Questions;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\EmailSuppression;
use App\Models\SystemLog;
use App\Models\User;
use App\Notifications\QuestionRelayed;
use App\Services\Canals\CanalEmails;
use App\Support\SubmissionTicket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestSupport\EventSetupTest;

/**
 * Otázka na podujatie importovaného kanála sa doručí e-mailom (QuestionRelay).
 * Strážia sa hlavne brzdy: len overená adresa, len prihlásený s overeným účtom
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
        $this->futureEvent->canal->forceFill([
            // Pevné meno: náhodné z factory môže vyzerať ako doména, a to je zberný kanál.
            'name' => 'Organizátor',
            'registration_source' => RegistrationSource::IMPORT,
            'claimed_at' => null,
            'email' => 'Info@Organizator.sk',
            'email_verified_at' => now(),
        ])->save();
    }

    private function ask(string $body = 'Je vstup naozaj zadarmo?'): \Illuminate\Testing\TestResponse
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
    public function the_button_is_offered_only_for_a_verified_contact(): void
    {
        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()->assertJsonPath('available', true)->assertJsonPath('relay', true);

        $this->futureEvent->canal->forceFill(['email_verified_at' => null])->save();

        // Neoverená adresa: nevieme, či niekto odpovie.
        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()->assertJsonPath('available', false);
    }

    #[Test]
    public function an_unverified_contact_cannot_be_reached_by_posting_directly(): void
    {
        $this->futureEvent->canal->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->user, 'sanctum');

        $this->ask()->assertStatus(422);

        Notification::assertNothingSent();
    }

    #[Test]
    public function the_events_own_e_mail_does_not_stand_in_for_a_verified_contact(): void
    {
        $this->futureEvent->update(['email' => 'akcia@organizator.sk']);
        $this->futureEvent->canal->forceFill(['email_verified_at' => null])->save();

        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()->assertJsonPath('available', false);
    }

    #[Test]
    public function a_reply_from_the_contact_opens_the_questions(): void
    {
        $this->futureEvent->canal->forceFill(['email_verified_at' => null])->save();

        app(CanalEmails::class)->recordReply('info@organizator.sk');

        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()->assertJsonPath('relay', true);
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
    public function a_verified_question_reaches_the_admin_and_the_organizer_is_only_simulated(): void
    {
        config(['canals.simulate_takeover_mail' => true]);
        $admin = $this->admin();
        $this->actingAs($this->user, 'sanctum');

        $this->ask()->assertCreated()->assertJsonPath('relayed', true);

        Notification::assertSentTo($admin, QuestionRelayed::class,
            fn (QuestionRelayed $n) => $n->audience === QuestionRelayed::ADMIN && $n->askerEmail === $this->user->email);

        // Simulácia: organizátorovi nič neodišlo, ale zapísalo sa to do denníka.
        Notification::assertNotSentTo(new \Illuminate\Notifications\AnonymousNotifiable, QuestionRelayed::class);
        $log = SystemLog::where('event', 'mail.simulated')->sole();
        $this->assertSame('info@organizator.sk', $log->recipient);

        // Nič sa neukladá a nástenka sa nezakladá.
        $this->assertNull($this->futureEvent->questionBoard()->first());
    }

    #[Test]
    public function the_organizer_mail_is_sent_for_real_once_simulation_is_off(): void
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
