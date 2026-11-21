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

class CompleteCanalsVenuesOf17NovemberMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_23_100000_complete_canals_venues_of_17_november.php';

    #[Test]
    public function it_completes_imported_canal_and_venue_and_keeps_claimed_canal(): void
    {
        $this->municipality(73, 'banska-bystrica', 'Banská Bystrica');
        $this->municipality(1623, 'krajne', 'Krajné');

        $canal = Canal::factory()->create([
            'id' => 1971,
            'name' => 'CZ ECAV Banská Bystrica',
            'body' => '<p>CZ ECAV Banská Bystrica je cirkevný zbor.</p>',
            'phone' => null,
            'website' => null,
            'street' => null,
            'municipality_id' => 73,
            'coordinates_source' => 'municipality',
            'registration_source' => RegistrationSource::IMPORT->value,
            'claimed_at' => null,
        ]);
        $claimed = Canal::factory()->create([
            'id' => 1972,
            'name' => 'CZ ECAV Krajné',
            'body' => '<p>Vlastný popis.</p>',
            'municipality_id' => 1623,
            'claimed_at' => now(),
        ]);
        $venue = Venue::factory()->create([
            'id' => 1094,
            'name' => 'Evanjelický chrám Boží',
            'status' => ModelStatus::Published,
            'village_id' => 73,
            'street' => null,
            'postcode' => null,
            'body' => '<p>Krátky popis.</p>',
            'coordinates_source' => 'municipality',
        ]);

        (require database_path(self::MIGRATION))->up();

        $canal->refresh();
        $this->assertSame(3, substr_count($canal->body, '<p>'));
        $this->assertSame('048/415 51 16', $canal->phone);
        $this->assertSame('Lazovná 42', $canal->street);
        $this->assertSame('address', $canal->coordinates_source);
        $this->assertContains('banskabystrica@ecav.sk', $canal->emails()->pluck('email')->all());

        $this->assertSame('<p>Vlastný popis.</p>', $claimed->fresh()->body);

        $venue->refresh();
        $this->assertSame('Evanjelický chrám Boží (Banská Bystrica)', $venue->name);
        $this->assertSame('Lazovná 268/46', $venue->street);
        $this->assertSame(3, substr_count($venue->body, '<p>'));
        $this->assertSame('address', $venue->coordinates_source);
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
