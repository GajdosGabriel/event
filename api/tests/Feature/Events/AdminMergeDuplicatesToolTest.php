<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Produkcia nemá shell — príkaz sa tam dá spustiť jedine týmto tlačidlom
 * v /admin/nastroje, takže endpoint je súčasť funkcie, nie príslušenstvo.
 */
class AdminMergeDuplicatesToolTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_duplicates_without_touching_them(): void
    {
        $this->actingAs($this->superAdmin(), 'sanctum');
        $this->duplicatePair();

        $response = $this->postJson('/api/admin/tools/merge-duplicates');

        $response->assertOk();
        $this->assertStringContainsString('Nájdené skupiny: 1', $response->json('output'));
        $this->assertSame(2, Event::query()->count());
    }

    #[Test]
    public function it_merges_the_pair_when_asked_to(): void
    {
        $this->actingAs($this->superAdmin(), 'sanctum');
        $this->duplicatePair();

        $this->postJson('/api/admin/tools/merge-duplicates', ['apply' => true])->assertOk();

        $this->assertSame(1, Event::query()->count());
    }

    #[Test]
    public function it_is_closed_to_everyone_else(): void
    {
        $this->postJson('/api/admin/tools/merge-duplicates')->assertUnauthorized();

        $editor = User::factory()->create();
        $editor->assignRole(Role::findOrCreate('canal-editor', 'web')->name);
        $this->actingAs($editor, 'sanctum');

        $this->postJson('/api/admin/tools/merge-duplicates')->assertForbidden();
    }

    private function superAdmin(): User
    {
        $role = Role::findOrCreate('super-admin', 'web');
        $role->givePermissionTo(Permission::findOrCreate('event.delete', 'web'));

        return User::factory()->create()->assignRole($role);
    }

    private function duplicatePair(): void
    {
        $collection = Canal::factory()->create([
            'name' => 'vyveska.sk',
            'slug' => 'vyveska-sk',
            'website' => 'https://www.vyveska.sk',
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
        $organizer = Canal::factory()->create([
            'name' => 'Komunitné centrum Košice',
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
        $venue = Venue::factory()->create(['canal_id' => $collection->id]);

        foreach ([$collection, $organizer] as $index => $canal) {
            Event::factory()->create([
                'name' => 'Tréning zručností tvorby komunity',
                'slug' => 'trening-zrucnosti-tvorby-komunity',
                'canal_id' => $canal->id,
                'user_id' => User::factory()->create(['canal_id' => $canal->id])->id,
                'venue_id' => $venue->id,
                'status' => ModelStatus::Published->value,
                'published_at' => now(),
                'start_at' => '2026-10-09 07:00:00',
                'end_at' => '2026-10-09 12:00:00',
                'orginal_source' => 'https://www.vyveska.sk/trening-' . $index,
            ]);
        }
    }
}
