<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

class RouteProtectedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_authenticated_routes_redirect_unauthenticated_users()
    {
        $protectedRoutes = [
            '/api/dashboard',
            '/api/dashboard/canals',
            '/api/dashboard/events',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->getJson($route);
            $response->assertStatus(401);
        }
    }

    #[Test]
    public function test_authenticated_routes_are_accessible_to_logged_in_users()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('canal-editor');
        $user->givePermissionTo(['canal.view', 'event.view']);
        $this->actingAs($user, 'sanctum');

        $protectedRoutes = [
            '/api/dashboard',
            '/api/dashboard/canals',
            '/api/dashboard/events',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->getJson($route);
            $response->assertStatus(200);
        }
    }
}
