<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Venue;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * „V mojom okolí" — okruh okolo bodu z prehliadača.
 *
 * Súradnice sú na mieste (`venues`), nie na podujatí, takže filter overuje aj
 * to, že podujatie bez miesta alebo bez súradníc do okruhu nespadne.
 */
class PublicEventNearbyTest extends EventSetupTest
{
    /** Bratislava, Hlavné námestie — východiskový bod všetkých testov. */
    private const ORIGIN = ['latitude' => 48.1440, 'longitude' => 17.1097];

    #[Test]
    public function event_inside_the_radius_is_returned(): void
    {
        $event = $this->publishedEventAt(48.1500, 17.1200); // ~1 km

        $ids = $this->nearbyIds(radiusKm: 10);

        $this->assertContains($event->id, $ids);
    }

    #[Test]
    public function event_outside_the_radius_is_filtered_out(): void
    {
        // Košice — cez 300 km, teda mimo akéhokoľvek rozumného okruhu.
        $far = $this->publishedEventAt(48.7164, 21.2611);

        $ids = $this->nearbyIds(radiusKm: 25);

        $this->assertNotContains($far->id, $ids);
    }

    /**
     * Hranica okruhu musí rezať naozaj podľa vzdušnej vzdialenosti, nie podľa
     * hrubého obdĺžnika, ktorý filter používa ako predsito — v jeho rohoch je
     * bod ďalej než polomer.
     */
    #[Test]
    public function rectangle_prefilter_does_not_leak_events_beyond_the_radius(): void
    {
        // Diagonálne od stredu: v obdĺžniku ±10 km áno, vo vzdialenosti ~13 km nie.
        $corner = $this->publishedEventAt(48.2340, 17.2440);

        $this->assertNotContains($corner->id, $this->nearbyIds(radiusKm: 10));
        $this->assertContains($corner->id, $this->nearbyIds(radiusKm: 25));
    }

    #[Test]
    public function event_without_venue_coordinates_is_not_nearby(): void
    {
        $event = $this->publishedEventAt(null, null);

        $this->assertNotContains($event->id, $this->nearbyIds(radiusKm: 100));
    }

    /**
     * Nezmyselná poloha filter ticho vypne. Prichádza z prehliadača, teda
     * z prostredia, ktoré nemáme pod kontrolou — 422 by vyzerala ako porucha
     * portálu, hoci ide o zoznam, ktorý sa dá ukázať aj bez polohy.
     */
    #[Test]
    public function invalid_coordinates_fall_back_to_the_plain_list(): void
    {
        $far = $this->publishedEventAt(48.7164, 21.2611);

        $this->app['auth']->forgetGuards();

        $ids = collect(
            $this->getJson('/api/events?per_page=100&latitude=999&longitude=17&radius_km=5')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertContains($far->id, $ids);
    }

    /** @return array<int, int> */
    private function nearbyIds(float $radiusKm): array
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(sprintf(
            '/api/events?per_page=100&latitude=%s&longitude=%s&radius_km=%s',
            self::ORIGIN['latitude'],
            self::ORIGIN['longitude'],
            $radiusKm,
        ))->assertOk();

        return collect($response->json('data'))->pluck('id')->all();
    }

    private function publishedEventAt(?float $latitude, ?float $longitude): Event
    {
        $venue = Venue::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'village_id' => (int) $this->canalPrimary->municipality_id,
            'status' => ModelStatus::Published->value,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        return Event::factory()->future()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'start_at' => Carbon::now()->addDays(5)->setTime(19, 0),
        ]);
    }
}
