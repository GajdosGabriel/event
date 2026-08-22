<?php

namespace Tests\Feature\Venues;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Mapa v editore miesta ide za adresou: obec ju posunie na obec, ulica s číslom
 * ju dotiahne na dom. Test drží ten rebríček — a hlavne to, že sa nikdy
 * nezrúti: keď geokóder mlčí, formulár musí dostať prázdny výsledok, nie chybu.
 */
class DashboardVenueGeocodeTest extends EventSetupTest
{
    #[Test]
    public function geocode_returns_address_coordinates_when_street_is_filled(): void
    {
        $municipality = DB::table('municipalities')->first();

        Http::fake([
            '*nominatim*' => Http::response([[
                'lat' => '48.8945',
                'lon' => '18.0444',
                'address' => [
                    'road' => 'Vyšný šianec',
                    'house_number' => '32',
                    'postcode' => '911 01',
                    'city' => $municipality->fullname,
                    'country' => 'Slovensko',
                ],
            ]]),
        ]);

        $response = $this->postJson('/api/dashboard/venues/geocode', [
            'village_id' => $municipality->id,
            'street' => 'Vyšný šianec 32',
        ]);

        $response->assertOk();
        $response->assertJson([
            'latitude' => 48.8945,
            'longitude' => 18.0444,
            'source' => 'address',
            'city' => $municipality->fullname,
            // PSČ dopĺňa číselník, keď ho formulár neposlal.
            'postcode' => $municipality->zip,
        ]);
    }

    #[Test]
    public function geocode_falls_back_to_the_municipality_centre(): void
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

        $response = $this->postJson('/api/dashboard/venues/geocode', [
            'village_id' => $municipality->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'latitude' => 48.7,
            'longitude' => 18.1,
            'source' => 'municipality',
        ]);
    }

    #[Test]
    public function geocode_returns_empty_coordinates_when_lookup_finds_nothing(): void
    {
        $municipality = DB::table('municipalities')->first();

        Http::fake(['*nominatim*' => Http::response([])]);

        $response = $this->postJson('/api/dashboard/venues/geocode', [
            'village_id' => $municipality->id,
            'street' => 'Neexistujúca 999',
        ]);

        $response->assertOk();
        $response->assertJson([
            'latitude' => null,
            'longitude' => null,
            'source' => null,
        ]);
    }

    #[Test]
    public function geocode_requires_a_municipality(): void
    {
        $response = $this->postJson('/api/dashboard/venues/geocode', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['village_id']);
    }
}
