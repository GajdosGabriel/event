<?php

namespace Tests\Feature\Geocoding;

use App\Jobs\BackfillPlaceCoordinates;
use App\Models\Canal;
use App\Models\CoordinateLookup;
use App\Models\Municipality;
use App\Models\Venue;
use App\Repositories\Contracts\CanalRepository;
use App\Repositories\Contracts\VenueRepository;
use App\Services\Geocoding\PlaceCoordinateBackfill;
use App\Support\NationwideCoordinates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Uloženie formulára na geokóder nečaká (na produkcii to bolo 32 s) a
 * polohu hľadá len vtedy, keď sa zmenilo niečo, z čoho sa odvodzuje.
 */
class CoordinateLookupOnSaveTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function saving_the_website_of_a_canal_without_location_does_not_look_it_up(): void
    {
        $canal = $this->canalWithoutLocation();
        Queue::fake();

        app(CanalRepository::class)->update($canal->id, ['website' => 'https://iny-web.sk']);

        Queue::assertNotPushed(BackfillPlaceCoordinates::class);
    }

    #[Test]
    public function changing_the_address_of_a_canal_queues_the_lookup_instead_of_waiting(): void
    {
        $canal = $this->canalWithoutLocation();
        Queue::fake();

        app(CanalRepository::class)->update($canal->id, ['street' => 'Hlavná 12']);

        Queue::assertPushed(BackfillPlaceCoordinates::class, fn ($job) => $job->modelClass === Canal::class && $job->modelId === $canal->id);
        $this->assertTrue(NationwideCoordinates::isPlaceholder($canal->fresh()->latitude, $canal->fresh()->longitude));
    }

    #[Test]
    public function a_venue_follows_the_same_rule(): void
    {
        $venue = Venue::factory()->create(['latitude' => null, 'longitude' => null]);
        Queue::fake();

        app(VenueRepository::class)->update($venue->id, ['website' => 'https://iny-web.sk']);
        Queue::assertNotPushed(BackfillPlaceCoordinates::class);

        app(VenueRepository::class)->update($venue->id, ['street' => 'Hlavná 12']);
        Queue::assertPushed(BackfillPlaceCoordinates::class, fn ($job) => $job->modelClass === Venue::class && $job->modelId === $venue->id);
    }

    #[Test]
    public function the_job_fills_in_the_coordinates(): void
    {
        $canal = $this->canalWithoutLocation();
        $municipality = Municipality::query()->findOrFail($canal->municipality_id);

        Http::fake([
            '*nominatim*' => Http::response([[
                'lat' => '48.1500',
                'lon' => '17.1100',
                'address' => ['city' => $municipality->shortname ?: $municipality->fullname, 'country' => 'Slovensko'],
            ]]),
            '*' => Http::response([], 500),
        ]);

        (new BackfillPlaceCoordinates(Canal::class, $canal->id))->handle(app(PlaceCoordinateBackfill::class));

        $fresh = $canal->fresh();
        $this->assertFalse(NationwideCoordinates::isPlaceholder($fresh->latitude, $fresh->longitude));
        $this->assertNotNull($fresh->coordinates_source);
    }

    #[Test]
    public function the_same_inputs_are_not_geocoded_twice(): void
    {
        $canal = $this->canalWithoutLocation();
        Http::fake(['*' => Http::response([])]);
        $backfill = app(PlaceCoordinateBackfill::class);

        $backfill->backfill($canal);
        $first = CoordinateLookup::for($canal)->looked_up_at;
        $this->assertNull(CoordinateLookup::for($canal)->source);

        // Nič sa nenašlo a podklady sú tie isté — znova sa nehľadá.
        $this->travel(5)->minutes();
        $backfill->backfill($canal->fresh());
        $this->assertTrue($first->equalTo(CoordinateLookup::for($canal)->looked_up_at));

        // Nová adresa je nový podklad.
        $canal->forceFill(['street' => 'Hlavná 12'])->save();
        $backfill->backfill($canal->fresh());
        $second = CoordinateLookup::for($canal)->looked_up_at;
        $this->assertTrue($second->greaterThan($first));

        // Ručné spustenie odtlačok ignoruje.
        $this->travel(5)->minutes();
        $backfill->backfill($canal->fresh(), force: true);
        $this->assertTrue(CoordinateLookup::for($canal)->looked_up_at->greaterThan($second));
    }

    #[Test]
    public function a_canal_is_never_geocoded_by_ai_or_by_its_name(): void
    {
        $canal = $this->canalWithoutLocation();
        Http::fake(['*' => Http::response([])]);

        app(PlaceCoordinateBackfill::class)->backfill($canal);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai'));
        Http::assertNotSent(fn ($request) => str_contains(urldecode($request->url()), $canal->name));
    }

    private function canalWithoutLocation(): Canal
    {
        $municipalityId = Municipality::query()
            ->where('id', '<>', (int) Municipality::nationwideId())
            ->whereNotNull('shortname')
            ->value('id');

        return Canal::factory()->create([
            'municipality_id' => $municipalityId,
            'latitude' => null,
            'longitude' => null,
        ]);
    }
}
