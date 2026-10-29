<?php

namespace Tests\Feature\Imports;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PeceniceVenueCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function misplaced(int $id, string $name): Venue
    {
        return Venue::factory()->create([
            'id' => $id, 'name' => $name, 'village_id' => 2612, 'street' => '1552', 'postcode' => '935 06',
            'latitude' => 48.2986958, 'longitude' => 18.7729911, 'coordinates_source' => 'address',
        ]);
    }

    public function test_misplaced_venues_move_to_their_towns_and_lose_the_invented_address(): void
    {
        $this->misplaced(4, 'kostol sv. Františka Minoriti');
        $this->misplaced(32, 'františkánsky kostol');
        $this->misplaced(102, 'Univerzitný kostol Svätej Rodiny');
        // Ručne opravené miesto (iný názov) sa nesmie prepísať.
        $fixed = $this->misplaced(19, 'Evanjelický kostol Brdárka');
        $fixedBefore = (array) DB::table('venues')->find($fixed->id);

        $migration = require database_path('migrations/2026_10_29_100000_fix_venues_misplaced_in_pecenice.php');
        $migration->up();
        $migration->up();

        $minorites = DB::table('venues')->find(4);
        $this->assertSame(242, (int) $minorites->village_id);
        $this->assertNull($minorites->street);
        $this->assertNull($minorites->postcode);
        $this->assertSame('municipality', $minorites->coordinates_source);

        $this->assertSame(3053, (int) DB::table('venues')->find(102)->village_id);

        $franciscans = DB::table('venues')->find(32);
        $this->assertSame(2612, (int) $franciscans->village_id, 'Bez jednej skutočnej obce ostáva na kontrolu.');
        $this->assertNull($franciscans->street);

        $this->assertSame($fixedBefore, (array) DB::table('venues')->find($fixed->id));
    }
}
