<?php

namespace Tests\Feature\Users;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestSupport\UserSetupTest;

class RoleManagementEndpointsTest extends UserSetupTest
{
    #[Test]
    public function super_admin_can_sync_dashboard_user_roles(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $response = $this->putJson('/api/dashboard/users/' . $target->id . '/roles', [
            'roles' => ['canal-editor'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'user_id' => $target->id,
        ]);

        $target->refresh();
        $this->assertTrue($target->hasRole('canal-editor'));
    }

    #[Test]
    public function regular_user_cannot_sync_dashboard_user_roles(): void
    {
        $target = User::factory()->create();

        /** @var User $regularUser */
        $regularUser = User::factory()->create();
        $this->actingAs($regularUser, 'sanctum');

        $response = $this->putJson('/api/dashboard/users/' . $target->id . '/roles', [
            'roles' => ['canal-editor'],
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function dashboard_sync_roles_validates_unknown_role_name(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $response = $this->putJson('/api/dashboard/users/' . $target->id . '/roles', [
            'roles' => ['unknown-role'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['roles.0']);
    }

    #[Test]
    public function admin_roles_endpoint_requires_super_admin_role(): void
    {
        Role::findOrCreate('canal-editor', 'web');

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(403);

        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $response = $this->getJson('/api/admin/roles');
        $response->assertStatus(200);
    }
}
