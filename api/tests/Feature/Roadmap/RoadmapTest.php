<?php

namespace Tests\Feature\Roadmap;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Organization;
use App\Models\SystemLog;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Tests\TestSupport\EventSetupTest;

class RoadmapTest extends EventSetupTest
{
    public function test_similarity_normalizes_names_excludes_self_and_hides_other_tenants(): void
    {
        $this->canalPrimary->update(['name' => '  Žltý   DOM  ']);
        Canal::factory()->create(['name' => 'Žltý dom']);
        $this->getJson('/api/dashboard/canals/similar?name=zlty%20dom')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->canalPrimary->id);
        $this->getJson('/api/dashboard/canals/similar?name=zlty%20dom&exclude_id='.$this->canalPrimary->id)
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_organization_similarity_uses_title_and_excludes_billing_details(): void
    {
        $organization = Organization::factory()->create(['title' => 'Žltý dom']);
        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $this->getJson('/api/admin/organizations/similar?name=zlty%20dom')->assertOk()
            ->assertJsonPath('data.0.name', 'Žltý dom')->assertJsonMissingPath('data.0.account_uuid');
    }

    public function test_remote_venues_are_filtered_by_active_channel_before_pagination(): void
    {
        $own = Venue::factory()->create(['canal_id' => $this->canalPrimary->id, 'name' => 'Unique Roadmap Venue', 'status' => 'published']);
        $other = Canal::factory()->create();
        Venue::factory()->create(['canal_id' => $other->id, 'name' => 'Unique Roadmap Venue', 'status' => 'published']);
        $response = $this->getJson('/api/dashboard/venues?search=Unique%20Roadmap%20Venue&per_page=20&canal_id='.$this->canalPrimary->id)->assertOk();
        $this->assertEquals([$own->id], array_column($response->json('data'), 'id'));
    }

    public function test_merge_transfers_even_deleted_events_and_keeps_target_content_with_audit(): void
    {
        $source = $this->futureEvent->venue;
        $source->update(['status' => 'published']);
        $target = Venue::factory()->create(['canal_id' => $this->canalPrimary->id, 'status' => 'published', 'name' => 'Survivor']);
        // Exactly the same ownership and visibility on both sides.
        $target->canals()->detach();
        foreach ($source->canals()->get() as $canal) {
            $target->canals()->attach($canal->id, ['is_owner' => $canal->pivot->is_owner, 'status' => $canal->pivot->status]);
        }
        DB::table('events')->where('id', $this->futureEvent->id)->update(['deleted_at' => now()]);
        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $this->postJson('/api/admin/venues/'.$source->id.'/merge', ['target_id' => $target->id])->assertOk();
        $this->assertSoftDeleted('venues', ['id' => $source->id]);
        $this->assertDatabaseHas('events', ['id' => $this->futureEvent->id, 'venue_id' => $target->id]);
        $this->assertSame('Survivor', $target->fresh()->name);
        $this->assertTrue(SystemLog::where('event', 'resource.merged')->where('user_id', $this->userSuperAdmin->id)->exists());
    }

    public function test_venue_merge_unites_channels_and_keeps_the_target_steward(): void
    {
        $a = Canal::factory()->create();
        $b = Canal::factory()->create();
        $source = Venue::factory()->forCanal($a->id)->create(['status' => 'published']);
        $target = Venue::factory()->forCanal($b->id)->create(['status' => 'published', 'name' => 'Survivor']);
        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $this->postJson('/api/admin/venues/'.$source->id.'/merge', ['target_id' => $target->id])->assertOk();
        $this->assertSoftDeleted('venues', ['id' => $source->id]);
        $this->assertDatabaseHas('canal_venue', ['venue_id' => $target->id, 'canal_id' => $b->id, 'is_owner' => true]);
        $this->assertDatabaseHas('canal_venue', ['venue_id' => $target->id, 'canal_id' => $a->id, 'is_owner' => false, 'status' => 'published']);
        $this->assertDatabaseMissing('canal_venue', ['venue_id' => $source->id]);
    }

    public function test_merge_rejects_conflicting_owners_without_changes_and_forbids_non_admin(): void
    {
        $a = Canal::factory()->withOwner($this->user)->create(['status' => 'draft', 'identity_mode' => 'personal']);
        $b = Canal::factory()->withOwner($this->userSuperAdmin)->create(['status' => 'draft', 'identity_mode' => 'personal']);
        $url = '/api/admin/canals/'.$a->id.'/merge';
        $this->postJson($url, ['target_id' => $b->id])->assertForbidden();
        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $this->postJson($url, ['target_id' => $b->id])->assertUnprocessable();
        $this->assertNotSoftDeleted('canals', ['id' => $a->id]);
        $this->assertDatabaseHas('canal_user', ['canal_id' => $a->id, 'user_id' => $this->user->id]);
    }

