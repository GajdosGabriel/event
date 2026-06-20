<?php

namespace Tests\Feature\Canal;


use PHPUnit\Framework\Attributes\Test;
use App\Models\Canal; // Import the Canal model
use Illuminate\Support\Str; // For generating random strings
use Tests\TestSupport\CanalSetupTest;

class DashboardCanalUpdateTest extends CanalSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->formCanal = Canal::factory()->make()->toArray();
    }

    #[Test]
    public function user_cannot_update_canal_from_dashboard_scope(): void
    {
        $canal = $this->user->canals->first();
        $payload = array_merge($this->formCanal, [
            'name' => $this->formCanal['name'] . Str::random(5),
            'body' => $this->formCanal['body'] . Str::random(30),
            'published_at' => now(),
        ]);

        $response = $this->putJson("/api/dashboard/canals/{$canal->id}", $payload);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('canals', [
            'id' => $canal->id,
            'name' => $payload['name'],
            'body' => $payload['body'],
        ]);
    }

    #[Test]
    public function user_cannot_update_soft_deleted_canal_from_dashboard_scope(): void
    {
        $canal = $this->user->canals->first();
        $canal->delete();

        $payload = array_merge($this->formCanal, [
            'name' => $this->formCanal['name'] . Str::random(5),
            'body' => $this->formCanal['body'] . Str::random(30),
            'published_at' => now(),
        ]);

        $response = $this->putJson("/api/dashboard/canals/{$canal->id}", $payload);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('canals', [
            'id' => $canal->id,
            'name' => $payload['name'],
            'body' => $payload['body'],
        ]);

        $this->assertSoftDeleted('canals', [
            'id' => $canal->id,
        ]);
    }
}
