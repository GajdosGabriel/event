<?php

namespace Tests\Feature\Venues;

use App\Models\Canal;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class VenueMunicipalitiesOverviewTest extends EventSetupTest
{
    #[Test]
    public function admin_venue_municipality_overview_counts_venues_by_village(): void
    {
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $municipalityId = $this->createMunicipality('Test Admin Venue Municipality');
        $canal = Canal::factory()->create(['municipality_id' => $municipalityId]);

        Venue::factory()->count(2)->forCanal($canal->id)->create([
            'village_id' => $municipalityId,
        ]);
        Venue::factory()->forCanal($canal->id)->create();

        $response = $this->getJson('/api/admin/venues/municipalities-overview');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.resource', 'venues');

        $row = collect($response->json('data'))
            ->firstWhere('municipality_id', $municipalityId);

        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['events_count']);
    }

    #[Test]
    public function dashboard_venue_municipality_overview_counts_only_accessible_venues(): void
    {
        $municipalityId = $this->createMunicipality('Test Dashboard Venue Municipality');

        $accessibleVenue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'village_id' => $municipalityId,
        ]);

        $foreignCanal = Canal::factory()->create(['municipality_id' => $municipalityId]);
        Venue::factory()->forCanal($foreignCanal->id)->create([
            'village_id' => $municipalityId,
        ]);

        $response = $this->getJson('/api/dashboard/venues/municipalities-overview');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.resource', 'venues');

        $row = collect($response->json('data'))
            ->firstWhere('municipality_id', $municipalityId);

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['events_count']);
        $this->assertDatabaseHas('venues', [
            'id' => $accessibleVenue->id,
            'village_id' => $municipalityId,
        ]);
    }

    private function createMunicipality(string $name): int
    {
        return (int) DB::table('municipalities')->insertGetId([
            'fullname' => $name . ' ' . uniqid(),
            'shortname' => 'TVM ' . random_int(100, 999),
            'zip' => '01001',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
