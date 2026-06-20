<?php

namespace Tests\Feature\Canal;

use App\Enums\ModelStatus;

use App\Models\Canal;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class AdminCanalMunicipalitiesOverviewTest extends EventSetupTest
{
    #[Test]
    public function admin_canal_municipality_overview_counts_canals_by_canal_municipality(): void
    {
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $municipalityId = (int) DB::table('municipalities')->insertGetId([
            'fullname' => 'Test Canal Municipality ' . uniqid(),
            'shortname' => 'TCM ' . random_int(100, 999),
            'zip' => '01001',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Canal::factory()->count(2)->create(['municipality_id' => $municipalityId]);
        Canal::factory()->create();

        $response = $this->getJson('/api/admin/canals/municipalities-overview');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.resource', 'canals');

        $row = collect($response->json('data'))
            ->firstWhere('municipality_id', $municipalityId);

        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['events_count']);
    }

    #[Test]
    public function dashboard_canal_municipality_overview_counts_only_accessible_canals(): void
    {
        $municipalityId = (int) DB::table('municipalities')->insertGetId([
            'fullname' => 'Test Dashboard Canal Municipality ' . uniqid(),
            'shortname' => 'TDCM ' . random_int(100, 999),
            'zip' => '01002',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accessibleCanals = Canal::factory()->count(2)->create(['municipality_id' => $municipalityId]);

        foreach ($accessibleCanals as $canal) {
            $this->user->canals()->attach($canal->id, [
                'is_owner' => true,
                'status' => ModelStatus::Published->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Canal::factory()->create(['municipality_id' => $municipalityId]);

        $response = $this->getJson('/api/dashboard/canals/municipalities-overview');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.resource', 'canals');

        $row = collect($response->json('data'))
            ->firstWhere('municipality_id', $municipalityId);

        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['events_count']);
    }
}

