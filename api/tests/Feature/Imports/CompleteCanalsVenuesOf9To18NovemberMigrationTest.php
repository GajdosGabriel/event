<?php

namespace Tests\Feature\Imports;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompleteCanalsVenuesOf9To18NovemberMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_24_100000_complete_canals_venues_of_9_to_18_november.php';

    #[Test]
    public function it_completes_canals_and_venues_and_merges_import_fragments(): void
    {
        $this->municipality(1455, 'klokocov', 'Klokočov');
        $this->municipality(1456, 'klokocov-1', 'Klokočov');
        $this->municipality(2636, 'pezinok', 'Pezinok');

        $emanuel = Canal::factory()->create([
            'id' => 1950,
            'name' => 'Emanuel',
            'municipality_id' => 1455,
            'coordinates_source' => 'municipality',
            'latitude' => 48.7411522,
            'longitude' => 19.4528646,
            'registration_source' => RegistrationSource::IMPORT->value,
            'claimed_at' => null,
        ]);
        $coach = Canal::factory()->create([
            'id' => 1974,
            'name' => 'Škola Kresťanský kouč',
            'body' => '<p>Škola Kresťanský kouč — organizátor podujatí.</p>',
            'website' => 'http://www.krestanskykouc.sk',
            'municipality_id' => 2636,
            'coordinates_source' => 'municipality',
            'latitude' => 48.7411522,
            'longitude' => 19.4528646,
            'registration_source' => RegistrationSource::IMPORT->value,
            'claimed_at' => null,
        ]);
        $claimed = Canal::factory()->create([
            'id' => 1952,
            'name' => 'Premonštráti',
            'municipality_id' => 1565,
            'street' => null,
            'claimed_at' => now(),
        ]);

        $monastery = Venue::factory()->create([
            'id' => 11,
            'name' => 'kombinovane online a Kláštor kapucínov v Pezinku',
            'status' => ModelStatus::Published,
            'village_id' => 2636,
            'street' => null,
            'body' => 'Kláštor kapucínov v Pezinku.',
            'coordinates_source' => 'municipality',
        ]);
        $fragment = Venue::factory()->create([
            'id' => 1101,
            'name' => 'Kláštor kapucínov a online',
            'status' => ModelStatus::Published,
            'village_id' => 2636,
            'coordinates_source' => 'municipality',
        ]);

        (require database_path(self::MIGRATION))->up();

        $emanuel->refresh();
        $this->assertSame(1456, (int) $emanuel->municipality_id);
        $this->assertEqualsWithDelta(22.0296342, (float) $emanuel->longitude, 0.0001);

        $coach->refresh();
        $this->assertSame(3, substr_count($coach->body, '<p>'));
        $this->assertSame('https://www.krestanskykouc.sk', $coach->website);
        $this->assertContains('info@krestanskykouc.sk', $coach->emails()->pluck('email')->all());

        $this->assertNull($claimed->fresh()->street);

        $monastery->refresh();
        $this->assertSame('Kláštor kapucínov (Pezinok)', $monastery->name);
        $this->assertSame('Holubyho 91', $monastery->street);
        $this->assertSame('address', $monastery->coordinates_source);
        $this->assertSame(3, substr_count($monastery->body, '<p>'));

        $this->assertNull(Venue::query()->find($fragment->id));
    }

    private function municipality(int $id, string $slug, string $name): void
    {
        if (! DB::table('municipalities')->where('id', $id)->exists()) {
            DB::table('municipalities')->insert([
                'id' => $id, 'fullname' => $name, 'shortname' => $name, 'slug' => $slug, 'zip' => '',
                'district_id' => 0, 'region_id' => 0, 'use' => true,
            ]);
        }
    }
}
