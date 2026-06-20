<?php

namespace Tests\Feature\Municipalities;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\UserSetupTest;

class DashboardMunicipalityDestroyTest extends UserSetupTest
{
    #[Test]
    public function user_cannot_delete_municipality_from_dashboard_scope(): void
    {
        $municipalityId = DB::table('municipalities')->insertGetId([
            'fullname' => 'Test Municipality',
            'shortname' => 'Test',
            'zip' => '01001',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson('/api/dashboard/municipalities/' . $municipalityId);

        $response->assertStatus(403);

        $this->assertDatabaseHas('municipalities', [
            'id' => $municipalityId,
        ]);
    }
}
