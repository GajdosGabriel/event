<?php

namespace Tests\Feature\Venues;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Venue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Zoznam podujatí na detaile miesta a kanála má vlastné menu akcií. Kým
 * neposielal práva, ponúkal „Upraviť" aj tam, kde ho policy zamieta —
 * archivovanému podujatiu. Rovnaké pravidlo ako vo výpisoch: čo sa nedá
 * spraviť, to sa neponúka.
 */
class NestedEventActionsTest extends EventSetupTest
{
    #[Test]
    public function venue_event_list_carries_permissions(): void
    {
        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);

        $draft = Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
            'status' => ModelStatus::Draft->value,
        ]);

        $archived = Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
            'status' => ModelStatus::Archived->value,
        ]);

        $rows = collect($this->getJson('/api/dashboard/venues/' . $venue->id . '/events')
            ->assertOk()
            ->json())
            ->keyBy('id');

        $this->assertTrue($rows[$draft->id]['permissions']['view']);
        $this->assertTrue($rows[$draft->id]['permissions']['update']);

        // Archivované sa upravovať nedá (DeniesArchivedUpdate) — menu preto
        // nesmie ponúknuť „Upraviť".
        $this->assertTrue($rows[$archived->id]['permissions']['view']);
        $this->assertFalse($rows[$archived->id]['permissions']['update']);
    }

    #[Test]
    public function canal_event_list_carries_permissions(): void
    {
        $archived = Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'user_id' => $this->user->id,
            'status' => ModelStatus::Archived->value,
        ]);

        $row = collect($this->getJson('/api/dashboard/canals/' . $this->canalPrimary->id . '/events')
            ->assertOk()
            ->json())
            ->firstWhere('id', $archived->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['permissions']['view']);
        $this->assertFalse($row['permissions']['update']);
    }

    /**
     * Zamknuté odpublikovanie nesmie prísť ako právo — menu by inak ponúklo
     * tlačidlo, ktoré vždy skončí na 422.
     */
    #[Test]
    public function used_published_venue_offers_neither_unpublish_nor_delete(): void
    {
        $this->user->givePermissionTo(['venue.delete', 'venue.update']);

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Published->value,
        ]);

        Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
            'status' => ModelStatus::Draft->value,
        ]);

        $row = collect($this->getJson('/api/dashboard/venues')->assertOk()->json('data'))
            ->firstWhere('id', $venue->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['permissions']['unpublish']);
        $this->assertFalse($row['permissions']['delete']);
        // Živý záznam sa neobnovuje — obnova patrí len tomu v koši.
        $this->assertFalse($row['permissions']['restore']);
    }

    #[Test]
    public function deleted_venue_offers_restore_instead_of_delete(): void
    {
        $this->user->givePermissionTo(['venue.delete', 'venue.update']);

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);
        $venue->delete();

        $row = collect($this->getJson('/api/dashboard/venues?deleted=1')->assertOk()->json('data'))
            ->firstWhere('id', $venue->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['permissions']['restore']);
        $this->assertFalse($row['permissions']['delete']);
    }
}
