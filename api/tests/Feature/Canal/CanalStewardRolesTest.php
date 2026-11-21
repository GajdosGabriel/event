<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Services\Canals\CanalMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Prevzatý kanál založila platforma: kto ho prevzal, je hlavný správca, nie
 * vlastník. Tím mu nesmie kanál „ukradnúť" ani zlikvidovať; pôvod vidí len
 * admin. Prehliadač vidí podujatia aj objednávateľov, nič nemení.
 */
class CanalStewardRolesTest extends TestCase
{
    use RefreshDatabase;

    private User $steward;

    private Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->steward = User::factory()->create(['email' => 'spravca@divadlo.test']);
        $this->canal = Canal::factory()->create([
            'name' => 'Importované divadlo',
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
        app(CanalMembership::class)->attach($this->canal, $this->steward, CanalRole::Owner);
        $this->canal->forceFill(['claimed_at' => now(), 'claimed_by_user_id' => $this->steward->id])->save();
    }

    #[Test]
    public function viewer_sees_events_and_buyers_but_changes_nothing(): void
    {
        $viewer = $this->member('prehliadac@divadlo.test', CanalRole::Viewer);
        $event = $this->makeEvent();

        $this->assertTrue($viewer->hasRole('canal-viewer'));

        $this->actingAs($viewer, 'sanctum')->getJson('/api/dashboard/events/'.$event->id)->assertOk();
        $this->actingAs($viewer, 'sanctum')->getJson('/api/dashboard/events/'.$event->id.'/tickets')->assertOk();

        $this->actingAs($viewer, 'sanctum')
            ->putJson('/api/dashboard/events/'.$event->id, [
                'name' => 'Prepísané',
                'canal_id' => $this->canal->id,
                'start_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(403);

        $this->actingAs($viewer, 'sanctum')
            ->postJson($this->teamUrl().'/invitations', ['email' => 'x@divadlo.test', 'role' => 'editor'])
            ->assertStatus(403);
    }

    #[Test]
    public function steward_can_invite_a_viewer(): void
    {
        Notification::fake();

        $this->actingAs($this->steward, 'sanctum')
            ->postJson($this->teamUrl().'/invitations', ['email' => 'pozera@divadlo.test', 'role' => CanalRole::Viewer->value])
            ->assertStatus(201)
            ->assertJsonPath('data.invitations.0.role', CanalRole::Viewer->value);
    }

    #[Test]
    public function co_manager_cannot_remove_or_demote_the_primary_steward(): void
    {
        $other = $this->member('druhy@divadlo.test', CanalRole::Owner);

        $this->actingAs($other, 'sanctum')
            ->deleteJson($this->teamUrl().'/'.$this->steward->id)
            ->assertStatus(422);

        $this->actingAs($other, 'sanctum')
            ->putJson($this->teamUrl().'/'.$this->steward->id, ['role' => CanalRole::Editor->value])
            ->assertStatus(422);

        $this->assertTrue($this->steward->fresh()->canInCanal($this->canal->id, 'canal.team'));

        // Naopak hlavný správca ďalšieho správcu odobrať smie.
        $this->actingAs($this->steward, 'sanctum')
            ->getJson($this->teamUrl())
            ->assertOk()
            ->assertJsonPath('data.members.0.is_primary', true);

        $this->actingAs($this->steward, 'sanctum')
            ->deleteJson($this->teamUrl().'/'.$other->id)
            ->assertOk();
    }

    #[Test]
    public function steward_cannot_archive_a_claimed_canal(): void
    {
        $this->actingAs($this->steward, 'sanctum')
            ->putJson('/api/dashboard/canals/'.$this->canal->id, $this->canalPayload(['status' => ModelStatus::Archived->value]))
            ->assertStatus(403);

        $this->assertSame(ModelStatus::Published, $this->canal->fresh()->status);
        $this->assertFalse($this->steward->can('archive', $this->canal->fresh()));

        $statuses = collect($this->actingAs($this->steward, 'sanctum')
            ->getJson('/api/dashboard/canals/'.$this->canal->id)
            ->assertOk()
            ->json('allowed_statuses'))->pluck('value');
        $this->assertNotEmpty($statuses->all());
        $this->assertNotContains(ModelStatus::Archived->value, $statuses->all());
    }

    #[Test]
    public function origin_is_visible_to_admin_only(): void
    {
        $this->actingAs($this->steward, 'sanctum')
            ->getJson('/api/dashboard/canals/'.$this->canal->id)
            ->assertOk()
            ->assertJsonMissingPath('origin');

        $admin = User::factory()->create();
        Role::findOrCreate('super-admin', 'web');
        $admin->assignRole('super-admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/canals/'.$this->canal->id)
            ->assertOk()
            ->assertJsonPath('origin.source', 'import')
            ->assertJsonPath('origin.claimed_by.id', $this->steward->id)
            ->assertJsonPath('members_list.0.role', CanalRole::Owner->value)
            ->assertJsonPath('members_list.0.is_primary', true);
    }

    private function teamUrl(): string
    {
        return '/api/dashboard/canals/'.$this->canal->id.'/team';
    }

    private function member(string $email, CanalRole $role): User
    {
        $user = User::factory()->create(['email' => $email]);
        app(CanalMembership::class)->attach($this->canal, $user, $role);

        return $user->fresh();
    }

    private function makeEvent(): Event
    {
        return Event::query()->create([
            'name' => 'Predstavenie '.uniqid(),
            'status' => ModelStatus::Draft->value,
            'canal_id' => $this->canal->id,
            'user_id' => $this->steward->id,
            'start_at' => now()->addMonth(),
            'end_at' => now()->addMonth()->addHours(2),
        ]);
    }

    private function canalPayload(array $override = []): array
    {
        return array_merge([
            'name' => $this->canal->name,
            'municipality_id' => $this->canal->municipality_id,
        ], $override);
    }
}
