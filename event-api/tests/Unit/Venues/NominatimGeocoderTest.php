<?php

namespace Tests\Unit\Venues;

use App\Services\Geocoding\NominatimGeocoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NominatimGeocoderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    #[Test]
    public function lookup_maps_nominatim_response_to_expected_fields(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.user_agent', 'Event API test geocoder');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Katedrala sv. Martina',
                    'lat' => '48.1485965',
                    'lon' => '17.1077477',
                    'address' => [
                        'road' => 'Rudnayovo namestie',
                        'house_number' => '1',
                        'postcode' => '811 01',
                        'city' => 'Bratislava',
                        'country' => 'Slovakia',
                    ],
                ],
            ], 200),
        ]);

        $result = (new NominatimGeocoder())->lookup('Katedrala sv. Martina', 'Bratislava', 'Slovakia');

        $this->assertSame('Katedrala sv. Martina', $result['name']);
        $this->assertSame('Rudnayovo namestie 1', $result['street']);
        $this->assertSame('811 01', $result['postcode']);
        $this->assertSame('Bratislava', $result['city']);
        $this->assertSame('Slovakia', $result['country']);
        $this->assertSame(48.1485965, $result['latitude']);
        $this->assertSame(17.1077477, $result['longitude']);
    }

    #[Test]
    public function lookup_returns_null_payload_when_service_fails(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => Http::response([], 500),
        ]);

        $result = (new NominatimGeocoder())->lookup('Unknown', 'Bratislava');

        $this->assertSame([
            'name' => null,
            'street' => null,
            'postcode' => null,
            'city' => null,
            'country' => null,
            'latitude' => null,
            'longitude' => null,
        ], $result);
    }

    #[Test]
    public function lookup_uses_cache_for_identical_queries(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Cached place',
                    'lat' => '48.1',
                    'lon' => '17.1',
                    'address' => [
                        'city' => 'Bratislava',
                    ],
                ],
            ], 200),
        ]);

        $geocoder = new NominatimGeocoder();

        $first = $geocoder->lookup('Cached place', 'Bratislava');
        $second = $geocoder->lookup('Cached place', 'Bratislava');

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    #[Test]
    public function lookup_tries_name_variants_and_picks_best_compatible_result(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => function ($request) {
                $query = mb_strtolower((string) data_get($request->data(), 'q', ''));

                if (str_contains($query, 'dom kultury') && str_contains($query, 'raslavice')) {
                    return Http::response([
                        [
                            'name' => 'Dom kultury',
                            'display_name' => 'Dom kultury, Toplianska 560, Raslavice, Slovakia',
                            'lat' => '49.1001',
                            'lon' => '21.3002',
                            'address' => [
                                'road' => 'Toplianska',
                                'house_number' => '560',
                                'postcode' => '086 41',
                                'village' => 'Raslavice',
                                'country' => 'Slovakia',
                            ],
                        ],
                    ], 200);
                }

                return Http::response([
                    [
                        'name' => 'Synagoga v Raslaviciach',
                        'display_name' => 'Synagoga v Raslaviciach, Raslavice, Slovakia',
                        'lat' => '49.999',
                        'lon' => '21.999',
                        'address' => [
                            'village' => 'Raslavice',
                            'country' => 'Slovakia',
                        ],
                    ],
                ], 200);
            },
        ]);

        $result = (new NominatimGeocoder())->lookup('Kultúrny dom Raslavice', 'Raslavice', 'Slovakia');

        $this->assertSame('Dom kultury', $result['name']);
        $this->assertSame('Toplianska 560', $result['street']);
        $this->assertSame('086 41', $result['postcode']);
        $this->assertSame('Raslavice', $result['city']);
        $this->assertSame('Slovakia', $result['country']);
        $this->assertSame(49.1001, $result['latitude']);
        $this->assertSame(21.3002, $result['longitude']);
    }

    #[Test]
    public function lookup_rejects_unrelated_result_from_same_city(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Synagoga v Raslaviciach',
                    'display_name' => 'Synagoga v Raslaviciach, Raslavice, Slovakia',
                    'lat' => '49.999',
                    'lon' => '21.999',
                    'address' => [
                        'village' => 'Raslavice',
                        'country' => 'Slovakia',
                    ],
                ],
            ], 200),
        ]);

        $result = (new NominatimGeocoder())->lookup('Kultúrny dom Raslavice', 'Raslavice', 'Slovakia');

        $this->assertSame([
            'name' => null,
            'street' => null,
            'postcode' => null,
            'city' => null,
            'country' => null,
            'latitude' => null,
            'longitude' => null,
        ], $result);
    }

    #[Test]
    public function lookup_supports_other_event_venue_types_beyond_cultural_houses(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => function ($request) {
                $query = mb_strtolower((string) data_get($request->data(), 'q', ''));

                if (str_contains($query, 'theatre')) {
                    return Http::response([
                        [
                            'name' => 'Theatre Jonasa Zaborskeho',
                            'display_name' => 'Theatre Jonasa Zaborskeho, Namestie legionárov 6, Presov, Slovakia',
                            'lat' => '48.9981',
                            'lon' => '21.2393',
                            'address' => [
                                'road' => 'Namestie legionarov',
                                'house_number' => '6',
                                'postcode' => '080 01',
                                'city' => 'Presov',
                                'country' => 'Slovakia',
                            ],
                        ],
                    ], 200);
                }

                return Http::response([
                    [
                        'name' => 'Kino Scala',
                        'display_name' => 'Kino Scala, Presov, Slovakia',
                        'lat' => '48.1',
                        'lon' => '21.1',
                        'address' => [
                            'city' => 'Presov',
                            'country' => 'Slovakia',
                        ],
                    ],
                ], 200);
            },
        ]);

        $result = (new NominatimGeocoder())->lookup('Divadlo Jonasa Zaborskeho v Presove', 'Presov', 'Slovakia');

        $this->assertSame('Theatre Jonasa Zaborskeho', $result['name']);
        $this->assertSame('Namestie legionarov 6', $result['street']);
        $this->assertSame('080 01', $result['postcode']);
        $this->assertSame('Presov', $result['city']);
        $this->assertSame(48.9981, $result['latitude']);
        $this->assertSame(21.2393, $result['longitude']);
    }

    #[Test]
    public function lookup_tries_split_location_variants_for_compound_venue_names(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => function ($request) {
                $query = mb_strtolower((string) data_get($request->data(), 'q', ''));

                if (str_contains($query, 'savore, sigord')) {
                    return Http::response([
                        [
                            'name' => 'Savore Sigord',
                            'display_name' => 'Savore Sigord, Sigord, Presov, Slovakia',
                            'lat' => '48.9477',
                            'lon' => '21.3065',
                            'address' => [
                                'road' => 'Sigord',
                                'house_number' => '1',
                                'postcode' => '080 01',
                                'city' => 'Presov',
                                'country' => 'Slovakia',
                            ],
                        ],
                    ], 200);
                }

                return Http::response([], 200);
            },
        ]);

        $result = (new NominatimGeocoder())->lookup('Savore Sigord', 'Presov', 'Slovakia');

        $this->assertSame('Savore Sigord', $result['name']);
        $this->assertSame('Sigord 1', $result['street']);
        $this->assertSame('080 01', $result['postcode']);
        $this->assertSame('Presov', $result['city']);
        $this->assertSame(48.9477, $result['latitude']);
        $this->assertSame(21.3065, $result['longitude']);
    }
}
