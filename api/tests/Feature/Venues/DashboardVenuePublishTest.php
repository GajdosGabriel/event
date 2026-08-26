<?php

namespace Tests\Feature\Venues;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Venue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Riadok vo výpise miest ponúkal „Publikovať" už dávno, ale routa k nemu
 * neexistovala a kliknutie končilo na 404.
 */
class DashboardVenuePublishTest extends EventSetupTest
{
    #[Test]
    public function draft_venue_can_be_published(): void
    {
        $this->user->givePermissionTo('venue.update');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);

        $this->postJson('/api/dashboard/venues/' . $venue->id . '/publish')
            ->assertOk()
            ->assertJsonPath('status', ModelStatus::Published->value);

        $this->assertSame(ModelStatus::Published, $venue->fresh()->status);
    }

    /** Archív nie je jednosmerka — miesto sa z neho musí dať vrátiť von. */
    #[Test]
    public function archived_venue_can_be_published(): void
    {
        $this->user->givePermissionTo('venue.update');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Archived->value,
        ]);

        $this->postJson('/api/dashboard/venues/' . $venue->id . '/publish')->assertOk();

        $this->assertSame(ModelStatus::Published, $venue->fresh()->status);
    }

    #[Test]
    public function published_venue_can_be_unpublished(): void
    {
        $this->user->givePermissionTo('venue.update');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Published->value,
        ]);

        $this->postJson('/api/dashboard/venues/' . $venue->id . '/publish', ['published' => false])
            ->assertOk()
            ->assertJsonPath('status', ModelStatus::Draft->value);

        $this->assertSame(ModelStatus::Draft, $venue->fresh()->status);
    }

    /**
     * Miesto, ktoré už používa podujatie, sa z výpisu stiahnuť nesmie — odkaz
     * z podujatia by viedol do prázdna. Viď VenuePolicy::unpublish().
     */
    #[Test]
    public function published_venue_used_by_an_event_cannot_be_unpublished(): void
    {
        $this->user->givePermissionTo('venue.update');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Published->value,
        ]);

        Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
        ]);

        $this->postJson('/api/dashboard/venues/' . $venue->id . '/publish', ['published' => false])
            ->assertForbidden();

        $this->assertSame(ModelStatus::Published, $venue->fresh()->status);
    }

    /** Rovnaký zámok drží aj druhá cesta k stavu — <select> vo formulári. */
    #[Test]
    public function form_cannot_unpublish_a_venue_used_by_an_event(): void
    {
        $this->user->givePermissionTo('venue.update');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Published->value,
        ]);

        Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
        ]);

        $this->putJson('/api/dashboard/venues/' . $venue->id, [
            'name' => $venue->name,
            'canal_id' => $this->canalPrimary->id,
            'village_id' => $venue->village_id,
            'status' => ModelStatus::Draft->value,
        ])->assertStatus(422);

        $this->assertSame(ModelStatus::Published, $venue->fresh()->status);
    }

    #[Test]
    public function published_venue_cannot_be_published_again(): void
    {
        $this->user->givePermissionTo('venue.update');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Published->value,
        ]);

        $this->postJson('/api/dashboard/venues/' . $venue->id . '/publish')->assertForbidden();
    }
}
