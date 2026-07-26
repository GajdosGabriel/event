<?php

namespace Tests\Feature\Imports;

use App\Models\Canal;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Imports\ImportedVenueManager;
use App\Services\OpenAI\Detector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportedVenueManagerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reuses_an_existing_venue_instead_of_creating_a_duplicate(): void
    {
        // Regresia: importované miesta majú category = NULL a filter
        // `category != 'fallback'` ich v SQL odfiltroval (NULL != 'x' je NULL),
        // takže každý beh importu zakladal nový duplikát.
        config()->set('services.imports.detect_canal_with_ai', false);
        config()->set('services.imports.describe_with_ai', false);

        $canal = Canal::factory()->create();
        $municipality = Municipality::query()->first();

        $existing = Venue::factory()->create([
            'name' => 'Šarišský hrad',
            'category' => null,
            'village_id' => $municipality?->id,
        ]);

        $before = Venue::query()->count();

        $first = app(ImportedVenueManager::class)->resolveOrDetect($canal, 'Šarišský hrad', 'Veľký Šariš');
        $second = app(ImportedVenueManager::class)->resolveOrDetect($canal, 'Šarišský hrad', 'Veľký Šariš');

        $this->assertSame($existing->id, $first->id);
        $this->assertSame($existing->id, $second->id);
        $this->assertSame($before, Venue::query()->count());
    }

    #[Test]
    public function it_reuses_a_venue_the_detector_stored_under_a_normalised_name(): void
    {
        // Regresia: detektor prepíše surový názov z článku ("Evanjelickom
        // kostole v Liptovskom Mikuláši") na normalizovaný ("Evanjelický
        // kostol (Liptovský Mikuláš)") a pod ním miesto uloží. Kontrola
        // duplicity sa však pýtala len na surový názov, takže existujúci
        // záznam nikdy nenašla a každý nočný beh importu založil ďalší.
        config()->set('services.imports.detect_canal_with_ai', true);
        config()->set('services.imports.describe_with_ai', false);

        $canal = Canal::factory()->create();
        $municipality = Municipality::query()->first();

        $manager = new ImportedVenueManager(
            $this->detectorReturning('Evanjelický kostol (Liptovský Mikuláš)', (int) $municipality?->id)
        );

        $rawName = 'Evanjelickom kostole v Liptovskom Mikuláši';

        $first = $manager->resolveOrDetect($canal, $rawName, 'Liptovský Mikuláš');
        $countAfterFirst = Venue::query()->count();

        $second = $manager->resolveOrDetect($canal, $rawName, 'Liptovský Mikuláš');
        $third = $manager->resolveOrDetect($canal, $rawName, 'Liptovský Mikuláš');

        $this->assertSame('Evanjelický kostol (Liptovský Mikuláš)', $first->name);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame($countAfterFirst, Venue::query()->count(), 'Ďalší beh importu nesmie založiť duplikát.');
    }

    #[Test]
    public function it_keeps_same_named_venues_in_different_towns_apart(): void
    {
        // Zátvorka s mestom je rozlišovač, nie alias — zhoda na "holom" slugu
        // sa preto nesmie preniesť cez hranicu obce.
        config()->set('services.imports.detect_canal_with_ai', false);
        config()->set('services.imports.describe_with_ai', false);

        $canal = Canal::factory()->create();
        [$first, $second] = Municipality::query()->orderBy('id')->take(2)->get()->all();

        $bratislava = Venue::factory()->create([
            'name' => 'Evanjelický kostol (Bratislava)',
            'slug' => 'evanjelicky-kostol-bratislava',
            'category' => null,
            'village_id' => $first->id,
        ]);

        $manager = new ImportedVenueManager(
            $this->detectorReturning('Evanjelický kostol (Liptovský Mikuláš)', (int) $second->id)
        );

        config()->set('services.imports.detect_canal_with_ai', true);
        $resolved = $manager->resolveOrDetect($canal, 'Evanjelickom kostole v Liptovskom Mikuláši', 'Liptovský Mikuláš');

        $this->assertNotSame($bratislava->id, $resolved->id);
    }

    private function detectorReturning(string $name, int $villageId): Detector
    {
        return new class($name, $villageId) extends Detector {
            public function __construct(private readonly string $venueName, private readonly int $villageId)
            {
                parent::__construct();
            }

            public function detectVenueDetails(string $name, string $city, ?string $country = null): array
            {
                return [
                    'can_store_immediately' => true,
                    'venue_store_payload' => [
                        'village_id' => $this->villageId,
                        'name' => $this->venueName,
                        'body' => 'Popis miesta.',
                        'country' => 'Slovensko',
                    ],
                ];
            }
        };
    }

    #[Test]
    public function it_does_not_reuse_the_shared_fallback_venue_as_a_named_match(): void
    {
        config()->set('services.imports.detect_canal_with_ai', false);
        config()->set('services.imports.describe_with_ai', false);

        $canal = Canal::factory()->create();
        $fallback = app(ImportedVenueManager::class)->resolveFallbackVenue();

        $resolved = app(ImportedVenueManager::class)->resolveOrDetect($canal, $fallback->name, null);

        $this->assertNotSame($fallback->id, $resolved->id, 'Zberné miesto sa nesmie priradiť podľa názvu.');
    }
}
