<?php

namespace Tests\Feature\Users;

use App\Models\Canal;
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

    /**
     * Regresia: `unique:users` bez `ignore()` zhodilo každé uloženie profilu,
     * pri ktorom sa e-mail nemenil — teda každú zmenu čohokoľvek iného.
     */
    #[Test]
    public function user_can_save_profile_without_changing_email(): void
    {
        $response = $this->putJson('/api/dashboard/users/' . $this->user->id, [
            'email' => $this->user->email,
            'registered_via' => 'local',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => $this->user->email,
        ]);
    }

    /** Cudzí e-mail zostáva obsadený — `ignore()` uvoľňuje len vlastný riadok. */
    #[Test]
    public function user_cannot_take_over_email_of_another_user(): void
    {
        $other = User::factory()->create();

        $response = $this->putJson('/api/dashboard/users/' . $this->user->id, [
            'email' => $other->email,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    #[Test]
    public function user_cannot_update_foreign_user_outside_dashboard_scope(): void
    {
        /** @var User $foreignUser */
        // Skutočný cudzí kanál, nie vymyslené id: `users.canal_id` má cudzí
        // kľúč, takže riadok ukazujúci do prázdna sa už ani nedá vložiť.
        // Zámer testu to nemení — kanál z factory nie je ten prihláseného.
        $foreignUser = User::factory()->create([
            'canal_id' => Canal::factory()->create()->id,
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
