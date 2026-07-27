<?php

namespace Tests\Feature\Tags;

use App\Models\Event;
use App\Models\Tag;
use App\Models\TagSuggestion;
use App\Services\OpenAI\ChatGPT;
use App\Services\Tags\EventAttributeDeriver;
use App\Services\Tags\EventTagger;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class EventTaggerTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();

        $this->futureEvent->update([
            'name' => 'Folklórny festival',
            'body' => 'Trojdňová prehliadka ľudových súborov s tancom a hudbou.',
        ]);
    }

    private function seedCatalog(): void
    {
        $tags = [
            ['format', 'koncert', 'Koncert'],
            ['format', 'festival', 'Festival'],
            ['topic', 'folklor', 'Folklór'],
            ['topic', 'tanec', 'Tanec'],
            ['audience', 'pre-rodiny', 'Pre rodiny'],
            ['attribute', 'viacdnove', 'Viacdňové'],
            ['attribute', 'vonku', 'Vonku'],
        ];

        foreach ($tags as $index => [$group, $slug, $name]) {
            Tag::query()->create([
                'group' => $group,
                'slug' => $slug,
                'name' => $name,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }

    /**
     * ChatGPT sa injektuje ako `new` default, nie z kontajnera — stub sa preto
     * robí podedením, rovnako ako v tests/Unit/Venues/DetectorVenueDetailsTest.
     */
    private function tagger(array $tags, array $suggested = []): EventTagger
    {
        $chatGpt = new class($tags, $suggested) extends ChatGPT {
            public function __construct(private array $stubTags, private array $stubSuggested)
            {
                parent::__construct();
            }

            public function extractTags(string $text, array $catalog): array
            {
                return ['tags' => $this->stubTags, 'suggested' => $this->stubSuggested];
            }
        };

        return new EventTagger($chatGpt, new EventAttributeDeriver());
    }

    private function slugsFor(Event $event, ?string $source = null): array
    {
        return DB::table('event_tag')
            ->join('tags', 'tags.id', '=', 'event_tag.tag_id')
            ->where('event_tag.event_id', $event->id)
            ->when($source !== null, fn ($query) => $query->where('event_tag.source', $source))
            ->orderBy('tags.slug')
            ->pluck('tags.slug')
            ->all();
    }

    #[Test]
    public function it_assigns_tags_across_facets(): void
    {
        $result = $this->tagger([
            ['slug' => 'festival', 'confidence' => 95],
            ['slug' => 'folklor', 'confidence' => 90],
            ['slug' => 'pre-rodiny', 'confidence' => 80],
        ])->tag($this->futureEvent);

        $this->assertTrue($result['success']);
        $this->assertSame(['festival', 'folklor', 'pre-rodiny'], $this->slugsFor($this->futureEvent, 'ai'));
    }

    #[Test]
    public function it_drops_tags_below_the_confidence_threshold(): void
    {
        // Merania ukázali, že nezmysly sedia na 50–60, správne určenia na 85+.
        $this->tagger([
            ['slug' => 'festival', 'confidence' => 90],
            ['slug' => 'koncert', 'confidence' => 55],
        ])->tag($this->futureEvent);

        $this->assertSame(['festival'], $this->slugsFor($this->futureEvent, 'ai'));
    }

    #[Test]
    public function it_ignores_slugs_that_are_not_in_the_catalog(): void
    {
        $this->tagger([
            ['slug' => 'festival', 'confidence' => 90],
            ['slug' => 'vymysleny-stitok', 'confidence' => 99],
        ])->tag($this->futureEvent);

        $this->assertSame(['festival'], $this->slugsFor($this->futureEvent, 'ai'));
    }

    #[Test]
    public function manual_tags_survive_retagging(): void
    {
        $manual = Tag::query()->where('slug', 'koncert')->firstOrFail();

        $this->futureEvent->tags()->attach([
            $manual->id => ['confidence' => 100, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->tagger([['slug' => 'festival', 'confidence' => 90]])->tag($this->futureEvent);

        $this->assertSame(['koncert'], $this->slugsFor($this->futureEvent, 'manual'));
        $this->assertContains('festival', $this->slugsFor($this->futureEvent));
    }

    #[Test]
    public function retagging_replaces_previous_ai_tags(): void
    {
        $this->tagger([['slug' => 'koncert', 'confidence' => 90]])->tag($this->futureEvent);
        $this->assertSame(['koncert'], $this->slugsFor($this->futureEvent, 'ai'));

        $this->tagger([['slug' => 'festival', 'confidence' => 90]])->tag($this->futureEvent);
        $this->assertSame(['festival'], $this->slugsFor($this->futureEvent, 'ai'));
    }

    #[Test]
    public function suggestions_are_collected_and_counted(): void
    {
        $this->tagger([], ['hasičská súťaž', 'Púť'])->tag($this->futureEvent);

        $this->assertDatabaseHas('tag_suggestions', ['slug' => 'hasicska-sutaz', 'occurrences' => 1]);

        $this->tagger([], ['hasičská súťaž'])->tag($this->futureEvent);

        $this->assertSame(2, (int) TagSuggestion::query()->where('slug', 'hasicska-sutaz')->value('occurrences'));
    }

    #[Test]
    public function suggestions_already_in_the_catalog_are_not_recorded(): void
    {
        $this->tagger([], ['Festival'])->tag($this->futureEvent);

        $this->assertDatabaseMissing('tag_suggestions', ['slug' => 'festival']);
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $result = $this->tagger([['slug' => 'festival', 'confidence' => 90]], ['púť'])
            ->tag($this->futureEvent, true);

        $this->assertTrue($result['success']);
        $this->assertSame([], $this->slugsFor($this->futureEvent));
        $this->assertDatabaseCount('tag_suggestions', 0);
        $this->assertNull($this->futureEvent->fresh()->ai_tagged_at);
    }

    #[Test]
    public function successful_tagging_stamps_state_and_resets_attempts(): void
    {
        $this->futureEvent->forceFill(['ai_tags_attempts' => 2])->save();

        $tagger = $this->tagger([['slug' => 'festival', 'confidence' => 90]]);
        $tagger->tag($this->futureEvent);

        $fresh = $this->futureEvent->fresh();

        $this->assertNotNull($fresh->ai_tagged_at);
        $this->assertSame(0, (int) $fresh->ai_tags_attempts);
        $this->assertSame($tagger->contentHash($fresh), $fresh->ai_tags_hash);
    }

    #[Test]
    public function ai_failure_is_reported_not_thrown(): void
    {
        $chatGpt = new class extends ChatGPT {
            public function extractTags(string $text, array $catalog): array
            {
                throw new \RuntimeException('OpenAI API error: 429');
            }
        };

        $result = (new EventTagger($chatGpt, new EventAttributeDeriver()))->tag($this->futureEvent);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('429', $result['error']);
        $this->assertNull($this->futureEvent->fresh()->ai_tagged_at);
    }

    #[Test]
    public function catalog_version_changes_when_a_tag_is_added(): void
    {
        $before = $this->tagger([])->catalogVersion();

        Tag::query()->create([
            'group' => 'format',
            'slug' => 'put',
            'name' => 'Púť',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        // Nový štítok musí zneplatniť hash, inak by ho dostali len novo
        // pridané podujatia a existujúce by sa nikdy nepreštítkovali.
        $this->assertNotSame($before, $this->tagger([])->catalogVersion());
    }

    #[Test]
    public function attribute_facet_is_not_offered_to_the_ai(): void
    {
        $captured = null;

        $chatGpt = new class($captured) extends ChatGPT {
            public function __construct(private &$captured)
            {
                parent::__construct();
            }

            public function extractTags(string $text, array $catalog): array
            {
                $this->captured = $catalog;

                return ['tags' => [], 'suggested' => []];
            }
        };

        (new EventTagger($chatGpt, new EventAttributeDeriver()))->tag($this->futureEvent);

        $this->assertArrayHasKey('format', $captured);
        // Facet „charakter" odvádza EventAttributeDeriver z dát — model ho
        // halucinoval (napr. „online" fyzickej púti).
        $this->assertArrayNotHasKey('attribute', $captured);
    }
}
