<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class AdminEventMunicipalitiesOverviewTest extends EventSetupTest
{
    #[Test]
    public function admin_event_municipality_overview_filters_planned_scope(): void
    {
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $scheduledMunicipalityId = (int) DB::table('municipalities')->insertGetId([
            'fullname' => 'Test Scheduled Municipality ' . uniqid(),
            'shortname' => 'TSM ' . random_int(100, 999),
            'zip' => '01001',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $draftMunicipalityId = (int) DB::table('municipalities')->insertGetId([
            'fullname' => 'Test Draft Municipality ' . uniqid(),
            'shortname' => 'TDM ' . random_int(100, 999),
            'zip' => '01002',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduledCanal = Canal::factory()->create(['municipality_id' => $scheduledMunicipalityId]);
        $scheduledVenue = Venue::factory()->create([
            'canal_id' => $scheduledCanal->id,
            'village_id' => $scheduledMunicipalityId,
        ]);

        Event::factory()->future()->create([
            'canal_id' => $scheduledCanal->id,
            'venue_id' => $scheduledVenue->id,
            'status' => ModelStatus::Scheduled->value,
            'user_id' => $this->userSuperAdmin->id,
        ]);

        $draftCanal = Canal::factory()->create(['municipality_id' => $draftMunicipalityId]);
        $draftVenue = Venue::factory()->create([
            'canal_id' => $draftCanal->id,
            'village_id' => $draftMunicipalityId,
        ]);

        Event::factory()->future()->create([
            'canal_id' => $draftCanal->id,
            'venue_id' => $draftVenue->id,
            'status' => ModelStatus::Draft->value,
            'user_id' => $this->userSuperAdmin->id,
        ]);

        $response = $this->getJson('/api/admin/events/municipalities-overview?scope=planned');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.scope', 'planned');

        $municipalityIds = collect($response->json('data'))->pluck('municipality_id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($scheduledMunicipalityId, $municipalityIds);
        $this->assertNotContains($draftMunicipalityId, $municipalityIds);
    }
}
