<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEventFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_detail_does_not_offer_dashboard_actions_for_a_foreign_canal(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $other = User::factory()->create();
        $foreign = Event::factory()->create(['canal_id' => $other->canal_id, 'user_id' => $other->id]);
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/events/'.$foreign->id)
            ->assertOk()->assertJsonPath('permissions.view', true)
            ->assertJsonPath('permissions.view_tickets', false)
            ->assertJsonPath('permissions.checkin', false);
        $this->getJson('/api/dashboard/events/'.$foreign->id)->assertNotFound();
    }

    public function test_attention_filter_excludes_recent_drafts_and_published_events(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->travelTo(now()->startOfDay()->addHours(12));

        $stale = Event::factory()->create(['status' => ModelStatus::Draft, 'created_at' => now()->subDays(8)]);
        Event::factory()->create(['status' => ModelStatus::Draft, 'created_at' => now()->subDays(7)]);
        Event::factory()->create(['status' => ModelStatus::Published, 'created_at' => now()->subDays(8)]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/events?attention=stale_drafts')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $stale->id);
        $this->getJson('/api/admin/events?attention=invalid')->assertUnprocessable();
    }

    public function test_missing_image_filter_checks_primary_images_and_active_dates(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->travelTo(now()->startOfDay()->addHours(12));
        $attrs = ['status' => ModelStatus::Published, 'start_at' => now()->addDay(), 'end_at' => null];
        $missing = Event::factory()->create($attrs);
        $withImage = Event::factory()->create($attrs);
        $withImage->files()->create(['name' => 'Primary', 'original_name' => 'test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'path' => 'test.jpg', 'type' => \App\Enums\FileType::IMAGE, 'is_primary' => true]);
        Event::factory()->create([...$attrs, 'start_at' => now()->subDay()]);
        Event::factory()->create([...$attrs, 'status' => ModelStatus::Draft]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/events?attention=missing_image')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $missing->id);
    }

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

    public function test_upcoming_sort_lists_nearest_future_event_first_and_past_after(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $farFuture = Event::factory()->create([
            'user_id' => $admin->id,
            'start_at' => now()->addMonths(2),
            'end_at' => now()->addMonths(2)->addHour(),
        ]);
        $nearFuture = Event::factory()->create([
            'user_id' => $admin->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
        ]);
        $past = Event::factory()->create([
            'user_id' => $admin->id,
            'start_at' => now()->subMonth(),
            'end_at' => now()->subMonth()->addHour(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/events?sort=upcoming');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame(
            [$nearFuture->id, $farFuture->id, $past->id],
            $ids
        );
    }

    /**
     * Eager load kanála má obmedzený výber stĺpcov. Nevybraný stĺpec Eloquent
     * nenahlási — ticho vráti null, takže `website` z odpovede roky vypadával
     * bez jedinej chyby. Test drží výber a to, čo resource vypisuje, spolu.
     */
    public function test_admin_events_index_exposes_canal_website_and_organization(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $organization = Organization::factory()->create(['title' => 'Mesto Nitra']);
        $canal = Canal::factory()->create([
            'website' => 'https://kultura-nitra.sk',
            'organization_id' => $organization->id,
        ]);

        Event::factory()->create([
            'user_id' => $admin->id,
            'canal_id' => $canal->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/events');

        $response->assertOk();
        $response->assertJsonPath('data.0.canal.website', 'https://kultura-nitra.sk');
        $response->assertJsonPath('data.0.canal.organization.title', 'Mesto Nitra');
    }
}
