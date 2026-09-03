<?php

namespace Tests\Feature\Venues;

use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class PublicVenueShowTest extends EventSetupTest
{
    #[Test]
    public function public_venue_detail_carries_municipality(): void
    {
        $name = 'Test Public Venue Municipality ' . uniqid();
        $municipalityId = $this->createMunicipality($name);

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'village_id' => $municipalityId,
            'street' => 'Panská 261/19',
            'postcode' => '811 01',
        ]);

        $response = $this->getJson("/api/venues/{$venue->id}");

        $response->assertStatus(200);
        // Front skladá adresu „ulica, PSČ obec" — bez tohto kľúča z nej ostane
        // len ulica a PSČ.
        $response->assertJsonPath('municipality.id', $municipalityId);
        $response->assertJsonPath('municipality.name', $name);
    }

    private function createMunicipality(string $name): int
    {
        return (int) DB::table('municipalities')->insertGetId([
            'fullname' => $name,
            'shortname' => 'TPV ' . random_int(100, 999),
            'zip' => '01001',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
