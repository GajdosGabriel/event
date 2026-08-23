<?php

namespace Tests\Feature\Canal;

use App\Models\Canal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

/**
 * Kanál má odkedy má formulár adresy aj rozpísanú adresu sídla — rovnaké
 * stĺpce ako miesto, aby ich vedel obslúžiť ten istý editor aj geokóder.
 */
class DashboardCanalAddressTest extends CanalSetupTest
{
    #[Test]
    public function canal_can_be_created_with_a_full_address(): void
    {
        // Backfill súradníc sa nesmie pýtať von — kanál si ich posiela sám.
        Http::fake(['*' => Http::response([])]);

        $payload = array_merge($this->formCanal, [
            'name' => 'Kanál s adresou '.uniqid(),
            'street' => 'Hlavná 12',
            'postcode' => '911 01',
            'country' => 'Slovensko',
            'latitude' => 48.8945,
            'longitude' => 18.0444,
            'coordinates_source' => 'address',
        ]);

        $response = $this->postJson('/api/dashboard/canals', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('canals', [
            'name' => $payload['name'],
            'street' => 'Hlavná 12',
            'postcode' => '911 01',
            'country' => 'Slovensko',
            'coordinates_source' => 'address',
        ]);
    }

    #[Test]
    public function canal_address_is_returned_by_the_api(): void
    {
        Http::fake(['*' => Http::response([])]);

        $canal = $this->canalPrimary;
        $canal->forceFill([
            'street' => 'Nám. SNP 1',
            'postcode' => '974 01',
            'country' => 'Slovensko',
            'coordinates_source' => 'municipality',
        ])->save();

        $response = $this->getJson('/api/dashboard/canals/'.$canal->id);

        $response->assertOk();
        $response->assertJsonPath('street', 'Nám. SNP 1');
        $response->assertJsonPath('postcode', '974 01');
        $response->assertJsonPath('country', 'Slovensko');
        $response->assertJsonPath('coordinates_source', 'municipality');
    }

    #[Test]
    public function address_beats_the_canal_name_when_coordinates_are_backfilled(): void
    {
        $municipality = DB::table('municipalities')->first();
        // Geokóder berie adresu len vtedy, keď sedí obec — inak by rovnaká
        // ulica z iného mesta prešla ako zhoda.
        $city = $municipality->shortname ?: $municipality->fullname;

        Http::fake([
            '*nominatim*' => Http::response([[
                'lat' => '48.1500',
                'lon' => '17.1100',
                'address' => [
                    'road' => 'Hlavná',
                    'house_number' => '12',
                    'city' => $city,
                    'country' => 'Slovensko',
                ],
            ]]),
        ]);

        $payload = array_merge($this->formCanal, [
            'name' => 'Kanál bez súradníc '.uniqid(),
            'municipality_id' => $municipality->id,
            'street' => 'Hlavná 12',
            'postcode' => '811 01',
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->postJson('/api/dashboard/canals', $payload);

        $response->assertStatus(201);

        $canal = Canal::query()->where('name', $payload['name'])->firstOrFail();

        $this->assertNotNull($canal->latitude);
        $this->assertNotNull($canal->longitude);
        // Presnosť sa zapisuje spolu so súradnicami — inak sa stred obce
        // navonok nelíši od presnej adresy.
        $this->assertNotNull($canal->coordinates_source);
    }

    #[Test]
    public function geocode_endpoint_accepts_a_municipality_id(): void
    {
        $municipality = DB::table('municipalities')->first();

        Http::fake([
            '*nominatim*' => Http::response([[
                'lat' => '48.7',
                'lon' => '18.1',
                'name' => $municipality->fullname,
                'address' => [
                    'city' => $municipality->fullname,
                    'country' => 'Slovensko',
                ],
            ]]),
        ]);

        $response = $this->postJson('/api/dashboard/geocode', [
            'municipality_id' => $municipality->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'latitude' => 48.7,
            'longitude' => 18.1,
            'source' => 'municipality',
        ]);
    }

    #[Test]
    public function geocode_endpoint_requires_a_municipality(): void
    {
        $response = $this->postJson('/api/dashboard/geocode', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['municipality_id']);
    }
}