    public function test_next_actions_are_scoped_and_limit_upcoming_to_seven_days(): void
    {
        $this->futureEvent->update(['status' => ModelStatus::Published, 'start_at' => now()->addDay()]);
        $this->cudziEvent->update(['status' => ModelStatus::Published, 'start_at' => now()->addDay()]);
        $this->pastEvent->update(['status' => ModelStatus::Draft]);
        $response = $this->getJson('/api/dashboard/next-actions')->assertOk();
        $ids = array_column($response->json('data.upcoming'), 'id');
        $this->assertContains($this->futureEvent->id, $ids);
        $this->assertNotContains($this->cudziEvent->id, $ids);
        $this->assertContains($this->pastEvent->id, array_column($response->json('data.drafts'), 'id'));
        $this->futureEvent->update(['start_at' => now()->addDays(8)]);
        $this->getJson('/api/dashboard/next-actions')->assertOk()->assertJsonCount(0, 'data.upcoming');
    }

    public function test_merge_moves_files_to_target_and_keeps_its_primary_picture(): void
    {
        $source = Venue::factory()->create(['status' => 'published']);
        $target = Venue::factory()->create(['status' => 'published']);
        $target->canals()->detach();
        foreach ($source->canals()->get() as $canal) {
            $target->canals()->attach($canal->id, ['is_owner' => $canal->pivot->is_owner, 'status' => $canal->pivot->status]);
        }
        $file = fn (int $id, string $path, bool $primary, int $order, string $sum) => DB::table('files')->insertGetId([
            'fileable_type' => Venue::class, 'fileable_id' => $id, 'original_name' => $path, 'size' => 1,
            'mime_type' => 'image/jpeg', 'path' => $path, 'type' => 'image', 'is_primary' => $primary,
            'sort_order' => $order, 'checksum' => $sum,
        ]);
        $targetMain = $file($target->id, 'target.jpg', true, 0, 'a');
        $sourceMain = $file($source->id, 'source.jpg', true, 0, 'b');
        $sourceSecond = $file($source->id, 'second.jpg', false, 1, 'c');
        $duplicate = $file($source->id, 'same-as-target.jpg', false, 2, 'a');
        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $this->postJson('/api/admin/venues/'.$source->id.'/merge', ['target_id' => $target->id])->assertOk();
        $this->assertSoftDeleted('venues', ['id' => $source->id]);
        $this->assertDatabaseHas('files', ['id' => $targetMain, 'fileable_id' => $target->id, 'is_primary' => true]);
        $this->assertDatabaseHas('files', ['id' => $sourceMain, 'fileable_id' => $target->id, 'is_primary' => false, 'path' => 'source.jpg']);
        $this->assertDatabaseHas('files', ['id' => $sourceSecond, 'fileable_id' => $target->id, 'is_primary' => false]);
        // The target already has the same picture: the copy stays with the soft-deleted source.
        $this->assertDatabaseHas('files', ['id' => $duplicate, 'fileable_id' => $source->id]);
        $this->assertSame(1, DB::table('files')->where('fileable_type', Venue::class)->where('fileable_id', $target->id)->where('is_primary', true)->count());
        $log = SystemLog::where('event', 'resource.merged')->where('subject_id', $target->id)->firstOrFail();
        $this->assertEqualsCanonicalizing([$sourceMain, $sourceSecond], $log->context['transferred']['files']);
    }

    public function test_archived_duplicate_merges_into_published_place_but_not_the_other_way(): void
    {
        $archived = Venue::factory()->create(['status' => 'archived']);
        $live = Venue::factory()->create(['status' => 'published']);
        $other = Venue::factory()->create(['status' => 'published']);
        $this->actingAs($this->userSuperAdmin, 'sanctum');
        // A live place never disappears into an archived one.
        $this->postJson('/api/admin/venues/'.$other->id.'/merge', ['target_id' => $archived->id])->assertUnprocessable();
        $this->assertNotSoftDeleted('venues', ['id' => $other->id]);
        $this->postJson('/api/admin/venues/'.$archived->id.'/merge', ['target_id' => $live->id])->assertOk();
        $this->assertSoftDeleted('venues', ['id' => $archived->id]);
        $this->assertSame(ModelStatus::Published, $live->fresh()->status);
    }

