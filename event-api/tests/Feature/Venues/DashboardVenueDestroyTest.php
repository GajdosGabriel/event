<?php

namespace Tests\Feature\Venues;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardVenueDestroyTest extends EventSetupTest
{
    #[Test]
    public function owner_can_delete_unused_venue_from_dashboard_scope(): void
    {
        $this->user->givePermissionTo('venue.delete');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);

        $response = $this->deleteJson('/api/dashboard/venues/' . $venue->id);

        $response->assertStatus(204);

        $this->assertSoftDeleted('venues', [
            'id' => $venue->id,
        ]);
    }

    #[Test]
    public function owner_cannot_delete_venue_that_was_used_by_event(): void
    {
        $this->user->givePermissionTo('venue.delete');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);

        Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson('/api/dashboard/venues/' . $venue->id);

        $response->assertStatus(422);

        $this->assertNotSoftDeleted('venues', [
            'id' => $venue->id,
        ]);
    }

    #[Test]
    public function non_owner_can_unlink_venue_from_dashboard_scope(): void
    {
        $this->user->givePermissionTo('venue.delete');

        $ownerCanal = Canal::factory()->create();
        $venue = Venue::factory()->forCanal($ownerCanal->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);
        $venue->assignCanal($this->canalPrimary->id, isOwner: false);

        $response = $this->deleteJson('/api/dashboard/venues/' . $venue->id);

        $response->assertStatus(204);

        $this->assertNotSoftDeleted('venues', [
            'id' => $venue->id,
        ]);
        $this->assertDatabaseMissing('canal_venue', [
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
        ]);
        $this->assertDatabaseHas('canal_venue', [
            'canal_id' => $ownerCanal->id,
            'venue_id' => $venue->id,
            'is_owner' => true,
        ]);
    }

    #[Test]
    public function non_owner_can_unlink_venue_that_was_used_by_event(): void
    {
        $this->user->givePermissionTo('venue.delete');

        $ownerCanal = Canal::factory()->create();
        $venue = Venue::factory()->forCanal($ownerCanal->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);
        $venue->assignCanal($this->canalPrimary->id, isOwner: false);

        Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson('/api/dashboard/venues/' . $venue->id);

        $response->assertStatus(204);

        $this->assertNotSoftDeleted('venues', [
            'id' => $venue->id,
        ]);
        $this->assertFalse(
            DB::table('canal_venue')
                ->where('canal_id', $this->canalPrimary->id)
                ->where('venue_id', $venue->id)
                ->exists()
        );
    }

    #[Test]
    public function user_cannot_delete_foreign_venue_from_dashboard_scope(): void
    {
        $this->user->givePermissionTo('venue.delete');

        $foreignUser = User::factory()->create();
        $foreignCanal = Canal::factory()->create();
        $foreignUser->canals()->attach($foreignCanal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignVenue = Venue::factory()->forCanal($foreignCanal->id)->create();

        $response = $this->deleteJson('/api/dashboard/venues/' . $foreignVenue->id);

        $response->assertStatus(404);

        $this->assertNotSoftDeleted('venues', [
            'id' => $foreignVenue->id,
        ]);
    }
}

