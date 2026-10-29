<?php

namespace Tests\Feature\Venues;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\DuplicateDecision;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Imports\DuplicateResolver;
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
    public function reusing_a_venue_fills_only_what_it_is_missing(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);

        $foreign = $this->foreignPublishedVenue([
            'street' => null,
            'website' => 'https://povodny.sk',
            'latitude' => null,
            'longitude' => null,
            'coordinates_source' => null,
        ]);

        $response = $this->postJson('/api/dashboard/venues', [
            'canal_id' => $this->canalPrimary->id,
            'village_id' => $foreign->village_id,
            'name' => 'Kultúrny dom Zvláštny názov',
            'street' => 'Hlavná 5',
            'website' => 'https://novy.sk',
            'latitude' => 48.7208,
            'longitude' => 21.2575,
            'coordinates_source' => 'address',
        ]);

        $response->assertOk()->assertJsonPath('reused', true);
        $this->assertEqualsCanonicalizing(['street', 'latitude', 'longitude', 'coordinates_source'], $response->json('filled_fields'));

        $foreign->refresh();
        $this->assertSame('Hlavná 5', $foreign->street);
        $this->assertSame('address', $foreign->coordinates_source);
        $this->assertStringContainsString('povodny.sk', (string) $foreign->website, 'Vyplnené pole sa neprepisuje.');
    }

    #[Test]
    public function reusing_a_foreign_venue_does_not_fill_its_contacts(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);

        $foreign = $this->foreignPublishedVenue(['street' => null, 'website' => null, 'email' => null, 'phone' => null, 'body' => null]);

        $response = $this->postJson('/api/dashboard/venues', [
            'canal_id' => $this->canalPrimary->id,
            'village_id' => $foreign->village_id,
            'name' => 'Kultúrny dom Zvláštny názov',
            'street' => 'Hlavná 5',
            'website' => 'https://podvrh.sk',
            'email' => 'podvrh@example.com',
            'body' => '<p>Cudzí popis</p>',
        ]);

        $response->assertOk()->assertJsonPath('reused', true);
        $this->assertSame(['street'], $response->json('filled_fields'));

        $foreign->refresh();
        $this->assertNull($foreign->website);
        $this->assertNull($foreign->email);
        $this->assertNull($foreign->body);
    }

    #[Test]
    public function reusing_an_own_venue_fills_its_contacts(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);

        $own = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'name' => 'Kultúrny dom Zvláštny názov',
            'category' => null,
            'village_id' => Municipality::query()->where('slug', 'kosice')->firstOrFail()->id,
            'status' => ModelStatus::Published->value,
            'email' => null,
        ]);

        $response = $this->postJson('/api/dashboard/venues', [
            'canal_id' => $this->canalPrimary->id,
            'village_id' => $own->village_id,
            'name' => 'Kultúrny dom Zvláštny názov',
            'email' => 'spravca@example.com',
        ]);

        $response->assertOk()->assertJsonPath('id', $own->id);
        $this->assertContains('email', $response->json('filled_fields'));
        $this->assertSame('spravca@example.com', $own->fresh()->email);
    }

    #[Test]
    public function a_foreign_canal_id_is_rejected_next_to_an_own_owner_canal(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);

        $foreign = $this->foreignPublishedVenue();
        $otherCanal = Canal::factory()->active()->create();

        $this->postJson('/api/dashboard/venues', [
            'owner_canal_id' => $this->canalPrimary->id,
            'canal_id' => $otherCanal->id,
            'village_id' => $foreign->village_id,
            'name' => 'Kultúrny dom Zvláštny názov',
        ])->assertStatus(422)->assertJsonValidationErrors('canal_ids');

        $this->assertDatabaseMissing('canal_venue', ['canal_id' => $otherCanal->id]);
    }

    #[Test]
    public function the_similarity_hint_finds_a_foreign_published_venue_by_its_address(): void
    {
        $foreign = $this->foreignPublishedVenue(['street' => 'Hlavná ulica 5']);
        $this->foreignPublishedVenue(['name' => 'Iný koncept', 'street' => 'Hlavná 5', 'status' => ModelStatus::Draft->value]);

        $response = $this->getJson('/api/dashboard/venues/similar?'.http_build_query([
            'name' => 'Hlavná 5',
            'municipality' => $foreign->village_id,
        ]));

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $foreign->id)
            ->assertJsonPath('data.0.own', false)
            ->assertJsonPath('data.0.street', 'Hlavná ulica 5');
    }

    #[Test]
    public function an_admin_is_asked_before_creating_a_duplicate(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);

        $foreign = $this->foreignPublishedVenue();
        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $payload = ['village_id' => $foreign->village_id, 'name' => 'Kultúrny dom Zvláštny názov'];

        $this->postJson('/api/admin/venues', $payload)
            ->assertStatus(409)
            ->assertJsonPath('duplicate.id', $foreign->id);

        $response = $this->postJson('/api/admin/venues', [...$payload, 'allow_duplicate' => true]);
        $response->assertCreated();
        $this->assertNotSame($foreign->id, $response->json('id'));

        // Odmietnutie prebije staršie `same` — resolver kandidáta už nepoužije.
        $this->assertDatabaseHas('duplicate_decisions', [
            'entity' => DuplicateDecision::ENTITY_VENUE,
            'candidate_id' => $foreign->id,
            'decision' => DuplicateDecision::DISTINCT,
            'source' => DuplicateDecision::SOURCE_HUMAN,
        ]);
        $this->assertNotSame($foreign->id, app(DuplicateResolver::class)->findVenue('Kultúrny dom Zvláštny názov', $foreign->village_id)?->id);
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
