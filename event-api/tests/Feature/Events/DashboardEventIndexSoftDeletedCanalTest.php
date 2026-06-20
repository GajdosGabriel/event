<?php

namespace Tests\Feature\Events;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardEventIndexSoftDeletedCanalTest extends EventSetupTest
{
    #[Test]
    public function user_can_see_events_for_soft_deleted_dashboard_canal(): void
    {
        $this->canalPrimary->delete();

        $response = $this->getJson('/api/dashboard/events');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->futureEvent->id,
            'canal_id' => $this->canalPrimary->id,
        ]);
        $response->assertJsonFragment([
            'id' => $this->pastEvent->id,
            'canal_id' => $this->canalPrimary->id,
        ]);
    }
}
