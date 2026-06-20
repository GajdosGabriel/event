<?php

namespace Tests\Feature\Venues;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Venue;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Str;
use Tests\TestSupport\EventSetupTest;

class DashboardVenueUpdateTest extends EventSetupTest
{
    private function validUpdatePayload(int $canalId, string $name, string $body): array
    {
        return [
            'canal_id' => $canalId,
            'village_id' => (int) $this->canalPrimary->municipality_id,
            'name' => $name,
            'body' => $body,
            'status' => ModelStatus::Draft->value,
        ];
    }

    #[Test]
    public function owner_can_update_owned_venue_from_dashboard_scope(): void
    {
        $this->user->givePermissionTo('venue.update');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);
        $payload = $this->validUpdatePayload(
            $this->canalPrimary->id,
            'Updated Venue ' . Str::random(5),
            'Updated venue body ' . Str::random(20),
        );

        $response = $this->putJson('/api/dashboard/venues/' . $venue->id, $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('venues', [
            'id' => $venue->id,
            'name' => $payload['name'],
            'body' => $payload['body'],
        ]);
    }

    #[Test]
    public function non_owner_can_view_but_cannot_update_venue(): void
    {
        $this->user->givePermissionTo('venue.update');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);
        $venue->canals()->updateExistingPivot($this->canalPrimary->id, ['is_owner' => false]);

        $payload = $this->validUpdatePayload(
            $this->canalPrimary->id,
            'Member Update Venue ' . Str::random(5),
            'Member update body ' . Str::random(20),
        );

        $this->getJson('/api/dashboard/venues/' . $venue->id)
            ->assertStatus(200);

        $response = $this->putJson('/api/dashboard/venues/' . $venue->id, $payload);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('venues', [
            'id' => $venue->id,
            'name' => $payload['name'],
        ]);
    }

    #[Test]
    public function owner_can_update_soft_deleted_owned_venue_from_dashboard_scope(): void
    {
        $this->user->givePermissionTo('venue.update');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);
        $venue->delete();

        $payload = $this->validUpdatePayload(
            $this->canalPrimary->id,
            'Soft Deleted Venue ' . Str::random(5),
            'Updated soft deleted venue body ' . Str::random(20),
        );

        $response = $this->putJson('/api/dashboard/venues/' . $venue->id, $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('venues', [
            'id' => $venue->id,
            'name' => $payload['name'],
            'body' => $payload['body'],
        ]);

        $this->assertSoftDeleted('venues', [
            'id' => $venue->id,
        ]);
    }

    #[Test]
    public function update_can_assign_owned_venue_to_multiple_accessible_canals(): void
    {
        $this->user->givePermissionTo('venue.update');

        $secondCanal = Canal::factory()->create();
        $this->user->canals()->attach($secondCanal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);
        $payload = $this->validUpdatePayload(
            $this->canalPrimary->id,
            'Multi Canal Venue ' . Str::random(5),
            'Updated multi canal venue body ' . Str::random(20),
        );
        unset($payload['canal_id']);
        $payload['canal_ids'] = [$this->canalPrimary->id, $secondCanal->id];

        $response = $this->putJson('/api/dashboard/venues/' . $venue->id, $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $venue->id,
            'canal_ids' => $payload['canal_ids'],
        ]);

        foreach ($payload['canal_ids'] as $canalId) {
            $this->assertDatabaseHas('canal_venue', [
                'canal_id' => $canalId,
                'venue_id' => $venue->id,
                'status' => ModelStatus::Published->value,
            ]);
        }
    }

    #[Test]
    public function update_rejects_inaccessible_canal_assignments(): void
    {
        $this->user->givePermissionTo('venue.update');

        $foreignCanal = Canal::factory()->create();
        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);
        $payload = $this->validUpdatePayload(
            $this->canalPrimary->id,
            'Forbidden Venue ' . Str::random(5),
            'Forbidden venue body ' . Str::random(20),
        );
        unset($payload['canal_id']);
        $payload['canal_ids'] = [$this->canalPrimary->id, $foreignCanal->id];

        $response = $this->putJson('/api/dashboard/venues/' . $venue->id, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['canal_ids']);

        $this->assertDatabaseMissing('venues', [
            'id' => $venue->id,
            'name' => $payload['name'],
        ]);
        $this->assertDatabaseMissing('canal_venue', [
            'canal_id' => $foreignCanal->id,
            'venue_id' => $venue->id,
        ]);
    }
}

