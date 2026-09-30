<?php

namespace Tests\Feature\Venues;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\User;
use App\Models\Venue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class VenueOwnerTest extends EventSetupTest
{
    private function payload(array $extra = []): array
    {
        return array_merge([
            'village_id' => (int) $this->canalPrimary->municipality_id,
            'name' => 'Sála pre prenájom',
            'status' => ModelStatus::Draft->value,
        ], $extra);
    }

    #[Test]
    public function saving_owner_keeps_links_of_canals_that_only_use_the_venue(): void
    {
        $this->user->givePermissionTo('venue.update');
        $tenant = Canal::factory()->create();

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create(['status' => ModelStatus::Draft->value]);
        $venue->assignCanal($tenant->id);

        $this->putJson('/api/dashboard/venues/'.$venue->id, $this->payload(['owner_canal_id' => $this->canalPrimary->id]))
            ->assertOk();

        $this->assertDatabaseHas('canal_venue', ['venue_id' => $venue->id, 'canal_id' => $tenant->id, 'is_owner' => false]);
        $this->assertDatabaseHas('canal_venue', ['venue_id' => $venue->id, 'canal_id' => $this->canalPrimary->id, 'is_owner' => true]);
    }

    #[Test]
    public function dashboard_user_must_pick_a_canal_for_a_new_venue(): void
    {
        $this->user->givePermissionTo('venue.create');

        $this->postJson('/api/dashboard/venues', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('canal_id');
    }

    #[Test]
    public function owner_canal_id_alone_creates_the_venue_with_that_owner(): void
    {
        $this->user->givePermissionTo('venue.create');

        $id = $this->postJson('/api/dashboard/venues', $this->payload(['owner_canal_id' => $this->canalPrimary->id]))
            ->assertStatus(201)
            ->json('id');

        $this->assertDatabaseHas('canal_venue', ['venue_id' => $id, 'canal_id' => $this->canalPrimary->id, 'is_owner' => true]);
    }

    #[Test]
    public function assign_owner_moves_ownership_and_null_removes_it_without_dropping_links(): void
    {
        $previous = Canal::factory()->create();
        $next = Canal::factory()->create();
        $venue = Venue::factory()->forCanal($previous->id)->create();

        $venue->assignOwner($next->id);

        $this->assertSame([$next->id], $venue->ownerCanals()->pluck('canals.id')->all());
        $this->assertDatabaseHas('canal_venue', ['venue_id' => $venue->id, 'canal_id' => $previous->id, 'is_owner' => false]);

        $venue->assignOwner(null);

        $this->assertSame(0, $venue->ownerCanals()->count());
        $this->assertSame(2, $venue->canals()->count());
        $this->assertNull($venue->fresh()->messageRecipient());
    }

    #[Test]
    public function admin_index_can_list_only_venues_without_a_manager(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'sanctum');

        $managed = Venue::factory()->forCanal($this->canalPrimary->id)->create();
        $orphan = Venue::factory()->forCanal($this->canalPrimary->id, false)->create();

        $ids = collect($this->getJson('/api/admin/venues?without_owner=1&per_page=100')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($orphan->id));
        $this->assertFalse($ids->contains($managed->id));
    }
}
