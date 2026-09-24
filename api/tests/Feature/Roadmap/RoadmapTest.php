<?php

namespace Tests\Feature\Roadmap;

use App\Models\{Canal, Venue, Event, Organization, SystemLog};
use App\Enums\ModelStatus;
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

    public function test_unsupported_file_dependency_blocks_merge_without_losing_any_data(): void
    {
        $source = Canal::factory()->create(['status' => 'draft', 'identity_mode' => 'personal']);
        $target = Canal::factory()->create(['status' => 'draft', 'identity_mode' => 'personal']);
        DB::table('files')->insert(['fileable_type' => Canal::class, 'fileable_id' => $source->id,
            'original_name' => 'keep.txt', 'size' => 1, 'mime_type' => 'text/plain', 'path' => 'keep.txt']);
        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $this->postJson('/api/admin/canals/'.$source->id.'/merge', ['target_id' => $target->id])->assertUnprocessable();
        $this->assertNotSoftDeleted('canals', ['id' => $source->id]);
        $this->assertDatabaseHas('files', ['fileable_id' => $source->id, 'path' => 'keep.txt']);
        $this->assertDatabaseMissing('system_logs', ['event' => 'resource.merged', 'subject_id' => $target->id]);
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

    public function test_remote_form_search_does_not_expand_to_unrelated_venues(): void
    {
        $other = Canal::factory()->create();
        Venue::factory()->create(['canal_id' => $other->id, 'name' => 'Isolated Roadmap Place', 'status' => 'published']);
        $this->getJson('/api/dashboard/venues?search=Isolated%20Roadmap%20Place&for_select=1')
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
