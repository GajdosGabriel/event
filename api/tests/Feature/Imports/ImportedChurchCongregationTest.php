<?php

namespace Tests\Feature\Imports;

use App\Models\Municipality;
use App\Services\Imports\ImportedCanalNameResolver;
use App\Services\OpenAI\ChatGPT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Holý názov cirkvi so zbormi („Cirkev bratská") pri podujatí v obci patrí
 * tamojšiemu zboru — inak by všetky zbory skončili v jednom kanáli celej
 * cirkvi a skloňované tvary („Cirkvi bratskej") v ďalších.
 */
class ImportedChurchCongregationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.imports.detect_canal_with_ai', true);
        Cache::flush();

        if (! Municipality::query()->where('fullname', 'Prešov')->exists()) {
            DB::table('municipalities')->insert([
                'fullname' => 'Prešov',
                'shortname' => 'Prešov',
                'zip' => '08001',
                'district_id' => 1,
                'region_id' => 1,
                'use' => true,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function a_bare_church_with_a_known_city_becomes_its_congregation(): void
    {
        $resolved = $this->resolve(['name' => 'Cirkvi bratskej'], ['name' => 'Modlitebňa', 'city' => 'Prešov']);

        $this->assertSame('Cirkev bratská Prešov', $resolved['detected_name']);
    }

    #[Test]
    public function an_inflected_congregation_city_is_turned_into_the_catalog_name(): void
    {
        $resolved = $this->resolve(['name' => 'zbor Cirkvi bratskej v Prešove'], ['name' => 'Modlitebňa', 'city' => 'Prešove']);

        $this->assertSame('Cirkev bratská Prešov', $resolved['detected_name']);
    }

    #[Test]
    public function a_church_venue_without_organizer_gives_the_congregation(): void
    {
        $resolved = $this->resolve(null, ['name' => 'Modlitebňa Cirkvi bratskej', 'city' => 'Prešov']);

        $this->assertSame('Cirkev bratská Prešov', $resolved['detected_name']);
    }

    #[Test]
    public function without_a_city_the_whole_church_stays(): void
    {
        $resolved = $this->resolve(['name' => 'Cirkvi bratskej'], null);

        $this->assertSame('Cirkev bratská', $resolved['detected_name']);
    }

    #[Test]
    public function a_city_district_is_not_mistaken_for_another_municipality(): void
    {
        $resolved = $this->resolve(['name' => 'Cirkev bratská Palisády'], ['name' => 'Modlitebňa', 'city' => 'Prešov']);

        $this->assertSame('Cirkev bratská Palisády', $resolved['detected_name']);
    }

    /**
     * @param  array<string, mixed>|null  $organizer
     * @param  array<string, mixed>|null  $venue
     * @return array<string, mixed>
     */
    private function resolve(?array $organizer, ?array $venue): array
    {
        $chatGPT = Mockery::mock(ChatGPT::class);
        $chatGPT->shouldReceive('extractData')->andReturn([
            'organizer' => $organizer,
            'venue' => $venue,
        ]);

        return (new ImportedCanalNameResolver($chatGPT))->resolve(
            'https://www.example.sk/clanok',
            'Stretnutie s Bibliou',
            'Pozývame na stretnutie s Bibliou.',
        );
    }
}
