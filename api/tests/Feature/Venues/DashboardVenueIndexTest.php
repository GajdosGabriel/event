<?php

namespace Tests\Feature\Venues;

use App\Enums\ModelStatus;

use App\Models\Canal;
use App\Models\User;
use App\Models\Venue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardVenueIndexTest extends EventSetupTest
{
    #[Test]
    public function user_can_see_only_venues_from_owned_canals(): void
    {
        $canalVenue = Venue::factory()->forCanal($this->canalPrimary->id)->create();
        $secondOwnedCanal = Canal::factory()->create();
        $this->user->canals()->attach($secondOwnedCanal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $secondOwnedVenue = Venue::factory()->forCanal($secondOwnedCanal->id)->create();

        $foreignUser = User::factory()->create();
        $foreignCanal = Canal::factory()->create();
        $foreignUser->canals()->attach($foreignCanal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignVenue = Venue::factory()->forCanal($foreignCanal->id)->create();

        $response = $this->getJson('/api/dashboard/venues');

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $canalVenue->id]);
        $response->assertJsonFragment(['id' => $secondOwnedVenue->id]);
        $response->assertJsonMissing(['id' => $foreignVenue->id]);
    }

    #[Test]
    public function search_finds_venues_from_entire_project(): void
    {
        $foreignUser = User::factory()->create();
        $foreignCanal = Canal::factory()->create();
        $foreignUser->canals()->attach($foreignCanal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignVenue = Venue::factory()->forCanal($foreignCanal->id)->create([
            'name' => 'Projektove Kulturne Centrum Zeta',
        ]);

        $this->getJson('/api/dashboard/venues')
            ->assertStatus(200)
            ->assertJsonMissing(['id' => $foreignVenue->id]);

        $this->getJson('/api/dashboard/venues?page=1&search=zeta')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $foreignVenue->id]);
    }
}

