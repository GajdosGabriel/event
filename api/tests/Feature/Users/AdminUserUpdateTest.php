<?php

namespace Tests\Feature\Users;

use App\Enums\ModelStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin formulár používateľa (PUT /api/admin/users/{id}).
 *
 * Endpoint obsluhuje celý formulár aj samotné (od)blokovanie z detailu, preto
 * je podstatné, že sa vždy zapíše len to, čo v požiadavke naozaj prišlo.
 */
class AdminUserUpdateTest extends TestCase
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

    #[Test]
    public function admin_updates_the_account_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.test',
            'email_verified_at' => null,
            'status' => ModelStatus::Published->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/users/' . $user->id, [
                'email' => 'new@example.test',
                'email_verified' => true,
                'status' => ModelStatus::Archived->value,
                'password' => 'tajne-heslo-123',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => 'new@example.test']);

        $user->refresh();

        $this->assertSame('new@example.test', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(ModelStatus::Archived, $user->status);
        $this->assertTrue(Hash::check('tajne-heslo-123', $user->password));
    }

    #[Test]
    public function unticking_the_verification_clears_the_timestamp(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/users/' . $user->id, ['email_verified' => false])
            ->assertStatus(200);

        $this->assertNull($user->refresh()->email_verified_at);
    }

    #[Test]
    public function blocking_alone_leaves_the_profile_untouched(): void
    {
        $user = User::factory()->create([
            'email' => 'keep@example.test',
            'status' => ModelStatus::Published->value,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/users/' . $user->id, [
                'blocked' => true,
                'blocked_reason' => 'spam',
            ])
            ->assertStatus(200);

        $user->refresh();

        $this->assertTrue($user->isBlocked());
        $this->assertSame('spam', $user->blocked_reason);
        $this->assertSame('keep@example.test', $user->email);
        $this->assertSame(ModelStatus::Published, $user->status);
    }

    #[Test]
    public function unblocking_clears_the_reason_and_deadline(): void
    {
        $user = User::factory()->create([
            'blocked_at' => now()->subDay(),
            'blocked_until' => now()->addDay(),
            'blocked_reason' => 'spam',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/users/' . $user->id, ['blocked' => false])
            ->assertStatus(200);

        $user->refresh();

        $this->assertFalse($user->isBlocked());
        $this->assertNull($user->blocked_at);
        $this->assertNull($user->blocked_until);
        $this->assertNull($user->blocked_reason);
    }

    #[Test]
    public function an_empty_password_keeps_the_old_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('povodne-heslo')]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/users/' . $user->id, [
                'email' => $user->email,
                'password' => null,
            ])
            ->assertStatus(200);

        $this->assertTrue(Hash::check('povodne-heslo', $user->refresh()->password));
    }

    #[Test]
    public function the_email_must_stay_unique(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/users/' . $user->id, ['email' => $other->email])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function the_own_email_passes_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/users/' . $user->id, ['email' => $user->email])
            ->assertStatus(200);
    }

    #[Test]
    public function admin_cannot_update_his_own_account_here(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/users/' . $this->admin->id, ['status' => ModelStatus::Archived->value])
            ->assertStatus(403);
    }
}
