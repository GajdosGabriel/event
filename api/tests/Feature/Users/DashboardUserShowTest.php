<?php

namespace Tests\Feature\Users;

use App\Models\Canal;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\UserSetupTest;

class DashboardUserShowTest extends UserSetupTest
{
    #[Test]
    public function user_can_read_his_own_profile(): void
    {
        $response = $this->getJson('/api/dashboard/users/' . $this->user->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $this->user->id);
    }

    /**
     * Regresia: endpoint zahadzoval `$id` z adresy a vracal vždy prihláseného,
     * takže dotaz na cudzí účet vyzeral ako úspech s cudzími dátami.
     */
    #[Test]
    public function user_does_not_get_his_own_profile_when_asking_for_someone_else(): void
    {
        // Skutočný cudzí kanál, nie vymyslené id: `users.canal_id` má cudzí
        // kľúč, takže riadok ukazujúci do prázdna sa už ani nedá vložiť.
        // Zámer testu to nemení — kanál z factory nie je ten prihláseného.
        $foreignUser = User::factory()->create([
            'canal_id' => Canal::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/dashboard/users/' . $foreignUser->id);

        $response->assertStatus(404);
    }
}
