<?php

namespace Tests\Feature\Venues;

use App\Models\Canal;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\Venue;
use App\Repositories\Contracts\VenueRepository;
use App\Support\NationwideCoordinates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Miesto ani kanál sa neuloží bez súradníc — inak z mapy ticho zmizne.
 *
 * Chýbajúcu polohu zastupuje stred Slovenska, ktorý geokóder smie neskôr
 * spresniť. Výnimkou je zberné „Celé Slovensko": tam je stred Slovenska
 * správna odpoveď a spresňovať ho nemá čo.
 */
class AlwaysHasCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_venue_saved_without_coordinates_gets_the_centre_of_slovakia(): void
    {
        $venue = Venue::factory()->create(['latitude' => null, 'longitude' => null]);

        $this->assertPlaceholder($venue->fresh());
    }

    #[Test]
    public function a_venue_keeps_coordinates_it_already_has(): void
    {
        $venue = Venue::factory()->create(['latitude' => 48.1, 'longitude' => 17.1, 'coordinates_source' => 'manual']);

        $venue->update(['name' => 'Iný názov']);

        $this->assertEqualsWithDelta(48.1, $venue->fresh()->latitude, 0.000001);
        $this->assertSame('manual', $venue->fresh()->coordinates_source);
    }

    #[Test]
    public function clearing_coordinates_falls_back_to_the_centre_of_slovakia(): void
    {
        $venue = Venue::factory()->create(['latitude' => 48.1, 'longitude' => 17.1]);

        $venue->update(['latitude' => null, 'longitude' => null]);

        $this->assertPlaceholder($venue->fresh());
    }

    #[Test]
    public function a_canal_saved_without_coordinates_gets_the_centre_of_slovakia(): void
    {
        $canal = Canal::factory()->create();

        $this->assertPlaceholder($canal->fresh());
    }

    #[Test]
    public function backfill_refines_the_placeholder_but_leaves_nationwide_venues_alone(): void
    {
        $municipality = DB::table('municipalities')->where('id', '<>', Municipality::nationwideId())->first();

        $local = Venue::factory()->create(['village_id' => $municipality->id, 'street' => null, 'latitude' => null, 'longitude' => null]);
        $nationwide = Venue::factory()->create(['village_id' => Municipality::nationwideId(), 'latitude' => null, 'longitude' => null]);

        Http::fake([
            '*nominatim*' => Http::response([[
                'lat' => '48.5',
                'lon' => '18.5',
                'name' => $municipality->fullname,
                'address' => ['city' => $municipality->fullname, 'country' => 'Slovensko'],
            ]]),
            '*' => Http::response([], 500),
        ]);

        app(VenueRepository::class)->backfillMissingCoordinates();

        $this->assertFalse(NationwideCoordinates::isPlaceholder($local->fresh()->latitude, $local->fresh()->longitude));
        $this->assertEqualsWithDelta(48.5, $local->fresh()->latitude, 0.000001);
        $this->assertPlaceholder($nationwide->fresh());
    }

    #[Test]
    public function the_placeholder_is_never_near_anyone(): void
    {
        // Filter „v mojom okolí" okolo stredu Slovenska: skutočné miesto
        // pár stoviek metrov odtiaľ áno, zástupný bod nie.
        $placeholder = Venue::factory()->create(['latitude' => null, 'longitude' => null]);
        $real = Venue::factory()->create(['latitude' => 48.745, 'longitude' => 19.46]);

        $unknown = Event::factory()->create(['venue_id' => $placeholder->id]);
        $near = Event::factory()->create(['venue_id' => $real->id]);

        $ids = Event::query()
            ->whereIn('id', [$unknown->id, $near->id])
            ->byDistance(NationwideCoordinates::LATITUDE, NationwideCoordinates::LONGITUDE, 10.0)
            ->pluck('id');

        $this->assertSame([$near->id], $ids->all());
    }

    private function assertPlaceholder(Venue|Canal $model): void
    {
        $this->assertTrue(
            NationwideCoordinates::isPlaceholder($model->latitude, $model->longitude),
            sprintf('Očakávaný stred Slovenska, dostal som %s, %s.', $model->latitude, $model->longitude),
        );
        $this->assertSame(NationwideCoordinates::SOURCE, $model->coordinates_source);
    }
}
