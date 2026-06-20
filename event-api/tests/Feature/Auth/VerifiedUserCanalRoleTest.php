<?php

namespace Tests\Feature\Auth;

use App\Enums\ModelStatus;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VerifiedUserCanalRoleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function verified_user_gets_owned_canal_membership_when_email_is_verified_later(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $user->refresh();

        $this->assertNotNull($user->canal_id);
        $this->assertDatabaseHas('canal_user', [
            'user_id' => $user->id,
            'canal_id' => $user->canal_id,
            'is_owner' => 1,
            'status' => ModelStatus::Published->value,
        ]);
    }

    #[Test]
    public function verified_user_gets_owned_canal_membership_when_created_as_verified(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->refresh();

        $this->assertNotNull($user->canal_id);
        $this->assertDatabaseHas('canal_user', [
            'user_id' => $user->id,
            'canal_id' => $user->canal_id,
            'is_owner' => 1,
            'status' => ModelStatus::Published->value,
        ]);
    }

    #[Test]
    public function verified_user_can_access_dashboard_venues_and_municipality_overview(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->refresh();
        $user->givePermissionTo(['event.view', 'venue.view']);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/dashboard/events/municipalities-overview')
            ->assertOk();

        $this->getJson('/api/dashboard/venues?page=1&per_page=6')
            ->assertOk();
    }
}

