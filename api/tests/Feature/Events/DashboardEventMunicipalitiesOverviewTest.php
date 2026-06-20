<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardEventMunicipalitiesOverviewTest extends EventSetupTest
{
    #[Test]
    public function dashboard_event_municipality_overview_returns_only_user_accessible_canals(): void
    {
        $primaryVenue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $this->canalPrimary->id))
            ->firstOrFail();

        Event::factory()->future()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $primaryVenue->id,
            'status' => ModelStatus::Scheduled->value,
            'user_id' => $this->user->id,
        ]);

        $foreignMunicipalityId = (int) (DB::table('municipalities')
            ->where('id', '!=', $primaryVenue->village_id)
            ->value('id') ?? $primaryVenue->village_id);

        $foreignCanal = Canal::factory()->create([
            'municipality_id' => $foreignMunicipalityId,
        ]);

        $foreignVenue = Venue::factory()->create([
            'canal_id' => $foreignCanal->id,
            'village_id' => $foreignMunicipalityId,
        ]);

        Event::factory()->future()->create([
            'canal_id' => $foreignCanal->id,
            'venue_id' => $foreignVenue->id,
            'status' => ModelStatus::Scheduled->value,
        ]);

        $response = $this->getJson('/api/dashboard/events/municipalities-overview?scope=planned');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.scope', 'planned');

        $municipalityIds = collect($response->json('data'))->pluck('municipality_id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $primaryVenue->village_id, $municipalityIds);
        $this->assertNotContains($foreignMunicipalityId, $municipalityIds);
    }
}
