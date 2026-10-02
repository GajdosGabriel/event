<?php

namespace Tests\Feature\Venues;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Municipality;
use App\Models\Venue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Miesto je spoločný záznam o reálnom objekte: nájde sa vo vyhľadávaní všetkých
 * miest, pri zakladaní sa neduplikuje a spoločné miesto správca nezmaže.
 */
class SharedVenueTest extends EventSetupTest
{
    private function foreignPublishedVenue(array $attributes = []): Venue
    {
        $kosice = Municipality::query()->where('slug', 'kosice')->firstOrFail();

        return Venue::factory()->forCanal(Canal::factory()->active()->create()->id)->create([
            'name' => 'Kultúrny dom Zvláštny názov',
            'category' => null,
            'village_id' => $kosice->id,
            'status' => ModelStatus::Published->value,
            ...$attributes,
        ]);
    }

    #[Test]
    public function the_venue_picker_search_offers_published_venues_of_other_canals(): void
    {
        $foreign = $this->foreignPublishedVenue();
        $draft = $this->foreignPublishedVenue(['name' => 'Kultúrny dom Zvláštny koncept', 'status' => ModelStatus::Draft->value]);

        $response = $this->getJson('/api/dashboard/venues?for_select=1&search=Zvl%C3%A1%C5%A1tny&canal_id='.$this->canalPrimary->id);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($foreign->id), 'Zverejnené cudzie miesto sa má ponúknuť.');
        $this->assertFalse($ids->contains($draft->id), 'Koncept cudzieho kanála sa ponúknuť nesmie.');
        $this->assertNotNull(collect($response->json('data'))->firstWhere('id', $foreign->id)['hint']);
    }

    #[Test]
    public function the_venue_picker_without_search_still_lists_only_own_venues(): void
    {
        $foreign = $this->foreignPublishedVenue();

        $response = $this->getJson('/api/dashboard/venues?for_select=1&canal_id='.$this->canalPrimary->id);

        $response->assertOk();
        $this->assertFalse(collect($response->json('data'))->pluck('id')->contains($foreign->id));
    }

    #[Test]
    public function picking_a_foreign_published_venue_links_it_to_the_canal_as_a_user(): void
    {
        $foreign = $this->foreignPublishedVenue();

        $response = $this->postJson('/api/dashboard/events', [
            'name' => 'Podujatie na spoločnom mieste',
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $foreign->id,
        ]);
        $response->assertCreated();

        $this->assertDatabaseHas('canal_venue', [
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $foreign->id,
            'is_owner' => false,
        ]);
    }

    #[Test]
    public function a_foreign_draft_venue_cannot_be_picked(): void
    {
        $foreign = $this->foreignPublishedVenue(['status' => ModelStatus::Draft->value]);

        $response = $this->postJson('/api/dashboard/events', [
            'name' => 'Podujatie na spoločnom mieste',
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $foreign->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('canal_venue', ['canal_id' => $this->canalPrimary->id, 'venue_id' => $foreign->id]);
    }

    #[Test]
    public function creating_a_venue_that_already_exists_in_the_town_reuses_it(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);

        $foreign = $this->foreignPublishedVenue();
        $before = Venue::query()->count();

        $response = $this->postJson('/api/dashboard/venues', [
            'canal_id' => $this->canalPrimary->id,
            'village_id' => $foreign->village_id,
            'name' => 'Kultúrny dom Zvláštny názov (Košice)',
        ]);

        $response->assertOk()->assertJsonPath('id', $foreign->id)->assertJsonPath('reused', true);

        $this->assertSame($before, Venue::query()->count());
        $this->assertDatabaseHas('canal_venue', [
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $foreign->id,
            'is_owner' => false,
        ]);
    }

    #[Test]
    public function a_new_venue_in_another_town_is_created_normally(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);

        $foreign = $this->foreignPublishedVenue();
        $trnava = Municipality::query()->where('slug', 'trnava')->firstOrFail();

        $response = $this->postJson('/api/dashboard/venues', [
            'canal_id' => $this->canalPrimary->id,
            'village_id' => $trnava->id,
            'name' => 'Kultúrny dom Zvláštny názov',
        ]);

        $response->assertCreated();
        $this->assertNotSame($foreign->id, $response->json('id'));
    }

    #[Test]
    public function a_steward_cannot_delete_a_venue_another_canal_uses(): void
    {
        $this->user->givePermissionTo('venue.delete');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);
        $venue->assignCanal(Canal::factory()->active()->create()->id, isOwner: false);

        $this->deleteJson('/api/dashboard/venues/'.$venue->id)->assertForbidden();

        $this->assertNotSoftDeleted('venues', ['id' => $venue->id]);
    }

    #[Test]
    public function a_claimed_canal_cannot_be_deleted_by_its_owner(): void
    {
        $this->user->givePermissionTo('canal.delete');

        $this->canalPrimary->forceFill(['claimed_at' => now(), 'claimed_by_user_id' => $this->user->id])->save();

        $this->deleteJson('/api/dashboard/canals/'.$this->canalPrimary->id)->assertForbidden();

        $this->assertNotSoftDeleted('canals', ['id' => $this->canalPrimary->id]);
    }
}
