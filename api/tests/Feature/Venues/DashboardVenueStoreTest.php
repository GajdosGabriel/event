<?php

namespace Tests\Feature\Venues;

use App\Enums\ModelStatus;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    /**
     * Presnosť polohy sa ukladá spolu so súradnicami — bez nej by sa značka
     * v strede obce navonok nelíšila od značky na budove.
     */
    #[Test]
    public function store_keeps_the_source_of_manually_placed_coordinates(): void
    {
        Http::fake();

        $payload = $this->validPayload() + [
            'latitude' => 48.1485965,
            'longitude' => 17.1077477,
            'coordinates_source' => 'manual',
        ];

        $response = $this->postJson('/api/dashboard/venues', $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment(['coordinates_source' => 'manual']);

        $this->assertDatabaseHas('venues', [
            'name' => $payload['name'],
            'coordinates_source' => 'manual',
        ]);
    }

    #[Test]
    public function store_records_where_automatically_resolved_coordinates_came_from(): void
    {
        $payload = $this->validPayload();
        $municipality = DB::table('municipalities')->where('id', $payload['village_id'])->first();

        Http::fake([
            '*nominatim*' => Http::response([
                [
                    'name' => $payload['name'],
                    'lat' => '48.1485965',
                    'lon' => '17.1077477',
                    'address' => [
                        'road' => 'Main Street',
                        'house_number' => '1',
                        'city' => $municipality->shortname,
                        'country' => 'Slovensko',
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/api/dashboard/venues', $payload)->assertStatus(201);

        $this->assertDatabaseHas('venues', [
            'name' => $payload['name'],
            'coordinates_source' => 'venue',
        ]);
    }

    #[Test]
    public function store_rejects_an_unknown_coordinates_source(): void
    {
        Http::fake();

        $response = $this->postJson('/api/dashboard/venues', $this->validPayload() + [
            'latitude' => 48.1485965,
            'longitude' => 17.1077477,
            'coordinates_source' => 'guess',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('coordinates_source');
    }
}
