<?php

namespace Tests\Feature\Console;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Tag;
use App\Services\Tags\EventTagger;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class AiTagEventsCommandTest extends EventSetupTest
{
    private const CATALOG_VERSION = 'stub-version';

    private function expectedHash(Event $event): string
    {
        return md5(implode('|', [
            self::CATALOG_VERSION,
            (string) $event->name,
            (string) ($event->body_ai ?? ''),
            (string) ($event->body ?? ''),
        ]));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Tag::query()->create([
            'group' => 'format',
            'slug' => 'festival',
            'name' => 'Festival',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // Len publikované a naplánované podujatia sa štítkujú.
        Event::query()->whereKeyNot($this->futureEvent->id)->update(['status' => ModelStatus::Draft->value]);
        $this->futureEvent->update(['status' => ModelStatus::Published->value]);
    }

    /**
     * Príkaz si EventTagger vyžiada cez type-hint v handle(), takže sa dá
     * podstrčiť cez kontajner.
     */
    private function fakeTagger(bool $success = true, int $expectedCalls = 1): void
    {
        $tagger = Mockery::mock(EventTagger::class);
        $tagger->shouldReceive('catalogVersion')->andReturn(self::CATALOG_VERSION);
        $tagger->shouldReceive('tag')
            ->times($expectedCalls)
            ->andReturnUsing(function (Event $event, bool $dryRun = false) use ($success) {
                if ($success && ! $dryRun) {
                    $event->forceFill([
                        'ai_tagged_at' => now(),
                        // Musí sedieť s výrazom v SQL strážcovi príkazu, inak
                        // by podujatie ostalo vo výbere a test „nešttítkuj
                        // dvakrát" by nič neoveroval.
                        'ai_tags_hash' => $this->expectedHash($event),
                        'ai_tags_attempts' => 0,
                    ])->saveQuietly();
                }

                return $success
                    ? ['success' => true, 'tags' => ['festival'], 'derived' => [], 'suggested' => []]
                    : ['success' => false, 'error' => 'OpenAI API error: 429'];
            });

        $this->app->instance(EventTagger::class, $tagger);
    }

    #[Test]
    public function it_reports_when_nothing_is_eligible(): void
    {
        Event::query()->update(['status' => ModelStatus::Draft->value]);

        $tagger = Mockery::mock(EventTagger::class);
        $tagger->shouldReceive('catalogVersion')->andReturn('stub-version');
        $tagger->shouldNotReceive('tag');
        $this->app->instance(EventTagger::class, $tagger);

        $this->artisan('app:events-ai-tag')
            ->expectsOutput('AiTagEvents: no eligible event found.')
            ->assertSuccessful();
    }

    #[Test]
    public function unchanged_event_is_not_tagged_twice(): void
    {
        $this->fakeTagger(expectedCalls: 1);

        $this->artisan('app:events-ai-tag --limit=1')->assertSuccessful();

        // Druhý beh musí nájsť prázdno — hash sedí, takže sa nič neprepočítava.
        $this->artisan('app:events-ai-tag --limit=1')
            ->expectsOutput('AiTagEvents: no eligible event found.')
            ->assertSuccessful();
    }

    #[Test]
    public function force_retags_even_when_nothing_changed(): void
    {
        $this->fakeTagger(expectedCalls: 2);

        $this->artisan('app:events-ai-tag --limit=1')->assertSuccessful();
        $this->artisan('app:events-ai-tag --limit=1 --force')->assertSuccessful();
    }

    #[Test]
    public function attempts_are_counted_before_the_call_and_capped(): void
    {
        // Zlyhanie nesmie nechať podujatie vo fronte navždy — presne to robí
        // app:ai-detector, ktorý ako claim používa len `body_ai IS NULL`.
        $this->fakeTagger(success: false, expectedCalls: 3);

        for ($run = 1; $run <= 3; $run++) {
            $this->artisan('app:events-ai-tag --limit=1')->assertFailed();
            $this->assertSame($run, (int) $this->futureEvent->fresh()->ai_tags_attempts);
        }

        $this->artisan('app:events-ai-tag --limit=1')
            ->expectsOutput('AiTagEvents: no eligible event found.')
            ->assertSuccessful();
    }

    #[Test]
    public function dry_run_does_not_touch_attempt_counter(): void
    {
        $this->fakeTagger(expectedCalls: 1);

        $this->artisan('app:events-ai-tag --limit=1 --dry-run')->assertSuccessful();

        $this->assertSame(0, (int) $this->futureEvent->fresh()->ai_tags_attempts);
        $this->assertNull($this->futureEvent->fresh()->ai_tagged_at);
    }

    #[Test]
    public function limit_caps_the_batch(): void
    {
        Event::query()->update(['status' => ModelStatus::Published->value]);

        $this->fakeTagger(expectedCalls: 2);

        $this->artisan('app:events-ai-tag --limit=2')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
