<?php

namespace Tests\Feature\Canal;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

class DashboardCanalDestroyTest extends CanalSetupTest
{
    #[Test]
    public function user_cannot_delete_canal_from_dashboard_scope(): void
    {
        $response = $this->deleteJson('/api/dashboard/canals/' . $this->canalPrimary->id);

        $response->assertStatus(422);

        $this->assertNotSoftDeleted('canals', [
            'id' => $this->canalPrimary->id,
        ]);
    }
}
