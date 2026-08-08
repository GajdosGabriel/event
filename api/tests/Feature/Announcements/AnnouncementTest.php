<?php

namespace Tests\Feature\Announcements;

use App\Enums\ModelStatus;
use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function announcement(array $attributes = []): Announcement
    {
        return Announcement::create(array_merge([
            'status' => ModelStatus::Published->value,
            'placement' => 'top',
            'title' => 'Oznam',
            'body' => null,
            'variant' => 'blue',
            'sort_order' => 10,
        ], $attributes));
    }

    public function test_public_index_returns_only_published_announcements_in_the_display_window(): void
    {
        $visible = $this->announcement(['title' => 'Viditeľný']);
        $this->announcement(['title' => 'Vypnutý', 'status' => ModelStatus::Draft->value]);
        $this->announcement(['title' => 'Ešte nie', 'published_from' => now()->addDay()]);
        $this->announcement(['title' => 'Už nie', 'published_until' => now()->subDay()]);

        $response = $this->getJson('/api/announcements');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_public_index_filters_by_placement(): void
    {
        $this->announcement(['placement' => 'top']);
        $bottom = $this->announcement(['placement' => 'bottom']);

        $response = $this->getJson('/api/announcements?placement=bottom');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $bottom->id);
    }

    public function test_admin_endpoints_are_closed_to_regular_users(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/announcements')
            ->assertForbidden();
    }

    public function test_super_admin_can_create_announcement_and_receives_form_options(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/announcements', [
            'placement' => 'top',
            'title' => 'Letná akcia',
            'body' => '<p>Text oznamu</p>',
            'variant' => 'green',
            'sort_order' => 5,
            'status' => ModelStatus::Published->value,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Letná akcia');
        $response->assertJsonPath('data.variant', 'green');
        $this->assertNotEmpty($response->json('meta.variants'));

        $this->assertDatabaseHas('announcements', [
            'title' => 'Letná akcia',
            'variant' => 'green',
        ]);
    }

    public function test_unknown_variant_is_rejected(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/announcements', [
                'placement' => 'top',
                'title' => 'Oznam',
                'variant' => 'bg-red-500 text-white',
                'status' => ModelStatus::Published->value,
            ])
            ->assertJsonValidationErrors('variant');
    }

    /** Vypnutie je zmena stavu, nie mazanie — text musí ostať uložený. */
    public function test_super_admin_can_switch_announcement_off_without_losing_the_text(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $announcement = $this->announcement(['title' => 'Kampaň', 'body' => '<p>Text</p>']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/announcements/' . $announcement->id, [
                'placement' => 'top',
                'title' => 'Kampaň',
                'body' => '<p>Text</p>',
                'variant' => 'blue',
                'sort_order' => 10,
                'status' => ModelStatus::Draft->value,
            ])
            ->assertOk();

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'status' => ModelStatus::Draft->value,
            'title' => 'Kampaň',
        ]);

        $this->getJson('/api/announcements')->assertJsonCount(0, 'data');
    }
}
