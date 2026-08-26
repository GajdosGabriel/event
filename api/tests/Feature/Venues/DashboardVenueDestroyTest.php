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

    /**
     * Referenčný zámok musí byť vidieť aj v odpovedi výpisu, inak by UI ponúklo
     * tlačidlo, ktoré vždy skončí na 422.
     */
    #[Test]
    public function index_explains_why_a_used_venue_cannot_be_deleted(): void
    {
        $this->user->givePermissionTo(['venue.delete', 'venue.update']);

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);

        Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
        ]);

        $row = collect($this->getJson('/api/dashboard/venues')->assertOk()->json('data'))
            ->firstWhere('id', $venue->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['permissions']['delete']);
        $this->assertNotNull($row['delete_blocked_reason']);
    }

    /**
     * Stav o mazaní nerozhoduje — rozhoduje história. Publikované miesto, ktoré
     * nikto nepoužil, sa zmazať dá; predtým to policy odmietla len preto, že
     * bolo `published`, a stačilo ho prepnúť na koncept. Viď VenuePolicy::delete().
     */
    #[Test]
    public function owner_can_delete_published_venue_that_no_event_used(): void
    {
        $this->user->givePermissionTo('venue.delete');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Published->value,
        ]);

        $this->deleteJson('/api/dashboard/venues/' . $venue->id)->assertStatus(204);

        $this->assertSoftDeleted('venues', ['id' => $venue->id]);
    }

    /**
     * Archív mazanie neblokuje. Blokovala ho história, a tú archivované miesto
     * bez podujatí nemá — predtým stačilo prepnúť stav na koncept a zmazať ho
     * aj tak, len o krok navyše.
     */
    #[Test]
    public function owner_can_delete_archived_venue_that_no_event_used(): void
    {
        $this->user->givePermissionTo('venue.delete');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Archived->value,
        ]);

        $this->deleteJson('/api/dashboard/venues/' . $venue->id)->assertStatus(204);

        $this->assertSoftDeleted('venues', ['id' => $venue->id]);
    }

    /** Zámkom ostáva história — na archivovanom mieste platí rovnako. */
    #[Test]
    public function archived_venue_used_by_an_event_still_cannot_be_deleted(): void
    {
        $this->user->givePermissionTo('venue.delete');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Archived->value,
        ]);

        Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
        ]);

        $this->deleteJson('/api/dashboard/venues/' . $venue->id)->assertStatus(422);

        $this->assertNotSoftDeleted('venues', ['id' => $venue->id]);
    }

    /**
     * Archív pri mieste znamená „mimo prevádzky", nie zmrazený záznam — inak by
     * sa archivované miesto nedalo ani vrátiť späť medzi publikované.
     */
    #[Test]
    public function archived_venue_can_still_be_edited(): void
    {
        $this->user->givePermissionTo('venue.update');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Archived->value,
        ]);

        $this->putJson('/api/dashboard/venues/' . $venue->id, [
            'name' => $venue->name,
            'village_id' => $venue->village_id,
            'canal_id' => $this->canalPrimary->id,
            'status' => ModelStatus::Published->value,
        ])->assertOk();

        $this->assertSame(ModelStatus::Published, $venue->fresh()->status);
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

