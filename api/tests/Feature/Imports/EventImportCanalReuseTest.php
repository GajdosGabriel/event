<?php

namespace Tests\Feature\Imports;

use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Services\Imports\EventDetailService;
use App\Services\Imports\EventImportService;
use App\Services\Imports\ImportedCanalNameResolver;
use App\Services\Imports\ImportedVenueManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventImportCanalReuseTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE_URL = 'https://www.ecav.sk/aktuality/pozvanky/teologicka-konferencia';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.imports.describe_with_ai', false);
        config()->set('services.imports.detect_canal_with_ai', false);

        Role::findOrCreate('super-admin', 'web');
        User::factory()->create()->assignRole('super-admin');
    }

    #[Test]
    public function it_does_not_create_a_canal_for_an_article_that_already_has_an_event(): void
    {
        // Regresia: kanál sa zakladal ešte pred kontrolou, či článok už event
        // má. Keď AI vrátila iný názov organizátora, vznikol nový kanál, ale
        // event ostal pod pôvodným (canal_id sa zámerne neprepisuje) — a nový
        // kanál ostal navždy bez podujatia.
        $originalCanal = Canal::factory()->create([
            'name' => 'ECAV na Slovensku',
            'slug' => 'ecav-na-slovensku',
        ]);
        $originalVenue = Venue::factory()->forCanal($originalCanal->id)->create();

        $event = Event::factory()->create([
            'canal_id' => $originalCanal->id,
            'venue_id' => $originalVenue->id,
            'user_id' => User::factory()->create()->id,
            'orginal_source' => self::SOURCE_URL,
        ]);

        $this->fakeImportPipeline('Generálny biskupský úrad ECAV');

        $canalsBefore = Canal::query()->count();

        $status = app(EventImportService::class)->importArticle(self::SOURCE_URL, force: true);

        $this->assertSame('updated', $status);
        $this->assertSame(
            $canalsBefore,
            Canal::query()->count(),
            'Import už raz naimportovaného článku nesmie založiť ďalší kanál.',
        );
        $this->assertSame($originalCanal->id, $event->fresh()->canal_id);
    }

    #[Test]
    public function it_still_creates_a_canal_for_a_genuinely_new_article(): void
    {
        $this->fakeImportPipeline('Generálny biskupský úrad ECAV');

        $canalsBefore = Canal::query()->count();

        $status = app(EventImportService::class)->importArticle(self::SOURCE_URL);

        $this->assertSame('imported', $status);
        $this->assertSame($canalsBefore + 1, Canal::query()->count());

        $canal = Canal::query()->where('name', 'Generálny biskupský úrad ECAV')->first();
        $this->assertNotNull($canal);
        $this->assertSame(1, Event::query()->where('canal_id', $canal->id)->count());
    }

    private function fakeImportPipeline(string $detectedName): void
    {
        $detail = Mockery::mock(EventDetailService::class);
        $detail->shouldReceive('extract')->andReturn([
            'title' => 'Teologická konferencia',
            'body' => '<p>Pozvánka na konferenciu.</p>',
            'body_text' => 'Pozvánka na konferenciu.',
            'start_at' => now()->addWeek()->startOfHour(),
            'end_at' => now()->addWeek()->startOfHour()->addHours(2),
            'start_at_precise' => true,
            'registration_deadline_at' => null,
            'published_at_source' => now()->subDay(),
            'links' => [],
            'link_items' => [],
            'image_urls' => [],
            'attachments' => [],
            'source_url' => self::SOURCE_URL,
        ]);
        $this->app->instance(EventDetailService::class, $detail);

        $resolver = Mockery::mock(ImportedCanalNameResolver::class);
        $resolver->shouldReceive('resolve')->andReturn([
            'name' => $detectedName,
            'detected_name' => $detectedName,
            'source_origin' => 'https://www.ecav.sk',
            'detected_venue_name' => null,
            'detected_venue_city' => null,
            'detected_venue_street' => null,
            'ai_start_at' => null,
            'ai_end_at' => null,
            'ai_email' => null,
            'ai_phone' => null,
        ]);
        $this->app->instance(ImportedCanalNameResolver::class, $resolver);

        // Konkrétne miesto nie je predmetom testu — pravý manažér k nemu doťahuje
        // obce aj geokódovanie. Napodobní sa len to podstatné: vrátené miesto
        // vždy patrí kanálu, s ktorým bol zavolaný.
        $venueManager = Mockery::mock(ImportedVenueManager::class);
        $venueManager->shouldReceive('resolveOrDetect')
            ->andReturnUsing(function (Canal $canal): Venue {
                $venue = Venue::query()
                    ->whereHas('canals', fn ($query) => $query->where('canals.id', $canal->id))
                    ->first();

                return $venue ?? Venue::factory()->forCanal($canal->id)->create();
            });
        $this->app->instance(ImportedVenueManager::class, $venueManager);
    }
}
