<?php

namespace Tests\Feature\Console;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
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
    public function it_processes_latest_imported_published_event_without_body_ai(): void
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
            'body_ai' => 'already processed',
        ]);

        $event = Event::factory()->create([
            'canal_id' => $canal->id,
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'orginal_source' => 'https://example.test/event',
            'body_ai' => null,
            'meta' => ['import' => ['source' => 'external_source']],
        ]);

        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromUrl')
            ->once()
            ->with('https://example.test/event')
            ->andReturn([
                'success' => true,
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
            ->expectsOutput('AiDetector processed event id ' . $event->id . '.')
            ->assertSuccessful();

        $event->refresh();

        $this->assertSame('AI extracted body text', $event->body_ai);
        $this->assertSame('https://example.test/event', $event->meta['ai_detector']['source_url'] ?? null);
        $this->assertSame(['https://example.test/info'], $event->meta['ai_detector']['links'] ?? null);
        $this->assertSame('Detected name', $event->meta['ai_detector']['event_payload']['name'] ?? null);
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
