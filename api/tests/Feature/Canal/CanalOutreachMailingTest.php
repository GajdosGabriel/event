<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalRole;
use App\Enums\MailingTrigger;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalOutreach;
use App\Models\EmailSuppression;
use App\Models\Event;
use App\Models\User;
use App\Models\View;
use App\Services\Canals\CanalMembership;
use App\Services\Canals\CanalOutreachSender;
use App\Services\Canals\CanalStewardship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fáza 5: ponuka prevzatia cez Account Emailing (ACCOUNT_MAILING) — triggery
 * pred akciou, prah zobrazení a po akcii, strop postupnosti a jej zastavenie.
 */
class CanalOutreachMailingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();

        config()->set('account.url', 'http://account.test');
        config()->set('account.token', 'acc_test');
        config()->set('account.webhook_secret', 'whsec_test');
        config()->set('canals.outreach.view_milestones', [50, 200]);

        $this->admin = User::factory()->create(['last_login_at' => now(), 'last_activity' => now()]);
        $this->admin->assignRole('super-admin');

        // Až po admínovi – jeho účet by inak šiel do adresára pred Http::fake v teste.
        config()->set('account.mailing', true);
    }

    #[Test]
    public function upcoming_event_is_reported_with_contact_and_claim_link(): void
    {
        $this->fakeAccount(queued: 1);
        $canal = $this->imported('Farnosť Hájske', 'info@hajske.sk');
        $event = $this->event($canal, startsInDays: 8);

        $this->artisan('app:canal-outreach')->assertSuccessful();

        $outreach = CanalOutreach::sole();
        $this->assertSame(MailingTrigger::CanalEventUpcoming->value, $outreach->trigger);
        $this->assertSame(CanalOutreach::QUEUED, $outreach->status);
        $this->assertSame(1, $outreach->account_queued);
        $this->assertSame("canal:{$canal->id}:canal_event_upcoming:{$event->id}", $outreach->dedupe_key);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/mailing/contacts')
            && $r['contacts'][0]['email'] === 'info@hajske.sk'
            && filled($r['contacts'][0]['permission_note']));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/mailing/events')
            && $r['event'] === 'canal_event_upcoming'
            && $r['ref'] === ['type' => 'canal', 'id' => (string) $canal->id, 'label' => 'Kanál: Farnosť Hájske', 'url' => rtrim((string) config('app.frontend_url'), '/').'/admin/canals/'.$canal->id]
            && $r['variables']['dni_do_zaciatku'] === 8
            && str_contains($r['variables']['odkaz_prevzatia'], '/pozvanka/'.$outreach->invitation()->first()?->token));
    }

    #[Test]
    public function milestone_is_reported_once_per_threshold_and_sequence_is_capped(): void
    {
        $this->fakeAccount(queued: 1);
        config()->set('canals.outreach.min_gap_days', 0);
        config()->set('canals.outreach.max_per_cooldown', 2);

        $canal = $this->imported('Farnosť Hájske', 'info@hajske.sk');
        $far = $this->event($canal, startsInDays: 30, views: 60);

        $candidates = app(CanalOutreachSender::class)->candidates();
        $this->assertSame([MailingTrigger::CanalViewsMilestone], $candidates->pluck('trigger')->all());
        $this->assertSame(50, $candidates[0]['milestone']);

        $this->artisan('app:canal-outreach')->assertSuccessful();
        $this->artisan('app:canal-outreach')->assertSuccessful();
        $this->assertSame(1, CanalOutreach::count());

        // Ďalší trigger (pred akciou) ešte prejde, tretí už nie — strop 2.
        $this->event($canal, startsInDays: 5);
        $this->artisan('app:canal-outreach')->assertSuccessful();
        $this->assertSame(2, CanalOutreach::count());

        $this->views($far, 200);
        $this->assertCount(0, app(CanalOutreachSender::class)->candidates());
    }

    #[Test]
    public function min_gap_blocks_back_to_back_mails(): void
    {
        $this->fakeAccount(queued: 1);
        $canal = $this->imported('Farnosť Hájske', 'info@hajske.sk');
        $this->event($canal, startsInDays: 3);
        CanalOutreach::query()->create([
            'canal_id' => $canal->id, 'email' => 'info@hajske.sk', 'status' => CanalOutreach::SENT,
            'created_at' => now()->subDays(2),
        ]);

        $this->assertCount(0, app(CanalOutreachSender::class)->candidates());
    }

    #[Test]
    public function event_without_active_campaign_does_not_count_and_is_retried(): void
    {
        // Prvé hlásenie: kampaň ešte nie je aktívna; druhé: už áno.
        Http::fake([
            'account.test/api/v1/mailing/events' => Http::sequence()
                ->push(['data' => ['queued' => 0]])
                ->push(['data' => ['queued' => 1]]),
            'account.test/*' => Http::response(['data' => []]),
        ]);
        $canal = $this->imported('Farnosť Hájske', 'info@hajske.sk');
        $this->event($canal, startsInDays: 4);

        $this->artisan('app:canal-outreach')->assertSuccessful();
        $this->assertSame(CanalOutreach::NOT_QUEUED, CanalOutreach::sole()->status);
        $this->assertCount(1, app(CanalOutreachSender::class)->candidates());

        $this->artisan('app:canal-outreach')->assertSuccessful();
        $this->assertSame(CanalOutreach::QUEUED, CanalOutreach::sole()->status);
    }

    #[Test]
    public function finished_event_still_works_via_account(): void
    {
        $this->fakeAccount(queued: 1);
        $canal = $this->imported('Farnosť Hájske', 'info@hajske.sk');
        $event = $this->event($canal, startsInDays: -3, views: 2);
        $event->forceFill(['status' => ModelStatus::Archived->value])->save();

        $this->artisan('app:canal-outreach')->assertSuccessful();

        $this->assertSame(MailingTrigger::CanalEventFinished->value, CanalOutreach::sole()->trigger);
        Notification::assertNothingSent();
    }

    #[Test]
    public function claim_cancels_pending_mails_in_account(): void
    {
        $this->fakeAccount(queued: 1);
        $canal = $this->imported('Farnosť Hájske', 'info@hajske.sk');
        $user = User::factory()->create(['last_login_at' => now(), 'last_activity' => now()]);

        app(CanalStewardship::class)->complete($canal, $user);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/mailing/events/cancel')
            && $r['ref'] === ['type' => 'canal', 'id' => (string) $canal->id]);
    }

    #[Test]
    public function unsubscribe_and_bounce_webhooks_stop_the_sequence(): void
    {
        $this->fakeAccount(queued: 1);
        $canal = $this->imported('Farnosť Hájske', 'info@hajske.sk');
        $other = $this->imported('Divadlo', 'zla@divadlo.sk');
        $this->event($canal, startsInDays: 4);
        $this->event($other, startsInDays: 4);

        $this->artisan('app:canal-outreach')->assertSuccessful();
        $outreach = CanalOutreach::where('canal_id', $other->id)->sole();

        $this->webhook('mailing.unsubscribed', ['contact' => ['email' => 'Info@Hajske.sk']])->assertOk();
        $this->assertTrue(EmailSuppression::has('info@hajske.sk'));

        $this->webhook('mailing.delivery.bounced', ['delivery' => [
            'id' => 9, 'status' => 'bounced', 'email' => 'zla@divadlo.sk',
            'ref' => ['type' => 'canal', 'id' => (string) $other->id],
            'event' => 'canal_event_upcoming', 'dedupe_key' => $outreach->dedupe_key, 'error' => '550 no such user',
        ]])->assertOk();
        $this->assertSame(CanalOutreach::BOUNCED, $outreach->fresh()->status);
        $this->assertTrue(EmailSuppression::has('zla@divadlo.sk'));

        // Nové akcie oboch kanálov sa už nehlásia.
        config()->set('canals.outreach.min_gap_days', 0);
        $this->event($canal, startsInDays: 6);
        $this->event($other, startsInDays: 6);
        $this->assertCount(0, app(CanalOutreachSender::class)->candidates());
    }

    #[Test]
    public function canal_triggers_are_registered_with_their_variables(): void
    {
        $this->fakeAccount(queued: 0);

        $this->artisan('emailing:register-triggers')->assertSuccessful();

        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/api/v1/mailing/triggers')) {
                return false;
            }

            $byKey = collect($r['triggers'])->keyBy('key');

            return in_array('odkaz_prevzatia', $byKey['canal_event_upcoming']['variables'], true)
                && ! in_array('odkaz_prevzatia', $byKey['ticket_registered']['variables'], true);
        });
    }

    /* ------------------------------------------------------------- */

    private function fakeAccount(int $queued): void
    {
        Http::fake([
            'account.test/api/v1/mailing/contacts' => Http::response(['data' => ['added' => 1, 'updated' => 0]]),
            'account.test/api/v1/mailing/events/cancel' => Http::response(['data' => ['skipped' => 1]]),
            'account.test/api/v1/mailing/events' => Http::response(['data' => ['queued' => $queued]]),
            'account.test/*' => Http::response(['data' => []]),
        ]);
    }

    private function imported(string $name, ?string $email): Canal
    {
        $canal = Canal::factory()->create([
            'name' => $name,
            'registration_source' => RegistrationSource::IMPORT->value,
            'email' => $email,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
        ]);
        app(CanalMembership::class)->attach($canal, $this->admin, CanalRole::Owner);

        return $canal;
    }

    private function event(Canal $canal, int $startsInDays, int $views = 0): Event
    {
        $start = now()->addDays($startsInDays)->addHour();

        $event = Event::factory()->create([
            'canal_id' => $canal->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subDays(20),
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(3),
            'email' => null,
        ]);

        $this->views($event, $views);

        return $event;
    }

    private function views(Event $event, int $total): void
    {
        $have = View::query()->where('viewable_type', $event->getMorphClass())->where('viewable_id', $event->id)->count();

        for ($i = $have; $i < $total; $i++) {
            View::query()->create([
                'viewable_type' => $event->getMorphClass(),
                'viewable_id' => $event->id,
                'visitor_hash' => hash('sha256', $event->id.'v'.$i),
                'viewed_on' => now()->subDays(1)->toDateString(),
            ]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function webhook(string $event, array $data): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['id' => 'evt-'.uniqid(), 'event' => $event, 'data' => $data]);
        $timestamp = time();

        return $this->call('POST', '/api/webhooks/account', server: [
            'HTTP_X_ACCOUNTS_TIMESTAMP' => $timestamp,
            'HTTP_X_ACCOUNTS_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $body);
    }
}
