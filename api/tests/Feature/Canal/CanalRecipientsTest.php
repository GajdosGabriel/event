<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalNotificationTopic;
use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalNotificationSetting;
use App\Models\Event;
use App\Models\SystemLog;
use App\Models\User;
use App\Notifications\EventSignupOrganizerNotice;
use App\Notifications\MessageReceived;
use App\Services\Canals\CanalMembership;
use App\Services\Canals\CanalRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fáza 2: kto z tímu kanála dostane akú notifikáciu (CanalRecipients).
 * Doterajší adresáti dostávajú e-mail normálne (ak si tému nevypli), noví
 * adresáti sú kým platí `canals.simulate_team_notifications` len v denníku.
 */
class CanalRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $editor;

    private User $checkin;

    private Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();

        $this->canal = Canal::factory()->create([
            'name' => 'Divadlo',
            'registration_source' => RegistrationSource::SELF->value,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);
        $this->owner = $this->user('owner@divadlo.test');
        $this->editor = $this->user('editor@divadlo.test');
        $this->checkin = $this->user('vstup@divadlo.test');

        $membership = app(CanalMembership::class);
        $membership->attach($this->canal, $this->owner, CanalRole::Owner);
        $membership->attach($this->canal, $this->editor, CanalRole::Editor);
        $membership->attach($this->canal, $this->checkin, CanalRole::Checkin);

        SystemLog::query()->delete();
    }

    #[Test]
    public function role_defaults_decide_who_is_subscribed(): void
    {
        $recipients = app(CanalRecipients::class);

        $this->assertSame([$this->owner->id, $this->editor->id],
            $recipients->subscribers($this->canal, CanalNotificationTopic::Signups)->pluck('id')->all());
        $this->assertSame([$this->owner->id],
            $recipients->subscribers($this->canal, CanalNotificationTopic::Messages)->pluck('id')->all());
        $this->assertSame($this->owner->id, $this->canal->messageRecipient()?->id);

        $matrix = $recipients->matrix($this->canal);
        $this->assertSame(['signups' => false, 'messages' => false, 'questions' => false, 'reviews' => false], $matrix[$this->checkin->id]);
    }

    #[Test]
    public function signup_goes_to_the_owner_and_only_simulated_to_the_editor(): void
    {
        $this->signup($this->event());

        Notification::assertSentTo($this->owner, EventSignupOrganizerNotice::class);
        Notification::assertNotSentTo($this->editor, EventSignupOrganizerNotice::class);
        Notification::assertNotSentTo($this->checkin, EventSignupOrganizerNotice::class);

        $simulated = SystemLog::where('event', 'mail.simulated')->get();
        $this->assertSame(['editor@divadlo.test'], $simulated->pluck('recipient')->all());
        $this->assertSame(EventSignupOrganizerNotice::class, $simulated[0]->context['class']);
    }

    #[Test]
    public function owner_who_muted_signups_gets_nothing_and_it_is_logged(): void
    {
        app(CanalRecipients::class)->set($this->canal, $this->owner, CanalNotificationTopic::Signups, false);

        $this->signup($this->event());

        Notification::assertNotSentTo($this->owner, EventSignupOrganizerNotice::class);
        $skipped = SystemLog::where('event', 'mail.skipped')->sole();
        $this->assertSame('owner@divadlo.test', $skipped->recipient);
        $this->assertSame('muted', $skipped->context['reason']);
    }

    #[Test]
    public function message_stays_with_the_owner_and_the_opted_in_editor_gets_a_simulated_copy(): void
    {
        app(CanalRecipients::class)->set($this->canal, $this->editor, CanalNotificationTopic::Messages, true);
        $visitor = $this->user('navstevnik@example.sk');

        $this->actingAs($visitor, 'sanctum')
            ->postJson('/api/messages', ['target_type' => 'canal', 'target_id' => $this->canal->id, 'body' => 'Dobrý deň, máte ešte lístky?'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', ['recipient_user_id' => $this->owner->id]);
        Notification::assertSentOnDemand(MessageReceived::class,
            fn ($n, $c, AnonymousNotifiable $to) => $to->routes['mail'] === 'owner@divadlo.test');
        Notification::assertNotSentTo($this->editor, MessageReceived::class);
        $this->assertSame(['editor@divadlo.test'], SystemLog::where('event', 'mail.simulated')->pluck('recipient')->all());
    }

    #[Test]
    public function while_simulating_only_an_owner_can_be_the_primary_message_recipient(): void
    {
        $recipients = app(CanalRecipients::class);
        $recipients->set($this->canal, $this->owner, CanalNotificationTopic::Messages, false);
        $recipients->set($this->canal, $this->editor, CanalNotificationTopic::Messages, true);

        $this->assertNull($this->canal->messageRecipient());

        config(['canals.simulate_team_notifications' => false]);
        $this->assertSame($this->editor->id, $this->canal->messageRecipient()?->id);
    }

    #[Test]
    public function without_simulation_the_editor_gets_the_real_notification(): void
    {
        config(['canals.simulate_team_notifications' => false]);

        $this->signup($this->event());

        Notification::assertSentTo($this->owner, EventSignupOrganizerNotice::class);
        Notification::assertSentTo($this->editor, EventSignupOrganizerNotice::class);
        $this->assertFalse(SystemLog::where('event', 'mail.simulated')->exists());
    }

    #[Test]
    public function members_change_their_own_settings_and_owners_change_anyones(): void
    {
        $url = fn (User $u) => "/api/dashboard/canals/{$this->canal->id}/team/{$u->id}/notifications";

        // Editor sebe áno, cudziemu nie.
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($url($this->editor), ['topic' => 'questions', 'enabled' => false])
            ->assertOk()
            ->assertJsonPath('meta.notification_topics.0.value', 'signups');
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($url($this->checkin), ['topic' => 'signups', 'enabled' => true])
            ->assertForbidden();

        // Vlastník komukoľvek.
        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson($url($this->checkin), ['topic' => 'signups', 'enabled' => true])
            ->assertOk();
        $member = collect($response->json('data.members'))->firstWhere('id', $this->checkin->id);
        $this->assertTrue($member['notifications']['signups']);

        $this->assertSame(2, CanalNotificationSetting::count());
        $this->assertSame(2, SystemLog::where('event', 'canals.notifications_changed')->count());

        // Návrat na predvoľbu roly riadok zmaže.
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($url($this->editor), ['topic' => 'questions', 'enabled' => true])
            ->assertOk();
        $this->assertSame(1, CanalNotificationSetting::count());

        // Odchod z tímu zmaže aj nastavenia.
        app(CanalMembership::class)->detach($this->canal, $this->checkin);
        $this->assertSame(0, CanalNotificationSetting::count());
    }

    #[Test]
    public function event_author_always_gets_mail_about_their_own_event(): void
    {
        // Editor má kontroly obsahu predvolene vypnuté — za kanál mu nechodia,
        // k vlastnému podujatiu áno.
        $event = $this->event();
        $event->forceFill(['user_id' => $this->editor->id])->save();
        $recipients = app(CanalRecipients::class);
        $notice = new MessageReceived(new \App\Models\Message, 'x', 'x@example.sk');

        $this->assertTrue($recipients->mayNotify($this->canal, $this->editor, CanalNotificationTopic::Reviews, $notice, $event));
        $this->assertFalse($recipients->mayNotify($this->canal, $this->editor, CanalNotificationTopic::Reviews, $notice, $this->canal));
        $this->assertFalse($recipients->mayNotify($this->canal, $this->checkin, CanalNotificationTopic::Reviews, $notice, $event));
    }

    #[Test]
    public function owner_flag_counts_even_when_the_role_column_lags_behind(): void
    {
        $legacy = $this->user('stary@divadlo.test');
        $this->canal->users()->attach($legacy->id, ['is_owner' => true, 'role' => 'editor', 'status' => ModelStatus::Published->value]);

        $this->assertContains($legacy->id,
            app(CanalRecipients::class)->subscribers($this->canal, CanalNotificationTopic::Reviews)->pluck('id')->all());
    }

    #[Test]
    public function reviews_follow_their_own_topic_not_messages(): void
    {
        app(CanalRecipients::class)->set($this->canal, $this->owner, CanalNotificationTopic::Messages, false);

        $this->assertNull($this->canal->messageRecipient());
        $this->assertSame($this->owner->id, $this->canal->attributeIssueRecipient()?->id);
        $this->assertSame($this->owner->id, $this->canal->contentReviewRecipient()?->id);
    }

    #[Test]
    public function other_members_do_not_see_foreign_settings(): void
    {
        $response = $this->actingAs($this->editor, 'sanctum')
            ->getJson("/api/dashboard/canals/{$this->canal->id}/team")
            ->assertOk();

        $members = collect($response->json('data.members'))->keyBy('id');
        $this->assertNotNull($members[$this->editor->id]['notifications']);
        $this->assertNull($members[$this->owner->id]['notifications']);
    }

    private function event(): Event
    {
        return Event::factory()->future()->create([
            'canal_id' => $this->canal->id,
            // Autor mimo tímu — inak by platilo pravidlo „k vlastnému podujatiu vždy".
            'user_id' => $this->user('autor-'.uniqid().'@example.sk')->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subDay(),
            'registration_deadline_at' => null,
            'price_amount' => null,
        ]);
    }

    private function signup(Event $event): void
    {
        $this->actingAs($this->user('ucastnik@example.sk'), 'sanctum')
            ->postJson("/api/events/{$event->id}/signup")
            ->assertOk();
    }

    /** Pevné časy prihlásenia — factory ich inak losuje a môže trafiť hodinu, ktorú preskočil prechod na letný čas. */
    private function user(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
            'last_login_at' => now(),
            'last_activity' => now(),
        ]);
    }
}
