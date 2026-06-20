<?php

namespace Tests\Feature\Organizations;

use App\Models\Organization;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\UserSetupTest;

class AdminOrganizationEndpointsTest extends UserSetupTest
{
    #[Test]
    public function non_super_admin_cannot_access_admin_organization_index(): void
    {
        $response = $this->getJson('/api/admin/organizations');

        $response->assertStatus(403);
    }

    #[Test]
    public function super_admin_can_access_admin_organization_index(): void
    {
        Organization::query()->create([
            'title' => 'Admin Organization',
            'status' => 'draft',
        ]);

        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $response = $this->getJson('/api/admin/organizations');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'title' => 'Admin Organization',
        ]);
    }
}
