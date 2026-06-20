<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEventFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_events_index_supports_published_query_filter(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        Event::factory()->create([
            'user_id' => $admin->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);

        Event::factory()->create([
            'user_id' => $admin->id,
            'status' => ModelStatus::Draft->value,
            'published_at' => null,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/events?published=true');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', ModelStatus::Published->value);
    }
}
