<?php

namespace Tests\Unit\Geocoding;

use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Geocoding\VenueCoordinateResolver;
use App\Services\OpenAI\ChatGPT;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VenueCoordinateResolverTest extends TestCase
{
    #[Test]
    public function building_match_wins_over_every_other_source(): void
    {
        $resolver = new VenueCoordinateResolver($this->geocoder(), $this->chatGpt());

        $result = $resolver->resolve(
            venueLat: 48.1485965,
            venueLng: 17.1077477,
            name: 'Katedrala sv. Martina',
            street: 'Rudnayovo namestie 1',
            city: 'Bratislava',
            country: 'Slovensko',
            aiLat: 1.0,
            aiLng: 2.0,
        );

        $this->assertSame(48.1485965, $result['latitude']);
        $this->assertSame(17.1077477, $result['longitude']);
        $this->assertSame('venue', $result['source']);
    }

    #[Test]
    public function address_is_used_when_the_building_was_not_found(): void
    {
        $resolver = new VenueCoordinateResolver(
            $this->geocoder(address: ['latitude' => 49.1234, 'longitude' => 21.4321]),
            $this->chatGpt(),
        );

        $result = $resolver->resolve(
            name: 'Kulturny dom Raslavice',
            street: 'Toplianska 560',
            postcode: '086 41',
            city: 'Raslavice',
            country: 'Slovensko',
        );

        $this->assertSame(49.1234, $result['latitude']);
        $this->assertSame(21.4321, $result['longitude']);
        $this->assertSame('address', $result['source']);
    }

    #[Test]
    public function ai_estimate_close_to_the_village_beats_the_village_centre(): void
    {
        $resolver = new VenueCoordinateResolver(
            $this->geocoder(municipality: ['latitude' => 49.10, 'longitude' => 21.40]),
            $this->chatGpt(),
        );

        $result = $resolver->resolve(
            name: 'Amfiteater Raslavice',
            city: 'Raslavice',
            country: 'Slovensko',
            aiLat: 49.11,
            aiLng: 21.41,
        );

        $this->assertSame(49.11, $result['latitude']);
        $this->assertSame('ai', $result['source']);
    }

    #[Test]
    public function ai_estimate_in_a_different_district_is_discarded(): void
    {
        $resolver = new VenueCoordinateResolver(
            $this->geocoder(municipality: ['latitude' => 49.10, 'longitude' => 21.40]),
            $this->chatGpt(),
        );

        // Kosice su od Raslavic ~50 km -- presne tak vznikali chybne miesta,
        // ked model trafil rovnomenny objekt v inom okrese.
        $result = $resolver->resolve(
            name: 'Amfiteater Raslavice',
            city: 'Raslavice',
            country: 'Slovensko',
            aiLat: 48.7196,
            aiLng: 21.2581,
        );

        $this->assertSame(49.10, $result['latitude']);
        $this->assertSame(21.40, $result['longitude']);
        $this->assertSame('municipality', $result['source']);
    }

    #[Test]
    public function ai_estimate_without_a_village_to_check_against_is_discarded(): void
    {
        $resolver = new VenueCoordinateResolver($this->geocoder(), $this->chatGpt());

        $result = $resolver->resolve(
            name: 'Klub pod schodmi',
            city: 'Neznama obec',
            aiLat: 48.7196,
            aiLng: 21.2581,
        );

        $this->assertNull($result['latitude']);
        $this->assertNull($result['source']);
    }

    #[Test]
    public function ai_is_asked_only_when_the_caller_allows_it(): void
    {
        $chatGpt = $this->chatGpt(['latitude' => 49.105, 'longitude' => 21.405]);

        $resolver = new VenueCoordinateResolver(
            $this->geocoder(municipality: ['latitude' => 49.10, 'longitude' => 21.40]),
            $chatGpt,
        );

        $withoutAi = $resolver->resolve(name: 'Kulturny dom', city: 'Raslavice');
        $this->assertSame('municipality', $withoutAi['source']);
        $this->assertSame(0, $chatGpt->calls);

        $withAi = $resolver->resolve(name: 'Kulturny dom', city: 'Raslavice', askAi: true);
        $this->assertSame('ai', $withAi['source']);
        $this->assertSame(49.105, $withAi['latitude']);
        $this->assertSame(1, $chatGpt->calls);
    }

    #[Test]
    public function a_failing_geocoder_never_breaks_the_caller(): void
    {
        $geocoder = new class extends NominatimGeocoder
        {
            public function lookupAddress(?string $street, ?string $postcode, ?string $city, ?string $country = null): array
            {
                throw new \RuntimeException('Nominatim down');
            }

            public function lookupMunicipality(?string $city, ?string $country = null): array
            {
                throw new \RuntimeException('Nominatim down');
            }
        };

        $result = (new VenueCoordinateResolver($geocoder, $this->chatGpt()))
            ->resolve(name: 'Kulturny dom', street: 'Hlavna 1', city: 'Raslavice');

        $this->assertSame(['latitude' => null, 'longitude' => null, 'source' => null], $result);
    }

    private function geocoder(array $address = [], array $municipality = []): NominatimGeocoder
    {
        return new class($address, $municipality) extends NominatimGeocoder
        {
            public function __construct(
                private readonly array $addressResult = [],
                private readonly array $municipalityResult = [],
            ) {}

            public function lookupAddress(?string $street, ?string $postcode, ?string $city, ?string $country = null): array
            {
                return $this->addressResult + ['latitude' => null, 'longitude' => null];
            }

            public function lookupMunicipality(?string $city, ?string $country = null): array
            {
                return $this->municipalityResult + ['latitude' => null, 'longitude' => null];
            }
        };
    }

    private function chatGpt(array $coordinates = []): ChatGPT
    {
        return new class($coordinates) extends ChatGPT
        {
            public int $calls = 0;

            public function __construct(private readonly array $coordinates = []) {}

            public function extractVenueDetails(array|string $input): array
            {
                $this->calls++;

                return $this->coordinates + ['latitude' => null, 'longitude' => null];
            }
        };
    }
}