    public function test_merge_promotes_source_picture_when_target_has_none(): void
    {
        $source = Canal::factory()->create(['status' => 'draft', 'identity_mode' => 'personal']);
        $target = Canal::factory()->create(['status' => 'draft', 'identity_mode' => 'personal']);
        $id = DB::table('files')->insertGetId(['fileable_type' => Canal::class, 'fileable_id' => $source->id,
            'original_name' => 'keep.jpg', 'size' => 1, 'mime_type' => 'image/jpeg', 'path' => 'keep.jpg',
            'type' => 'image', 'is_primary' => true]);
        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $this->postJson('/api/admin/canals/'.$source->id.'/merge', ['target_id' => $target->id])->assertOk();
        $this->assertSoftDeleted('canals', ['id' => $source->id, 'merged_into_id' => $target->id]);
        $this->assertDatabaseHas('files', ['id' => $id, 'fileable_id' => $target->id, 'is_primary' => true, 'path' => 'keep.jpg']);
    }

    public function test_canal_merge_keeps_history_with_source_and_drops_address_search_state(): void
    {
        $source = Canal::factory()->create(['status' => 'draft', 'identity_mode' => 'organization']);
        $target = Canal::factory()->create(['status' => 'draft', 'identity_mode' => 'organization']);
        // Riadky, ktoré o zdroji vytvoria automatické úlohy — predtým zlúčenie blokovali.
        DB::table('ai_usages')->insert(['feature' => 'profile_research', 'model' => 'test', 'canal_id' => $source->id,
            'subject_type' => $source->getMorphClass(), 'subject_id' => $source->id, 'cost_usd' => 0.01, 'created_at' => now()]);
        DB::table('content_reviews')->insert(['reviewable_type' => $source->getMorphClass(), 'reviewable_id' => $source->id,
            'content_hash' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('views')->insert(['viewable_type' => $source->getMorphClass(), 'viewable_id' => $source->id,
            'visitor_hash' => 'v', 'viewed_on' => now()->toDateString(), 'created_at' => now()]);
        DB::table('canal_email_searches')->insert(['canal_id' => $source->id, 'attempts' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('profile_enrichments')->insert(['subject_type' => $source->getMorphClass(), 'subject_id' => $source->id,
            'attempts' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $this->postJson('/api/admin/canals/'.$source->id.'/merge', ['target_id' => $target->id])->assertOk();

        $this->assertSoftDeleted('canals', ['id' => $source->id, 'merged_into_id' => $target->id]);
        $this->assertDatabaseHas('ai_usages', ['canal_id' => $source->id]);
        $this->assertDatabaseHas('content_reviews', ['reviewable_id' => $source->id]);
        $this->assertDatabaseHas('views', ['viewable_id' => $source->id]);
        $this->assertDatabaseHas('profile_enrichments', ['subject_id' => $source->id]);
        $this->assertDatabaseMissing('canal_email_searches', ['canal_id' => $source->id]);
    }

    public function test_canal_merge_preserves_identical_memberships_and_rejects_repeat_and_self(): void
    {
        $source = Canal::factory()->withOwner($this->user)->create(['status' => 'draft', 'identity_mode' => 'personal']);
        $target = Canal::factory()->withOwner($this->user)->create(['status' => 'draft', 'identity_mode' => 'personal']);
        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $url = '/api/admin/canals/'.$source->id.'/merge';
        $this->postJson($url, ['target_id' => $source->id])->assertUnprocessable();
        $this->postJson($url, ['target_id' => $target->id])->assertOk();
        $this->assertDatabaseHas('canal_user', ['canal_id' => $target->id, 'user_id' => $this->user->id, 'is_owner' => true]);
        $this->assertDatabaseMissing('canal_user', ['canal_id' => $source->id]);
        $this->postJson($url, ['target_id' => $target->id])->assertNotFound();
    }

    public function test_remote_form_search_offers_published_venues_of_other_canals_but_not_unpublished_ones(): void
    {
        // Miesto je spoločný záznam — výber vo formulári podujatia ponúka cudzie
        // zverejnené miesta (EloquentVenueRepository::selectableQuery()), nie koncepty.
        $other = Canal::factory()->create();
        Venue::factory()->create(['canal_id' => $other->id, 'name' => 'Isolated Roadmap Place', 'status' => 'published']);
        Venue::factory()->create(['canal_id' => $other->id, 'name' => 'Isolated Roadmap Draft', 'status' => 'draft']);

        $this->getJson('/api/dashboard/venues?search=Isolated%20Roadmap&for_select=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Isolated Roadmap Place');
    }
}
