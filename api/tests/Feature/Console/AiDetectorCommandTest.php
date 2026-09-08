<?php

namespace Tests\Feature\Console;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Venue;
use App\Services\OpenAI\Detector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiDetectorCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unavailable_sources_are_skipped_in_both_queues_and_other_events_can_proceed(): void
    {
        $collection = Canal::factory()->create([
            'name' => 'vyveska.sk',
            'slug' => 'vyveska-sk',
            'website' => 'https://www.vyveska.sk',
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
        $user = User::factory()->create(['canal_id' => $collection->id]);
        $venue = Venue::factory()->create(['canal_id' => $collection->id]);
        foreach ([404, 410] as $status) {
            foreach ([null, now()->subDay()->startOfSecond()] as $rewrittenAt) {
                $event = Event::factory()->create([
                    'canal_id' => $collection->id,
                    'user_id' => $user->id,
                    'venue_id' => $venue->id,
                    'published_at' => now(),
                    'orginal_source' => 'https://example.test/missing',
                    'body_rewritten_at' => $rewrittenAt,
                    'meta' => ['import' => ['source' => 'external_source']],
                ]);
                $originalBody = $event->body;
                $detector = Mockery::mock(Detector::class);
                $detector->shouldReceive('detectFromUrl')->once()->andReturn([
                    'success' => false, 'error' => 'HTTP chyba: '.$status, 'source_http_status' => $status,
                ]);
                $this->app->instance(Detector::class, $detector);
                $this->artisan('app:ai-detector')->assertSuccessful();
                $this->artisan('app:ai-detector')->expectsOutput('AiDetector: no eligible event found.')->assertSuccessful();
                $event->refresh();
                $this->assertSame($originalBody, $event->body);
                $this->assertEquals($rewrittenAt, $event->body_rewritten_at);
                $this->assertNotNull($event->meta['ai_detector']['skipped_at']);
                $this->assertSame('external_source', $event->meta['import']['source']);
            }
        }
    }

    #[Test]
    public function transient_failure_is_retried_after_an_hour_and_cleared_on_success(): void
    {
        $this->freezeTime();
        $canal = Canal::factory()->create();
        $user = User::factory()->create(['canal_id' => $canal->id]);
        $venue = Venue::factory()->create(['canal_id' => $canal->id]);
        $event = Event::factory()->create([
            'canal_id' => $canal->id, 'user_id' => $user->id, 'venue_id' => $venue->id,
            'published_at' => now(), 'orginal_source' => 'https://example.test/temporary',
            'body_rewritten_at' => null,
        ]);
        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromUrl')->twice()->andReturn(
            ['success' => false, 'error' => 'HTTP chyba: 503', 'source_http_status' => 503],
            ['success' => true, 'corrected_text' => null],
        );
        $this->app->instance(Detector::class, $detector);
        $this->artisan('app:ai-detector')->assertFailed();
        $this->artisan('app:ai-detector')->expectsOutput('AiDetector: no eligible event found.')->assertSuccessful();
        $this->assertNull($event->fresh()->body_rewritten_at);
        $this->travel(1)->hours();
        $this->artisan('app:ai-detector')->assertSuccessful();
        $this->assertNotNull($event->fresh()->body_rewritten_at);
        $this->assertArrayNotHasKey('retry_at', $event->fresh()->meta['ai_detector']);
    }

    #[Test]
    public function it_rewrites_the_body_of_the_latest_imported_published_event(): void
    {
        $canal = Canal::factory()->create([
            'website' => 'https://www.vyveska.sk',
        ]);
        $user = User::factory()->create([
            'canal_id' => $canal->id,
        ]);
        $venue = Venue::factory()->create([
            'canal_id' => $canal->id,
        ]);

        Event::factory()->create([
            'canal_id' => $canal->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subHour(),
            'orginal_source' => 'https://example.test/older-event',
            'body_rewritten_at' => now()->subHour(),
        ]);

        $event = Event::factory()->create([
            'canal_id' => $canal->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'orginal_source' => 'https://example.test/event',
            'body' => '<p>Surový zoškrabaný text z importu.</p>',
            'body_rewritten_at' => null,
            'meta' => ['import' => ['source' => 'external_source']],
        ]);

        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromUrl')
            ->once()
            ->with('https://example.test/event')
            ->andReturn([
                'success' => true,
                'corrected_text' => '<h3>Program</h3><p>Púť sa začína <strong>o 9:00</strong>.</p>',
                'extracted_text' => 'AI extracted body text',
                'links' => ['https://example.test/info'],
                'attachments' => [
                    ['url' => 'https://example.test/file.pdf'],
                ],
                'event_payload' => [
                    'name' => 'Detected name',
                ],
            ]);
        $this->app->instance(Detector::class, $detector);

        $this->artisan('app:ai-detector')
            ->expectsOutput('AiDetector processed event id '.$event->id.'.')
            ->assertSuccessful();

        $event->refresh();

        // Popis prepíše copywriter HTML — vykresľuje sa cez v-html.
        $this->assertSame('<h3>Program</h3>' . "\n" . '<p>Púť sa začína <strong>o 9:00</strong>.</p>', $event->body);
        $this->assertNotNull($event->body_rewritten_at);
        // Pôvodný zoškrabaný text ostáva dostupný v meta.
        $this->assertSame('<p>Surový zoškrabaný text z importu.</p>', $event->meta['imported_raw_body'] ?? null);
        $this->assertSame('https://example.test/event', $event->meta['ai_detector']['source_url'] ?? null);
        $this->assertSame(['https://example.test/info'], $event->meta['ai_detector']['links'] ?? null);
        $this->assertSame('Detected name', $event->meta['ai_detector']['event_payload']['name'] ?? null);
    }

    #[Test]
    public function it_marks_the_event_processed_but_keeps_the_body_when_the_copywriter_returns_nothing(): void
    {
        $canal = Canal::factory()->create();
        $user = User::factory()->create(['canal_id' => $canal->id]);
        $venue = Venue::factory()->create(['canal_id' => $canal->id]);

        $event = Event::factory()->create([
            'canal_id' => $canal->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'orginal_source' => 'https://example.test/event',
            'body' => '<p>Pôvodný text.</p>',
            'body_rewritten_at' => null,
        ]);

        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromUrl')
            ->once()
            ->andReturn([
                'success' => true,
                'corrected_text' => null,
                'extracted_text' => 'AI extracted body text',
                'event_payload' => ['name' => 'Detected name'],
            ]);
        $this->app->instance(Detector::class, $detector);

        $this->artisan('app:ai-detector')->assertSuccessful();

        $event->refresh();

        // Bez copywriter HTML sa telo nedotýka — surový extrakt je horší než to,
        // čo už v `body` je z importu. Podujatie je ale označené za spracované,
        // aby ďalší beh nezacyklil na tom istom zázname.
        $this->assertSame('<p>Pôvodný text.</p>', $event->body);
        $this->assertNotNull($event->body_rewritten_at);
    }

    /**
     * Tento beh číta celý článok naraz, takže o organizátorovi vie viac než
     * import — mesto z jeho payloadu je najlepší údaj o sídle kanála, aký
     * máme. Dovtedy sa len uložilo do `meta` a nikto ho nepoužil: kanál
     * ostal sedieť na obci odvodenej z (nesprávne trafeného) miesta konania.
     */
    #[Test]
    public function it_gives_the_canal_the_seat_of_the_detected_organizer(): void
    {
        $nationwideId = Municipality::nationwideId();
        $trnavaId = (int) Municipality::query()->where('slug', 'trnava')->value('id');

        $canal = Canal::factory()->create([
            'municipality_id' => $nationwideId,
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
        $user = User::factory()->create(['canal_id' => $canal->id]);
        $venue = Venue::factory()->create(['canal_id' => $canal->id]);

        Event::factory()->create([
            'canal_id' => $canal->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'orginal_source' => 'https://example.test/event',
            'body_rewritten_at' => null,
        ]);

        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromUrl')
            ->once()
            ->andReturn([
                'success' => true,
                'corrected_text' => null,
                'event_payload' => [
                    'organizer' => ['name' => 'Západoslovenské múzeum', 'city' => 'Trnava'],
                ],
            ]);
        $this->app->instance(Detector::class, $detector);

        $this->artisan('app:ai-detector')->assertSuccessful();

        $this->assertSame($trnavaId, (int) $canal->fresh()->municipality_id);
    }

    /**
     * Zberný kanál zdroja („vyveska.sk") nie je organizátor, ale odkladisko na
     * podujatia, pri ktorých import nevedel prečítať, kto ich robí. Tento beh
     * číta celý článok naraz, takže organizátora často pozná — a vtedy sa
     * podujatie presunie k nemu.
     */
    #[Test]
    public function it_moves_an_event_off_the_source_collection_canal_to_the_detected_organizer(): void
    {
        Role::findOrCreate('super-admin', 'web');
        User::factory()->create()->assignRole('super-admin');
        config()->set('services.imports.describe_with_ai', false);

        $collection = Canal::factory()->create([
            'name' => 'vyveska.sk',
            'slug' => 'vyveska-sk',
            'website' => 'https://www.vyveska.sk',
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
        $user = User::factory()->create(['canal_id' => $collection->id]);
        $venue = Venue::factory()->create(['canal_id' => $collection->id]);

        $event = Event::factory()->create([
            'canal_id' => $collection->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'orginal_source' => 'https://www.vyveska.sk/podujatie',
            'body_rewritten_at' => null,
        ]);

        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromUrl')
            ->once()
            ->andReturn([
                'success' => true,
                'corrected_text' => null,
                'event_payload' => [
                    'organizer' => ['name' => 'Farnosť Košice-Sever vás srdečne pozýva'],
                ],
            ]);
        $this->app->instance(Detector::class, $detector);

        $this->artisan('app:ai-detector')->assertSuccessful();

        $event->refresh();
        $target = Canal::query()->find($event->canal_id);

        $this->assertNotSame($collection->id, $event->canal_id);
        // Veta pozvánky nalepená za meno sa oreže rovnako ako pri importe.
        $this->assertSame('Farnosť Košice-Sever', $target?->name);
        $this->assertSame('https://www.vyveska.sk', $target?->website);
    }

    /**
     * Kanál pomenovaný organizátorom je hotový údaj — ten sa neprepisuje ani
     * vtedy, keď AI v článku vidí niekoho iného (napr. spoluorganizátora).
     */
    #[Test]
    public function it_leaves_the_event_on_a_canal_that_already_names_an_organizer(): void
    {
        $canal = Canal::factory()->create([
            'name' => 'Cirkevný zbor ECAV Bardejov',
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
        $user = User::factory()->create(['canal_id' => $canal->id]);
        $venue = Venue::factory()->create(['canal_id' => $canal->id]);

        $event = Event::factory()->create([
            'canal_id' => $canal->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'orginal_source' => 'https://www.ecav.sk/podujatie',
            'body_rewritten_at' => null,
        ]);

        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromUrl')
            ->once()
            ->andReturn([
                'success' => true,
                'corrected_text' => null,
                'event_payload' => ['organizer' => ['name' => 'Mesto Bardejov']],
            ]);
        $this->app->instance(Detector::class, $detector);

        $this->artisan('app:ai-detector')->assertSuccessful();

        $this->assertSame($canal->id, $event->fresh()->canal_id);
    }

    /**
     * Druhý rad príkazu. Podujatia zo zberných kanálov majú popis dávno
     * prepísaný, takže ich prvý claim (`body_rewritten_at IS NULL`) preskočí —
     * bez tohto radu by na zbernom kanáli ostali visieť navždy.
     */
    #[Test]
    public function it_rechecks_the_organizer_of_an_already_rewritten_collection_canal_event(): void
    {
        Role::findOrCreate('super-admin', 'web');
        User::factory()->create()->assignRole('super-admin');
        config()->set('services.imports.describe_with_ai', false);

        $collection = Canal::factory()->create([
            'name' => 'tkkbs.sk',
            'slug' => 'tkkbs-sk',
            'website' => 'https://www.tkkbs.sk',
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
        $user = User::factory()->create(['canal_id' => $collection->id]);
        $venue = Venue::factory()->create(['canal_id' => $collection->id]);

        $event = Event::factory()->create([
            'canal_id' => $collection->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
            'orginal_source' => 'https://www.tkkbs.sk/view.php?cisloclanku=1',
            'body' => '<p>Prepísaný popis.</p>',
            'body_rewritten_at' => now()->subMonth(),
            'meta' => ['ai_detector' => ['processed_at' => now()->subMonth()->toIso8601String()]],
        ]);

        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromUrl')
            ->once()
            ->andReturn([
                'success' => true,
                'corrected_text' => '<p>Druhýkrát prepísaný popis.</p>',
                'event_payload' => ['organizer' => ['name' => 'Rehoľa menších bratov františkánov']],
            ]);
        $this->app->instance(Detector::class, $detector);

        $this->artisan('app:ai-detector')->assertSuccessful();

        $event->refresh();

        $this->assertNotSame($collection->id, $event->canal_id);
        // Hotový popis sa druhýkrát neprepisuje — copywriter už raz zbehol.
        $this->assertSame('<p>Prepísaný popis.</p>', $event->body);
        $this->assertNotNull($event->meta['ai_detector']['organizer_checked_at'] ?? null);
    }

    /**
     * Rad sa musí vyčerpať: podujatie, ktorého sa už príkaz na organizátora
     * pýtal, sa nesmie vrátiť do druhého claimu — inak by naň minul AI
     * volanie každú minútu donekonečna.
     */
    #[Test]
    public function it_does_not_recheck_a_collection_canal_event_twice(): void
    {
        $collection = Canal::factory()->create([
            'name' => 'ecav.sk',
            'slug' => 'ecav-sk',
            'website' => 'https://www.ecav.sk',
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
        $user = User::factory()->create(['canal_id' => $collection->id]);
        $venue = Venue::factory()->create(['canal_id' => $collection->id]);

        Event::factory()->create([
            'canal_id' => $collection->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
            'orginal_source' => 'https://www.ecav.sk/podujatie',
            'body_rewritten_at' => now()->subMonth(),
            'meta' => ['ai_detector' => ['organizer_checked_at' => now()->subDay()->toIso8601String()]],
        ]);

        $detector = Mockery::mock(Detector::class);
        $detector->shouldNotReceive('detectFromUrl');
        $this->app->instance(Detector::class, $detector);

        $this->artisan('app:ai-detector')
            ->expectsOutput('AiDetector: no eligible event found.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_exits_successfully_when_no_event_is_available(): void
    {
        $detector = Mockery::mock(Detector::class);
        $detector->shouldNotReceive('detectFromUrl');
        $this->app->instance(Detector::class, $detector);

        $this->artisan('app:ai-detector')
            ->expectsOutput('AiDetector: no eligible event found.')
            ->assertSuccessful();
    }
}
