<?php

namespace Tests\Feature\Users;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\UserSetupTest;

class DashboardUserDestroyTest extends UserSetupTest
{
    #[Test]
    public function user_cannot_delete_himself_from_dashboard_scope(): void
    {
        $response = $this->deleteJson('/api/dashboard/users/' . $this->user->id);

        $response->assertStatus(403);

        $this->assertNotSoftDeleted('users', [
            'id' => $this->user->id,
        ]);
    }
}
