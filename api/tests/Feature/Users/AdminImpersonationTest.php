<?php

namespace Tests\Feature\Users;

use App\Models\SystemLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Super-admin sa prihlási do cudzieho účtu (POST /api/admin/users/{id}/impersonate)
 * a vráti sa späť (DELETE /api/impersonation).
 */
class AdminImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
    }

    private function impersonate(User $user): string
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/users/'.$user->id.'/impersonate')
            ->assertOk()
            ->json('access_token');
    }

    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    #[Test]
    public function admin_gets_a_token_that_acts_as_the_user(): void
    {
        $user = User::factory()->create();

        $token = $this->impersonate($user);

        $this->asToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.impersonator.id', $this->admin->id);

        $this->assertDatabaseHas('system_logs', [
            'event' => 'admin.impersonation_started',
            'user_id' => $this->admin->id,
            'subject_id' => $user->id,
        ]);
    }

    #[Test]
    public function regular_login_has_no_impersonator(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.impersonator', null);
    }

    #[Test]
    public function stopping_revokes_only_the_impersonation_token(): void
    {
        $user = User::factory()->create();
        $user->createToken('auth_token');
        $token = $this->impersonate($user);

        $this->asToken($token)->deleteJson('/api/impersonation')->assertOk();

        $this->assertSame(['auth_token'], $user->tokens()->pluck('name')->all());
        $this->assertSame(1, SystemLog::where('event', 'admin.impersonation_stopped')->count());
    }

    #[Test]
    public function logout_while_impersonating_keeps_the_users_own_sessions(): void
    {
        $user = User::factory()->create();
        $user->createToken('auth_token');
        $token = $this->impersonate($user);

        $this->asToken($token)->postJson('/api/logout')->assertOk();

        $this->assertSame(['auth_token'], $user->tokens()->pluck('name')->all());
    }

    #[Test]
    public function stop_without_impersonation_is_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/impersonation')
            ->assertStatus(422);
    }

    #[Test]
    public function admin_cannot_impersonate_self_or_another_super_admin(): void
    {
        $other = User::factory()->create();
        $other->assignRole('super-admin');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/users/'.$this->admin->id.'/impersonate')
            ->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/users/'.$other->id.'/impersonate')
            ->assertForbidden();
    }

    #[Test]
    public function regular_user_cannot_impersonate(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/users/'.$victim->id.'/impersonate')
            ->assertForbidden();
    }

    #[Test]
    public function impersonated_session_does_not_touch_user_activity(): void
    {
        $user = User::factory()->create(['last_activity' => null, 'last_login_at' => null]);
        $token = $this->impersonate($user);

        $this->asToken($token)->getJson('/api/user')->assertOk();

        $user->refresh();
        $this->assertNull($user->last_activity);
        $this->assertNull($user->last_login_at);
    }
}
