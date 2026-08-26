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

    /**
     * Importované miesto musí mať vlastnícky kanál — VenuePolicy::update() sa
     * pýta výhradne naň, takže bez neho ho v dashboarde neupraví nikto okrem
     * super-admina. Presne toto bolo na produkcii pri všetkých 113 miestach.
     */
    #[Test]
    public function a_newly_imported_venue_is_owned_by_the_canal_that_created_it(): void
    {
        config()->set('services.imports.detect_canal_with_ai', false);
        config()->set('services.imports.describe_with_ai', false);

        $canal = Canal::factory()->create();

        $venue = app(ImportedVenueManager::class)->resolveOrDetect($canal, 'Kultúrny dom Ladce', 'Ladce');

        $this->assertSame(
            [$canal->id],
            $venue->ownerCanals()->pluck('canals.id')->all(),
            'Kanál, ktorý miesto priniesol, ho musí aj vlastniť.',
        );
    }

    /** Zberné miesto je spoločné — vlastníka dostať nesmie. */
    #[Test]
    public function the_shared_fallback_venue_stays_without_an_owner(): void
    {
        config()->set('services.imports.detect_canal_with_ai', false);
        config()->set('services.imports.describe_with_ai', false);

        $canal = Canal::factory()->create();

        $fallback = app(ImportedVenueManager::class)->resolveFallbackVenueForCanal($canal);

        $this->assertSame([], $fallback->ownerCanals()->pluck('canals.id')->all());
    }

    #[Test]
    public function a_country_wide_name_lands_on_the_shared_fallback(): void
    {
        // „Celé Slovensko“ nie je obec, je to priznanie, že miesto nie je
        // známe. Kým sa taký názov púšťal do dohľadania obce, sadol na
        // číselníkový záznam 4209 a vzniklo druhé miesto s rovnakým menom
        // vedľa zberného — presne tá duplicita, ktorú má import vylúčiť.
        config()->set('services.imports.detect_canal_with_ai', false);
        config()->set('services.imports.describe_with_ai', false);

        $canal = Canal::factory()->create();
        $fallback = app(ImportedVenueManager::class)->resolveFallbackVenue();
        $before = Venue::query()->count();

        $resolved = app(ImportedVenueManager::class)->resolveOrDetect($canal, $fallback->name, null);

        $this->assertSame($fallback->id, $resolved->id);
        $this->assertSame($before, Venue::query()->count(), 'Nesmie vzniknúť druhé „Celé Slovensko“.');
    }

    #[Test]
    public function it_does_not_reuse_the_shared_fallback_venue_as_a_named_match(): void
    {
        // Poistka nad voľným LIKE v findByName: zberné miesto sa nesmie
        // priradiť ako zhoda názvu skutočného miesta, nech sa volá akokoľvek.
        config()->set('services.imports.detect_canal_with_ai', false);
        config()->set('services.imports.describe_with_ai', false);

        $canal = Canal::factory()->create();
        $fallback = app(ImportedVenueManager::class)->resolveFallbackVenue();
        $fallback->update(['name' => 'Mestské divadlo', 'slug' => 'mestske-divadlo']);

        $resolved = app(ImportedVenueManager::class)->resolveOrDetect($canal, 'Mestské divadlo', 'Nitra');

        $this->assertNotSame($fallback->id, $resolved->id, 'Zberné miesto sa nesmie priradiť podľa názvu.');
    }

    #[Test]
    public function a_trailing_town_name_does_not_create_a_second_venue(): void
    {
        // Regresia z produkcie: import raz uloží „Sanktuárium Božieho
        // Milosrdenstva“ a inokedy to isté miesto s pripísanou obcou. Zátvorku
        // baseSlug() orezávala, čiarku s obcou nie, tak vznikli dva záznamy
        // v tej istej obci (venue 43 a 99, obe v Ladcoch).
        $ladce = Municipality::query()->where('fullname', 'Ladce')->firstOrFail();

        $canal = Canal::factory()->create();
        $existing = Venue::factory()->create([
            'village_id' => $ladce->id,
            'name'       => 'Sanktuárium Božieho Milosrdenstva',
            'slug'       => 'sanktuarium-bozieho-milosrdenstva',
        ]);

        $before = Venue::query()->count();

        $resolved = app(ImportedVenueManager::class)->resolveOrDetect(
            $canal,
            'Sanktuárium Božieho Milosrdenstva, Ladce',
            'Ladce',
        );

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame($before, Venue::query()->count());
    }

    #[Test]
    public function two_similarly_named_villages_are_not_merged(): void
    {
        // Protipól predošlého testu: orezáva sa výlučne meno obce, nie
        // hocijaký spoločný prefix. „Klokoč“ a „Klokočov“ sú dve rôzne obce.
        $klokocov = Municipality::query()->where('fullname', 'Klokočov')->firstOrFail();

        $canal = Canal::factory()->create();
        Venue::factory()->create([
            'village_id' => $klokocov->id,
            'name'       => 'Klokoč',
            'slug'       => 'klokoc',
        ]);

        $resolved = app(ImportedVenueManager::class)->resolveOrDetect(
            $canal,
            'Klokočov',
            'Klokočov',
        );

        $this->assertSame('Klokočov', $resolved->name);
    }
}