<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalEmailStatus;
use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalEmail;
use App\Models\CanalEmailSearch;
use App\Models\EmailSuppression;
use App\Models\Event;
use App\Models\ProfileEnrichment;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Canals\CanalEmailFinder;
use App\Services\Canals\CanalEmails;
use App\Services\Canals\CanalWebsitePages;
use App\Services\Profiles\ProfileResearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vrátený e-mail → hľadanie náhradnej adresy (CanalEmailFinder): podujatia
 * kanála, jeho web, po vrátení aj AI — a dočasné vrátenie, ktoré adresu
 * vyradí až po opakovaní.
 */
class CanalEmailFinderTest extends TestCase
{
    use RefreshDatabase;

    private CanalEmails $emails;

    /** @var array<string, string> URL → HTML pre podvrhnutý web */
    private array $pages = [];

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();
        Http::fake(['*' => Http::response([])]);
        config(['openai.api_key' => '']);

        $test = $this;
        $this->app->instance(CanalWebsitePages::class, new class($test) extends CanalWebsitePages
        {
            public function __construct(private readonly CanalEmailFinderTest $test) {}

            public function fetch(string $url): ?string
            {
                return $this->test->page($url);
            }
        });

        $this->emails = app(CanalEmails::class);
    }

    public function page(string $url): ?string
    {
        return $this->pages[rtrim($url, '/')] ?? null;
    }

    #[Test]
    public function a_single_soft_bounce_keeps_the_address(): void
    {
        $canal = $this->canal('plna@farnost.test');

        $this->emails->recordBounce('plna@farnost.test', CanalEmail::BOUNCE_SOFT, 'mailbox full');
        $this->emails->recordBounce('plna@farnost.test', CanalEmail::BOUNCE_SOFT, 'mailbox full');

        $row = $canal->emails()->sole();
        $this->assertSame(CanalEmailStatus::Unverified, $row->status);
        $this->assertSame(2, $row->bounce_count);
        $this->assertSame('plna@farnost.test', $canal->fresh()->email);

        $this->emails->recordBounce('plna@farnost.test', CanalEmail::BOUNCE_SOFT, 'mailbox full');

        $this->assertSame(CanalEmailStatus::Undeliverable, $row->fresh()->status);
        $this->assertNull($canal->fresh()->email);
        $this->assertFalse(EmailSuppression::has('plna@farnost.test'));
    }

    #[Test]
    public function an_old_soft_bounce_does_not_count(): void
    {
        $canal = $this->canal('plna@farnost.test');
        $canal->emails()->update(['bounce_type' => 'soft', 'bounce_count' => 2, 'bounced_at' => now()->subDays(40)]);

        $this->emails->recordBounce('plna@farnost.test', CanalEmail::BOUNCE_SOFT);

        $row = $canal->emails()->sole();
        $this->assertSame(1, $row->bounce_count);
        $this->assertTrue($row->isUsable());
    }

    #[Test]
    public function bounce_of_the_last_address_finds_a_new_one_in_the_canal_events(): void
    {
        $canal = $this->canal('zla@farnost.test');
        $this->event($canal, 'Fara@Farnost.test');
        $this->event($canal, 'fara@farnost.test');
        $this->event($canal, 'zla@farnost.test');

        $this->emails->recordBounce('zla@farnost.test', CanalEmail::BOUNCE_HARD, '550 5.1.1');

        $this->assertNotNull(CanalEmailSearch::where('canal_id', $canal->id)->value('requested_at'));

        $this->artisan('app:canal-emails-find')->assertSuccessful();

        $canal->refresh();
        $this->assertSame('fara@farnost.test', $canal->email);
        $found = $canal->emails()->where('email', 'fara@farnost.test')->sole();
        $this->assertSame(CanalEmail::SOURCE_DISCOVERED, $found->source);
        $this->assertSame(CanalEmailStatus::Unverified, $found->status);
        $this->assertStringStartsWith('podujatie #', $found->found_on);
        // Vrátená adresa sa späť nedostane, ani keď je v podujatí.
        $this->assertSame(CanalEmailStatus::Undeliverable, $canal->emails()->where('email', 'zla@farnost.test')->sole()->status);

        $search = CanalEmailSearch::where('canal_id', $canal->id)->sole();
        $this->assertSame(CanalEmailSearch::FOUND, $search->result);
        $this->assertSame('events', $search->found_via);
        $this->assertNull($search->requested_at);
        $this->assertTrue(SystemLog::where('event', 'canals.email_discovered')->where('recipient', 'fara@farnost.test')->exists());
    }

    #[Test]
    public function website_contact_page_wins_with_an_address_on_the_canal_domain(): void
    {
        $canal = $this->canal(null, website: 'https://www.farnost.test');
        $this->pages = [
            'https://www.farnost.test' => '<a href="/kontakt/">Kontakt</a><img src="logo@2x.png"> <a href="mailto:noreply@farnost.test">x</a>',
            'https://www.farnost.test/kontakt' => '<p>Farský úrad: urad [at] farnost.test</p><p>Kaplán: kaplan@gmail.com</p>',
        ];

        $this->assertSame(CanalEmailSearch::FOUND, app(CanalEmailFinder::class)->run($canal));

        $canal->refresh();
        $this->assertSame('urad@farnost.test', $canal->email);
        $this->assertEqualsCanonicalizing(['urad@farnost.test', 'kaplan@gmail.com'], $canal->emails()->pluck('email')->all());
        $this->assertSame('https://www.farnost.test/kontakt/', $canal->emails()->where('email', 'urad@farnost.test')->value('found_on'));
    }

    #[Test]
    public function a_website_shared_by_several_canals_is_not_searched(): void
    {
        $canal = $this->canal(null, website: 'https://www.cirkev.test');
        $this->canal(null, website: 'https://cirkev.test/');
        $this->pages = ['https://www.cirkev.test' => '<a href="mailto:media@cirkev.test">Médiá</a>'];

        $this->assertSame(CanalEmailSearch::NONE, app(CanalEmailFinder::class)->run($canal));
        $this->assertNull($canal->fresh()->email);
    }

    #[Test]
    public function managed_and_personal_canals_are_left_alone(): void
    {
        $managed = $this->canal(null, source: RegistrationSource::SELF);
        $personal = $this->canal(null, identity: CanalIdentityMode::Personal);
        $this->event($managed, 'fara@farnost.test');
        $this->event($personal, 'fara@farnost.test');

        $this->assertFalse(app(CanalEmailFinder::class)->dueQuery()->whereKey([$managed->id, $personal->id])->exists());
        $this->assertSame(CanalEmailSearch::SKIPPED, app(CanalEmailFinder::class)->run($managed));
        $this->assertSame(0, $managed->emails()->count());
    }

    #[Test]
    public function bounce_is_not_recorded_for_a_deleted_canal(): void
    {
        $alive = $this->canal('spolocna@farnost.test');
        $deleted = $this->canal('spolocna@farnost.test');
        $deleted->delete();

        $this->assertSame(1, $this->emails->recordBounce('spolocna@farnost.test'));
        $this->assertSame(1, SystemLog::where('event', 'canals.email_bounced')->count());
        $this->assertSame(CanalEmailStatus::Undeliverable, $alive->emails()->sole()->status);
    }

    #[Test]
    public function ai_address_is_accepted_only_when_its_source_page_contains_it(): void
    {
        config(['openai.api_key' => 'test', 'canals.emails.discovery.ai' => true]);
        $canal = $this->canal('zla@farnost.test', website: null);
        $this->fakeResearch('nova@farnost.test', 'https://dieceza.test/farnosti');
        $this->pages = ['https://dieceza.test/farnosti' => '<p>Farnosť: nova@farnost.test</p>'];

        $this->emails->recordBounce('zla@farnost.test');
        $this->artisan('app:canal-emails-find')->assertSuccessful();

        $this->assertSame('nova@farnost.test', $canal->fresh()->email);
        $this->assertSame('ai', CanalEmailSearch::where('canal_id', $canal->id)->value('found_via'));

        // Vymyslená adresa (na zdroji nie je) sa neprijme.
        $other = $this->canal('zla2@farnost.test', website: null);
        $this->fakeResearch('vymyslena@farnost.test', 'https://dieceza.test/farnosti');
        $this->emails->recordBounce('zla2@farnost.test');
        $this->artisan('app:canal-emails-find')->assertSuccessful();

        $this->assertNull($other->fresh()->email);
        $this->assertTrue(SystemLog::where('event', 'canals.email_ai_rejected')->exists());
    }

    #[Test]
    public function ai_is_not_asked_without_a_bounce(): void
    {
        config(['openai.api_key' => 'test']);
        $canal = $this->canal(null, website: null);
        $this->enriched($canal);
        $this->fakeResearch('nova@farnost.test', 'https://dieceza.test/farnosti', fail: true);

        $this->assertSame(CanalEmailSearch::NONE, app(CanalEmailFinder::class)->run($canal));
        $this->assertNotNull(CanalEmailSearch::where('canal_id', $canal->id)->value('retry_at'));
        // Kým neuplynie opakovanie, kanál nie je na rade.
        $this->assertFalse(app(CanalEmailFinder::class)->dueQuery()->whereKey($canal->id)->exists());
    }

    #[Test]
    public function a_canal_without_website_waits_for_profile_enrichment(): void
    {
        config(['openai.api_key' => 'test']);
        $canal = $this->canal(null, website: null);
        $finder = app(CanalEmailFinder::class);

        $this->assertFalse($finder->dueQuery()->whereKey($canal->id)->exists());
        $this->assertSame('awaiting_profile', $finder->skipReason($canal));
        $this->assertSame(CanalEmailSearch::SKIPPED, $finder->run($canal));
        // Čakanie nie je pokus a nič neodkladá.
        $this->assertNull(CanalEmailSearch::where('canal_id', $canal->id)->value('retry_at'));
        $this->assertFalse(SystemLog::where('event', 'canals.email_not_found')->exists());

        $this->enriched($canal);

        $this->assertTrue($finder->dueQuery()->whereKey($canal->id)->exists());
        $this->assertNull($finder->skipReason($canal));
    }

    #[Test]
    public function a_new_website_cancels_the_retry_delay(): void
    {
        $canal = $this->canal(null, website: null);
        $this->assertSame(CanalEmailSearch::NONE, app(CanalEmailFinder::class)->run($canal));
        $this->assertFalse(app(CanalEmailFinder::class)->dueQuery()->whereKey($canal->id)->exists());

        $this->pages = ['https://www.fara.test' => '<a href="mailto:farnost@fara.test">Napíšte nám</a>'];
        $canal->update(['website' => 'https://www.fara.test']);

        $search = CanalEmailSearch::where('canal_id', $canal->id)->sole();
        $this->assertNull($search->retry_at);
        $this->assertSame(0, $search->attempts);

        $this->artisan('app:canal-emails-find')->assertSuccessful();

        $this->assertSame('farnost@fara.test', $canal->fresh()->email);
    }

    #[Test]
    public function only_canals_with_a_current_event_or_a_bounce_are_searched(): void
    {
        $idle = $this->canal(null, currentEvent: false);
        $past = $this->canal(null, currentEvent: false);
        Event::factory()->create([
            'canal_id' => $past->id, 'user_id' => User::factory(), 'status' => ModelStatus::Archived->value,
            'start_at' => now()->subDays(40), 'end_at' => now()->subDays(40), 'email' => null, 'additional_emails' => null,
        ]);
        $bounced = $this->canal('zla@farnost.test', currentEvent: false);
        $this->emails->recordBounce('zla@farnost.test', CanalEmail::BOUNCE_HARD, '550 5.1.1');
        $finder = app(CanalEmailFinder::class);

        $this->assertEqualsCanonicalizing([$bounced->id], $finder->dueQuery()->whereKey([$idle->id, $past->id, $bounced->id])->pluck('id')->all());
        $this->assertSame('no_current_event', $finder->skipReason($idle));
        $this->assertSame(CanalEmailSearch::SKIPPED, $finder->run($idle));
        $this->assertNull(CanalEmailSearch::where('canal_id', $idle->id)->value('retry_at'));
    }

    #[Test]
    public function waiting_for_the_profile_ends_after_the_limit_or_without_budget(): void
    {
        config(['openai.api_key' => 'test', 'canals.emails.discovery.profile_wait_hours' => 48]);
        $finder = app(CanalEmailFinder::class);

        $stale = $this->canal(null, website: null, currentEvent: false);
        $this->currentEvent($stale, now()->subHours(49));
        $this->assertTrue($finder->dueQuery()->whereKey($stale->id)->exists());
        $this->assertNull($finder->skipReason($stale));

        $fresh = $this->canal(null, website: null);
        $this->assertSame('awaiting_profile', $finder->skipReason($fresh));

        \App\Models\AiUsage::create([
            'feature' => ProfileResearch::FEATURE, 'model' => 'test', 'cost_usd' => 5,
            'prompt_tokens' => 0, 'completion_tokens' => 0,
        ]);
        $this->assertTrue($finder->dueQuery()->whereKey($fresh->id)->exists());
        $this->assertNull($finder->skipReason($fresh));
    }

    private function enriched(Canal $canal): void
    {
        ProfileEnrichment::create([
            'subject_type' => $canal->getMorphClass(),
            'subject_id' => $canal->id,
            'attempts' => 1,
            'completed_at' => now(),
            'notification_completed_at' => now(),
        ]);
    }

    private function fakeResearch(string $email, string $source, bool $fail = false): void
    {
        $this->app->instance(ProfileResearch::class, new class($email, $source, $fail) extends ProfileResearch
        {
            public function __construct(private string $email, private string $source, private bool $fail) {}

            public function research($subject, array $missing, array $hints = []): array
            {
                if ($this->fail) {
                    throw new \LogicException('AI sa nemá volať.');
                }

                return ['email' => ['value' => $this->email, 'source_url' => $this->source, 'evidence' => 'kontakt']];
            }
        });
    }

    private function canal(
        ?string $email,
        ?string $website = 'https://farnost.test',
        RegistrationSource $source = RegistrationSource::IMPORT,
        CanalIdentityMode $identity = CanalIdentityMode::Organization,
        bool $currentEvent = true,
    ): Canal {
        $canal = Canal::factory()->active()->create([
            'registration_source' => $source->value,
            'identity_mode' => $identity->value,
            'name' => 'Farnosť '.fake()->unique()->city(),
            'email' => $email,
            'email_verified_at' => null,
            'website' => $website,
            'claimed_at' => null,
        ]);

        if ($currentEvent) {
            $this->currentEvent($canal);
        }

        return $canal;
    }

    /** Zverejnená akcia bez e-mailu, ktorá ešte nezačala. */
    private function currentEvent(Canal $canal, ?\DateTimeInterface $published = null): Event
    {
        return Event::factory()->create([
            'canal_id' => $canal->id,
            'user_id' => User::factory(),
            'status' => ModelStatus::Published->value,
            'published_at' => $published ?? now()->subHour(),
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(2),
            'email' => null,
            'additional_emails' => null,
        ]);
    }

    private function event(Canal $canal, string $email): Event
    {
        return Event::factory()->create([
            'canal_id' => $canal->id,
            'user_id' => User::factory(),
            'status' => ModelStatus::Published->value,
            'email' => $email,
        ]);
    }
}
