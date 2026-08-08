<?php

namespace Tests\Feature\Organizations;

use App\Models\Canal;
use App\Models\Organization;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\UserSetupTest;

class DashboardOrganizationEndpointsTest extends UserSetupTest
{
    #[Test]
    public function user_with_permissions_can_list_organizations(): void
    {
        $organization = Organization::query()->create([
            'title' => 'Organization One',
            'status' => 'draft',
        ]);

        // Do dashboardu sa firma dostane len cez kanál používateľa —
        // `organization.view` samo o sebe nestačí, inak by výpis ukazoval
        // všetky firmy v systéme.
        Canal::query()
            ->whereKey($this->user->canal_id)
            ->update(['organization_id' => $organization->id]);

        $response = $this->getJson('/api/dashboard/organizations');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'title' => 'Organization One',
        ]);
    }

    #[Test]
    public function organization_without_a_canal_is_not_listed(): void
    {
        Organization::query()->create([
            'title' => 'Firma bez kanála',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/dashboard/organizations');

        $response->assertStatus(200);
        $response->assertJsonMissing(['title' => 'Firma bez kanála']);
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
