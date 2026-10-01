<?php

namespace Tests\Feature\Canals;

use Illuminate\Foundation\Testing\DatabaseTransactions; // Používame DatabaseTransactions pre testy,
use Tests\TestSupport\CanalSetupTest;

class DashboardCanalShowTest extends CanalSetupTest
{
    public function test_user_can_show_primary_canal()
    {
        $response = $this->getJson('/api/dashboard/canals/'.$this->canalPrimary->id);

        // dump($response->getContent());

        // // 3. Overte konkrétne dáta
        $response->assertJsonFragment([
            'id' => $this->canalPrimary->id,
            'name' => $this->canalPrimary->name,
        ]);
    }
}
