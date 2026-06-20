<?php

namespace Tests\Feature\Filters;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEventFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');

        // Create published events
        Event::factory(3)->create([
            'status' => ModelStatus::Published->value,
            'published_at' => now()
        ]);

        // Create draft events
        Event::factory(2)->create([
            'status' => ModelStatus::Draft->value,
            'published_at' => null
        ]);
    }

    public function test_filter_events_by_published(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?published=true');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(3, $data);
        foreach ($data as $event) {
            $this->assertEquals(ModelStatus::Published->value, $event['status']);
        }
    }

    public function test_filter_events_by_unpublished(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?unpublished=true');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
        foreach ($data as $event) {
            $this->assertNotEquals(ModelStatus::Published->value, $event['status']);
        }
    }

    public function test_filter_events_by_status(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?status=' . ModelStatus::Draft->value);

        $response->assertStatus(200);
        $data = $response->json('data');

        foreach ($data as $event) {
            $this->assertEquals(ModelStatus::Draft->value, $event['status']);
        }
    }

    public function test_filter_with_per_page(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?per_page=2');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
    }

    public function test_filter_events_by_search_matches_name_and_body(): void
    {
        $needle = 'SearchNeedle' . uniqid();

        $nameMatch = Event::factory()->create([
            'name' => 'Name ' . $needle,
            'body' => 'No match here',
        ]);

        $bodyMatch = Event::factory()->create([
            'name' => 'Generic event',
            'body' => 'Body contains ' . $needle,
        ]);

        $otherEvent = Event::factory()->create([
            'name' => 'Completely different',
            'body' => 'Still different',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?search=' . urlencode($needle));

        $response->assertStatus(200);

        $data = $response->json('data');
        $ids = collect($data)->pluck('id')->all();

        $this->assertSame($nameMatch->id, $data[0]['id'] ?? null);
        $this->assertContains($nameMatch->id, $ids);
        $this->assertContains($bodyMatch->id, $ids);
        $this->assertNotContains($otherEvent->id, $ids);
    }

    public function test_filter_includes_deleted_when_requested(): void
    {
        $deletedEvent = Event::factory()->create(['status' => ModelStatus::Published->value]);
        $deletedEvent->delete();

        $withoutDeleted = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?deleted=false');
        $withDeleted = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?deleted=true');

        $withoutDeletedIds = collect($withoutDeleted->json('data'))->pluck('id')->toArray();
        $withDeletedIds = collect($withDeleted->json('data'))->pluck('id')->toArray();

        $this->assertFalse(in_array($deletedEvent->id, $withoutDeletedIds));
        $this->assertTrue(in_array($deletedEvent->id, $withDeletedIds));
    }

    public function test_invalid_status_validation(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?status=invalid_status');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_invalid_search_validation(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?search=' . str_repeat('a', 251));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('search');
    }
}
