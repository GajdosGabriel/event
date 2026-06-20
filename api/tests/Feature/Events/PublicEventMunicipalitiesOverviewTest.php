<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicEventMunicipalitiesOverviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_event_municipality_overview_returns_only_planned_public_events(): void
    {
        $user = User::factory()->create();
        $plannedMunicipalityId = (int) DB::table('municipalities')->value('id');

        $plannedCanal = Canal::factory()->create([
            'municipality_id' => $plannedMunicipalityId,
        ]);

        $plannedVenue = Venue::factory()->create([
            'canal_id' => $plannedCanal->id,
            'village_id' => $plannedMunicipalityId,
        ]);

        Event::factory()->future()->create([
            'canal_id' => $plannedCanal->id,
            'venue_id' => $plannedVenue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subHour(),
            'user_id' => $user->id,
        ]);

        $pastMunicipalityId = (int) (DB::table('municipalities')
            ->where('id', '!=', $plannedVenue->village_id)
            ->value('id') ?? $plannedVenue->village_id);

        $pastCanal = Canal::factory()->create([
            'municipality_id' => $pastMunicipalityId,
        ]);

        $pastVenue = Venue::factory()->create([
            'canal_id' => $pastCanal->id,
            'village_id' => $pastMunicipalityId,
        ]);

        Event::factory()->past()->create([
            'canal_id' => $pastCanal->id,
            'venue_id' => $pastVenue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subDay(),
            'user_id' => $user->id,
        ]);

        Event::factory()->future()->create([
            'canal_id' => $pastCanal->id,
            'venue_id' => $pastVenue->id,
            'status' => ModelStatus::Draft->value,
            'published_at' => null,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/events/municipalities-overview?scope=planned');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.scope', 'planned');

        $municipalityIds = collect($response->json('data'))
            ->pluck('municipality_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $plannedVenue->village_id, $municipalityIds);
        $this->assertNotContains($pastMunicipalityId, $municipalityIds);
    }
}
