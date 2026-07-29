<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\CanalInvitation;
use App\Models\Event;
use App\Models\User;
use App\Notifications\CanalInvitationSent;
use App\Services\Canals\CanalMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tím kanála: pozvánka e-mailom, per-kanál rola a to, že rola platí len
 * v kanáli, do ktorého bola pridelená.
 */
class CanalTeamTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'owner@divadlo.test']);
        $this->canal = $this->makeCanal('Divadlo');
        app(CanalMembership::class)->attach($this->canal, $this->owner, CanalRole::Owner);
    }

    #[Test]
    public function owner_can_invite_a_member_and_the_mail_goes_out(): void
    {
        Notification::fake();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson($this->teamUrl() . '/invitations', [
                'email' => 'dramaturg@divadlo.test',
                'role' => CanalRole::Editor->value,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.invitations.0.email', 'dramaturg@divadlo.test')
            ->assertJsonPath('data.invitations.0.role', CanalRole::Editor->value);

        $this->assertDatabaseHas('canal_invitations', [
            'canal_id' => $this->canal->id,
            'email' => 'dramaturg@divadlo.test',
            'role' => CanalRole::Editor->value,
            'invited_by_user_id' => $this->owner->id,
            'accepted_at' => null,
        ]);

        Notification::assertSentOnDemand(CanalInvitationSent::class);
    }

    #[Test]
    public function member_without_team_rights_cannot_invite(): void
    {
        $editor = $this->member('editor@divadlo.test', CanalRole::Editor);

        $this->actingAs($editor, 'sanctum')
            ->postJson($this->teamUrl() . '/invitations', [
                'email' => 'dalsi@divadlo.test',
                'role' => CanalRole::Editor->value,
            ])
            ->assertStatus(403);

        // Zoznam tímu člen vidieť smie, cudzie adresy v ňom však nie.
        $this->actingAs($editor, 'sanctum')
            ->getJson($this->teamUrl())
            ->assertOk()
            ->assertJsonPath('meta.permissions.manage', false)
            ->assertJsonPath('data.invitations', [])
            ->assertJsonPath('data.members.0.email', null);
    }

    #[Test]
    public function invited_user_accepts_and_gets_the_role_from_the_invitation(): void
    {
        $invitation = $this->invite('brigadnik@divadlo.test', CanalRole::Checkin);
        $invited = User::factory()->create(['email' => 'brigadnik@divadlo.test']);

        $this->getJson('/api/invitations/' . $invitation->token)
            ->assertOk()
            ->assertJsonPath('data.canal.name', 'Divadlo')
            ->assertJsonPath('data.role', CanalRole::Checkin->value)
            ->assertJsonPath('data.status', 'pending');

        $this->actingAs($invited, 'sanctum')
            ->postJson('/api/invitations/' . $invitation->token . '/accept')
            ->assertOk()
            ->assertJsonPath('data.canal.id', $this->canal->id);

        $this->assertDatabaseHas('canal_user', [
            'canal_id' => $this->canal->id,
            'user_id' => $invited->id,
            'role' => CanalRole::Checkin->value,
            'is_owner' => 0,
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertTrue($invited->fresh()->hasRole('canal-checkin'));
    }

    #[Test]
    public function invitation_cannot_be_accepted_by_a_different_account(): void
    {
        $invitation = $this->invite('pozvany@divadlo.test', CanalRole::Editor);
        $someoneElse = User::factory()->create(['email' => 'niekto.iny@inde.test']);

        $this->actingAs($someoneElse, 'sanctum')
            ->postJson('/api/invitations/' . $invitation->token . '/accept')
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('canal_user', [
            'canal_id' => $this->canal->id,
            'user_id' => $someoneElse->id,
        ]);
    }

    #[Test]
    public function expired_invitation_cannot_be_accepted(): void
    {
        $invitation = $this->invite('neskoro@divadlo.test', CanalRole::Editor);
        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        $invited = User::factory()->create(['email' => 'neskoro@divadlo.test']);

        $this->getJson('/api/invitations/' . $invitation->token)
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');

        $this->actingAs($invited, 'sanctum')
            ->postJson('/api/invitations/' . $invitation->token . '/accept')
            ->assertStatus(422);
    }

    #[Test]
    public function revoked_invitation_cannot_be_accepted(): void
    {
        $invitation = $this->invite('zruseny@divadlo.test', CanalRole::Editor);

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson($this->teamUrl() . '/invitations/' . $invitation->id)
            ->assertOk();

        $invited = User::factory()->create(['email' => 'zruseny@divadlo.test']);

        $this->actingAs($invited, 'sanctum')
            ->postJson('/api/invitations/' . $invitation->token . '/accept')
            ->assertStatus(422);
    }

    #[Test]
    public function editor_can_edit_events_of_the_canal_but_cannot_delete_them(): void
    {
        $editor = $this->member('editor2@divadlo.test', CanalRole::Editor);
        $event = $this->makeEvent($this->canal);

        $this->actingAs($editor, 'sanctum')
            ->putJson('/api/dashboard/events/' . $event->id, [
                'name' => 'Premiéra',
                'canal_id' => $this->canal->id,
                'start_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            ])
            ->assertOk();

        $this->actingAs($editor, 'sanctum')
            ->deleteJson('/api/dashboard/events/' . $event->id)
            ->assertStatus(403);
    }

    #[Test]
    public function checkin_member_cannot_create_events_in_the_canal(): void
    {
        $brigadnik = $this->member('vstup@divadlo.test', CanalRole::Checkin);
        $event = $this->makeEvent($this->canal);

        $this->actingAs($brigadnik, 'sanctum')
            ->getJson('/api/dashboard/events/' . $event->id)
            ->assertOk();

        // Podujatie vzniká v aktívnom kanáli, preto sa naň brigádnik najprv
        // prepne — inak by mu vzniklo vo vlastnom osobnom kanáli.
        $this->actingAs($brigadnik, 'sanctum')
            ->postJson('/api/dashboard/users/active-canal', ['canal_id' => $this->canal->id])
            ->assertOk();

        $this->actingAs($brigadnik->fresh(), 'sanctum')
            ->postJson('/api/dashboard/events', [
                'name' => 'Nepovolené',
                'canal_id' => $this->canal->id,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('events', ['name' => 'Nepovolené']);
    }

    #[Test]
    public function role_applies_only_to_the_canal_it_was_granted_in(): void
    {
        $editor = $this->member('externista@divadlo.test', CanalRole::Editor);

        $otherCanal = $this->makeCanal('Cudzí klub');
        $otherOwner = User::factory()->create(['email' => 'cudzi@klub.test']);
        app(CanalMembership::class)->attach($otherCanal, $otherOwner, CanalRole::Owner);
        $foreignEvent = $this->makeEvent($otherCanal);

        // Vo vlastnom kanáli editor podujatie upraví…
        $ownEvent = $this->makeEvent($this->canal);
        $this->actingAs($editor, 'sanctum')
            ->putJson('/api/dashboard/events/' . $ownEvent->id, [
                'name' => 'Vlastné',
                'canal_id' => $this->canal->id,
            ])
            ->assertOk();

        // …v cudzom o ňom nesmie ani vedieť — dashboard výpis cudzie kanály
        // vôbec nevidí, preto 404 a nie 403.
        $this->actingAs($editor, 'sanctum')
            ->getJson('/api/dashboard/events/' . $foreignEvent->id)
            ->assertStatus(404);
    }

    #[Test]
    public function owner_can_change_member_role(): void
    {
        $member = $this->member('povysenie@divadlo.test', CanalRole::Checkin);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson($this->teamUrl() . '/' . $member->id, ['role' => CanalRole::Owner->value])
            ->assertOk();

        $this->assertDatabaseHas('canal_user', [
            'canal_id' => $this->canal->id,
            'user_id' => $member->id,
            'role' => CanalRole::Owner->value,
            'is_owner' => 1,
        ]);
        $this->assertTrue($member->fresh()->hasRole('canal-owner'));
    }

    #[Test]
    public function last_owner_cannot_be_removed_or_demoted(): void
    {
        $secondOwner = $this->member('druhy@divadlo.test', CanalRole::Owner);

        // Kým sú vlastníci dvaja, degradácia prejde.
        $this->actingAs($this->owner, 'sanctum')
            ->putJson($this->teamUrl() . '/' . $secondOwner->id, ['role' => CanalRole::Editor->value])
            ->assertOk();

        // Posledného vlastníka už odobrať nejde — kanál by ostal bez správcu.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CanalMembership::class)->detach($this->canal, $this->owner);
    }

    #[Test]
    public function owner_cannot_change_or_remove_their_own_membership(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson($this->teamUrl() . '/' . $this->owner->id, ['role' => CanalRole::Editor->value])
            ->assertStatus(422);

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson($this->teamUrl() . '/' . $this->owner->id)
            ->assertStatus(422);
    }

    #[Test]
    public function removed_member_loses_access_to_the_canal(): void
    {
        $editor = $this->member('odchod@divadlo.test', CanalRole::Editor);
        $event = $this->makeEvent($this->canal);

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson($this->teamUrl() . '/' . $editor->id)
            ->assertOk();

        $this->assertDatabaseMissing('canal_user', [
            'canal_id' => $this->canal->id,
            'user_id' => $editor->id,
        ]);

        $this->actingAs($editor->fresh(), 'sanctum')
            ->getJson('/api/dashboard/events/' . $event->id)
            ->assertStatus(404);
    }

    #[Test]
    public function inviting_an_existing_member_is_rejected(): void
    {
        $editor = $this->member('uz.clen@divadlo.test', CanalRole::Editor);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson($this->teamUrl() . '/invitations', [
                'email' => $editor->email,
                'role' => CanalRole::Editor->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    private function teamUrl(): string
    {
        return '/api/dashboard/canals/' . $this->canal->id . '/team';
    }

    private function makeCanal(string $name): Canal
    {
        return Canal::factory()->create([
            'name' => $name,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    private function makeEvent(Canal $canal): Event
    {
        return Event::query()->create([
            'name' => 'Predstavenie ' . uniqid(),
            'status' => ModelStatus::Draft->value,
            'canal_id' => $canal->id,
            'user_id' => $this->owner->id,
            'start_at' => now()->addMonth(),
            'end_at' => now()->addMonth()->addHours(2),
        ]);
    }

    /** Rovno pripojený člen — skratka tam, kde sa netestuje samotná pozvánka. */
    private function member(string $email, CanalRole $role): User
    {
        $user = User::factory()->create(['email' => $email]);
        app(CanalMembership::class)->attach($this->canal, $user, $role);

        return $user->fresh();
    }

    private function invite(string $email, CanalRole $role): CanalInvitation
    {
        Notification::fake();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson($this->teamUrl() . '/invitations', ['email' => $email, 'role' => $role->value])
            ->assertStatus(201);

        return CanalInvitation::query()->where('email', $email)->latest('id')->firstOrFail();
    }
}
