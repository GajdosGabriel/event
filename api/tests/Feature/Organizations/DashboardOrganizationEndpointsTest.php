<?php

namespace Tests\Feature\Organizations;

use App\Models\Organization;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\UserSetupTest;

class DashboardOrganizationEndpointsTest extends UserSetupTest
{
    #[Test]
    public function user_with_permissions_can_list_organizations(): void
    {
        Organization::query()->create([
            'title' => 'Organization One',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/dashboard/organizations');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'title' => 'Organization One',
        ]);
    }

    #[Test]
    public function user_with_permissions_can_create_organization(): void
    {
        // Rola canal-editor z UserSetupTest má len organization.view, takže
        // výpis prejde, ale zakladanie nie. Test overuje práve povolený prípad.
        $this->user->givePermissionTo('organization.create');

        $payload = [
            'title' => 'New Organization',
            'status' => 'draft',
            'published' => true,
        ];

        $response = $this->postJson('/api/dashboard/organizations', $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'title' => 'New Organization',
        ]);

        $this->assertDatabaseHas('organizations', [
            'title' => 'New Organization',
            'slug' => 'new-organization',
        ]);
    }

    #[Test]
    public function roleless_user_cannot_list_organizations(): void
    {
        /** @var User $rolelessUser */
        $rolelessUser = User::factory()->create();
        $this->actingAs($rolelessUser, 'sanctum');

        $response = $this->getJson('/api/dashboard/organizations');

        $response->assertStatus(403);
    }
}
