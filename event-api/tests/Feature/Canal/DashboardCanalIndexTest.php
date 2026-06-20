<?php

namespace Tests\Feature\Canals;

use App\Enums\ModelStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Event;
use App\Models\User;
use App\Models\Canal;
use Illuminate\Foundation\Testing\DatabaseTransactions; // Používame DatabaseTransactions pre testy,
use App\Repositories\Contracts\CanalRepository;
use Tests\TestSupport\CanalSetupTest;


class DashboardCanalIndexTest extends CanalSetupTest
{
 
    public function test_user_can_see_yours_primary_canal()
    {
        $response = $this->getJson('/api/dashboard/canals');

        // 3. Overte konkrétne dáta
        $response->assertJsonFragment([
            'id' => $this->canalPrimary->id,
            'name' => $this->canalPrimary->name,
            'is_owner' => 1,
            'status' => ModelStatus::Published->value
        ]);
    }

    public function test_user_can_filter_dashboard_canals_by_status(): void
    {
        $blockedCanal = Canal::factory()->create([
            'status' => ModelStatus::Blocked->value,
            'published_at' => now(),
        ]);

        $publishedCanal = Canal::factory()->create([
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);

        $this->user->canals()->syncWithoutDetaching([
            $blockedCanal->id => ['is_owner' => true, 'status' => ModelStatus::Published->value],
            $publishedCanal->id => ['is_owner' => true, 'status' => ModelStatus::Published->value],
        ]);

        $response = $this->getJson('/api/dashboard/canals?status=blocked');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $blockedCanal->id]);
        $response->assertJsonMissing(['id' => $publishedCanal->id]);
    }
}

