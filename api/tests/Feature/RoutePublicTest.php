<?php

namespace Tests\Feature;

use App\Models\Canal;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class RoutePublicTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_routes_are_accessible_without_authentication(): void
    {
        $publicRoutes = [
            '/api',
            '/api/events',
            '/api/canals',
        ];

        foreach ($publicRoutes as $route) {
            $response = $this->getJson($route);
            $response->assertOk();
        }
    }

    #[Test]
    public function test_user_route_returns_null_when_not_authenticated()
    {
        $response = $this->getJson('/api/user');
        $response->assertStatus(401);
    }

    #[Test]
    public function test_user_route_returns_user_when_authenticated()
    {
        $canal = Canal::factory()->create();
        $user = User::factory()->create([
            'canal_id' => $canal->id,
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/user');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'canal_context' => [
                    'active' => [
                        'id',
                        'name',
                    ],
                    'is_owner',
                ],
            ],
        ]);
    }
}
