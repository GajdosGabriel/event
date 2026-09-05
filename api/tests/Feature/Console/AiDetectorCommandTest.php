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
use Tests\TestCase;

class AiDetectorCommandTest extends TestCase
{
    use RefreshDatabase;

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
