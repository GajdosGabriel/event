<?php

namespace Tests\Feature\Users;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\UserSetupTest;

class DashboardUserUpdateTest extends UserSetupTest
{
    #[Test]
    public function user_can_update_himself_from_dashboard_scope(): void
    {
        $payload = [
            'email' => 'updated.' . $this->user->id . '@example.test',
            'registered_via' => 'local',
        ];

        $response = $this->putJson('/api/dashboard/users/' . $this->user->id, $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => $payload['email'],
            'registered_via' => $payload['registered_via'],
        ]);
    }

    #[Test]
    public function user_cannot_update_foreign_user_outside_dashboard_scope(): void
    {
        /** @var User $foreignUser */
        $foreignUser = User::factory()->create([
            'canal_id' => 999999,
        ]);

        $response = $this->putJson('/api/dashboard/users/' . $foreignUser->id, [
            'email' => 'foreign.' . $foreignUser->id . '@example.test',
        ]);

        $response->assertStatus(404);

        $this->assertDatabaseHas('users', [
            'id' => $foreignUser->id,
            'email' => $foreignUser->email,
        ]);
    }
}
