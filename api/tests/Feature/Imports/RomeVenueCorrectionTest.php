<?php

namespace Tests\Feature\Imports;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RomeVenueCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_rome_venue_is_corrected_without_changing_other_venues(): void
    {
        $venue = Venue::factory()->create(['street' => 'Via di Monserrato 62', 'country' => 'Slovensko']);
        DB::table('venues')->where('id', $venue->id)->update(['slug' => 'kostol-san-girolamo-della-carita']);
        $other = Venue::factory()->create(['country' => 'Slovensko']);
        $otherBefore = (array) DB::table('venues')->find($other->id);
        $migration = require database_path('migrations/2026_10_04_150000_correct_rome_venue.php');
        $migration->up();
        $migration->up();
        $corrected = DB::table('venues')->find($venue->id);
        $city = DB::table('municipalities')->find($corrected->village_id);
        $this->assertSame('Rím', $city->shortname);
        $this->assertSame(0, $city->region_id);
        $this->assertSame('Taliansko', $corrected->country);
        $this->assertSame('00186', $corrected->postcode);
        $this->assertEqualsWithDelta(41.8953618, (float) $corrected->latitude, 0.000001);
        $this->assertEqualsWithDelta(12.4702096, (float) $corrected->longitude, 0.000001);
        $this->assertSame(1, DB::table('municipalities')->where('slug', 'rim')->count());
        $this->assertSame($otherBefore, (array) DB::table('venues')->find($other->id));
        $resolved = app(\App\Services\Geocoding\MunicipalityGeocodeResolver::class)->catalogOnly('Rím');
        $this->assertSame($city->id, $resolved);
    }
}
