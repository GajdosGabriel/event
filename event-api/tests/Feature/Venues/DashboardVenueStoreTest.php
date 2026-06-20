<?php

namespace Tests\Feature\Venues;

use App\Enums\ModelStatus;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Canal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardVenueStoreTest extends EventSetupTest
{
    private function validPayload(): array
    {
        return [
            'canal_id' => $this->canalPrimary->id,
            'village_id' => (int) DB::table('municipalities')->value('id'),
            'name' => 'Venue ' . Str::random(8),
            'street' => 'Main Street 1',
            'postcode' => '811 01',
            'body' => 'Venue body ' . Str::random(16),
            'country' => 'Slovensko',
        ];
    }

    #[Test]
    public function store_allows_only_required_fields(): void
    {
        $payload = [
            'canal_id' => $this->canalPrimary->id,
            'village_id' => (int) DB::table('municipalities')->value('id'),
            'name' => 'Venue ' . Str::random(8),
        ];

        $response = $this->postJson('/api/dashboard/venues', $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'name' => $payload['name'],
            'canal_id' => $payload['canal_id'],
            'village_id' => $payload['village_id'],
        ]);

        $this->assertDatabaseHas('venues', [
            'name' => $payload['name'],
            'village_id' => $payload['village_id'],
            'street' => null,
            'postcode' => null,
        ]);
        $this->assertDatabaseHas('canal_venue', [
            'canal_id' => $payload['canal_id'],
            'venue_id' => $response->json('id'),
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
        ]);
    }

    #[Test]
    public function store_requires_canal_id(): void
    {
        $payload = $this->validPayload();
        unset($payload['canal_id']);

        $response = $this->postJson('/api/dashboard/venues', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['canal_id']);

        $this->assertDatabaseMissing('venues', [
            'name' => $payload['name'],
        ]);
    }

    #[Test]
    public function store_can_assign_venue_to_multiple_accessible_canals(): void
    {
        $secondCanal = Canal::factory()->create();
        $this->user->canals()->attach($secondCanal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = $this->validPayload();
        unset($payload['canal_id']);
        $payload['canal_ids'] = [$this->canalPrimary->id, $secondCanal->id];

        $response = $this->postJson('/api/dashboard/venues', $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'name' => $payload['name'],
            'canal_id' => $this->canalPrimary->id,
            'canal_ids' => $payload['canal_ids'],
        ]);

        foreach ($payload['canal_ids'] as $canalId) {
            $this->assertDatabaseHas('canal_venue', [
                'canal_id' => $canalId,
                'venue_id' => $response->json('id'),
                'status' => ModelStatus::Published->value,
            ]);
        }
    }

    #[Test]
    public function store_rejects_inaccessible_canal_assignments(): void
    {
        $foreignCanal = Canal::factory()->create();

        $payload = $this->validPayload();
        unset($payload['canal_id']);
        $payload['canal_ids'] = [$this->canalPrimary->id, $foreignCanal->id];

        $response = $this->postJson('/api/dashboard/venues', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['canal_ids']);

        $this->assertDatabaseMissing('venues', [
            'name' => $payload['name'],
        ]);
    }

    #[Test]
    public function store_requires_village_id(): void
    {
        $payload = $this->validPayload();
        unset($payload['village_id']);

        $response = $this->postJson('/api/dashboard/venues', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['village_id']);

        $this->assertDatabaseMissing('venues', [
            'name' => $payload['name'],
        ]);
    }
}

