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
    #[Test]
    public function a_generic_type_word_is_never_queried_without_a_town(): void
    {
        // Regresia z produkcie: buildNameVariants pridáva medzi varianty holé
        // druhové slová („amfiteater“, „kostol“). S dotazom bez obce
        // („amfiteater, Slovensko“) trafili hociktorú takú stavbu na
        // Slovensku — „Amfiteáter Košice“ tak dostal obec Námestovo a päť
        // rôznych kostolov rovnaké súradnice v Pečeniciach.
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 0);

        $queries = [];

        Http::fake([
            'https://nominatim.example/search*' => function ($request) use (&$queries) {
                $queries[] = $request['q'];

                return Http::response([], 200);
            },
        ]);

        (new NominatimGeocoder())->lookup('Amfiteater Kosice', 'Kosice', 'Slovensko');

        $this->assertNotEmpty($queries);
        $this->assertNotContains('amfiteater', $queries);
        $this->assertNotContains('amfiteater, Slovensko', $queries);

        foreach ($queries as $query) {
            $this->assertStringContainsStringIgnoringCase(
                'kosice',
                $query,
                'Druhový dotaz bez obce trafí ľubovoľnú stavbu toho typu: ' . $query,
            );
        }
    }

    #[Test]
    public function a_building_of_the_right_type_in_the_wrong_town_is_rejected(): void
    {
        // Aj keď obec v dotaze je, Nominatim je fulltext a vráti aj zásah
        // z iného okresu. Zhoda mena je pritom len druhové slovo, takže sama
        // o sebe nesmie stačiť na prijatie.
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 0);

        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Amfiteater',
                    'lat' => '49.4079',
                    'lon' => '19.4801',
                    'address' => [
                        'city' => 'Namestovo',
                        'country' => 'Slovensko',
                    ],
                ],
            ], 200),
        ]);

        $result = (new NominatimGeocoder())->lookup('Amfiteater Kosice', 'Kosice', 'Slovensko');

        $this->assertNull($result['city']);
        $this->assertNull($result['latitude']);
    }

    #[Test]
    public function a_real_venue_in_the_requested_town_still_matches(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 0);

        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Amfiteater Kosice',
                    'lat' => '48.7268',
                    'lon' => '21.2646',
                    'address' => [
                        'road' => 'Festivalove namestie',
                        'city' => 'Kosice',
                        'country' => 'Slovensko',
                    ],
                ],
            ], 200),
        ]);

        $result = (new NominatimGeocoder())->lookup('Amfiteater Kosice', 'Kosice', 'Slovensko');

        $this->assertSame('Kosice', $result['city']);
        $this->assertSame(48.7268, $result['latitude']);
    }

    #[Test]
    public function lookup_address_accepts_only_results_in_the_requested_municipality(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        // Rovnaka ulica existuje v desiatkach obci -- vysledok z inej obce je
        // rovnako bezcenny ako zly objekt.
        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => null,
                    'lat' => '48.9',
                    'lon' => '21.9',
                    'address' => [
                        'road' => 'Toplianska',
                        'house_number' => '560',
                        'city' => 'Bardejov',
                        'country' => 'Slovensko',
                    ],
                ],
                [
                    'name' => null,
                    'lat' => '49.1234',
                    'lon' => '21.4321',
                    'address' => [
                        'road' => 'Toplianska',
                        'house_number' => '560',
                        'postcode' => '086 41',
                        'village' => 'Raslavice',
                        'country' => 'Slovensko',
                    ],
                ],
            ], 200),
        ]);

        $result = (new NominatimGeocoder())->lookupAddress('Toplianska 560', '086 41', 'Raslavice', 'Slovensko');

        $this->assertSame(49.1234, $result['latitude']);
        $this->assertSame(21.4321, $result['longitude']);
        $this->assertSame('Raslavice', $result['city']);
    }

    #[Test]
    public function lookup_address_without_street_never_asks_the_geocoder(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');

        Http::fake();

        $result = (new NominatimGeocoder())->lookupAddress(null, '086 41', 'Raslavice', 'Slovensko');

        $this->assertNull($result['latitude']);
        Http::assertNothingSent();
    }

    #[Test]
    public function lookup_municipality_returns_the_centre_of_the_requested_village(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Raslavice',
                    'lat' => '49.0999',
                    'lon' => '21.4111',
                    'address' => [
                        'village' => 'Raslavice',
                        'country' => 'Slovensko',
                    ],
                ],
            ], 200),
        ]);

        $result = (new NominatimGeocoder())->lookupMunicipality('Raslavice', 'Slovensko');

        $this->assertSame(49.0999, $result['latitude']);
        $this->assertSame(21.4111, $result['longitude']);
    }

    #[Test]
    public function lookup_municipality_rejects_a_different_village(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Raslavice-Nizne',
                    'lat' => '49.5',
                    'lon' => '21.5',
                    'address' => [
                        'county' => 'okres Bardejov',
                        'country' => 'Slovensko',
                    ],
                ],
            ], 200),
        ]);

        $result = (new NominatimGeocoder())->lookupMunicipality('Raslavice', 'Slovensko');

        $this->assertNull($result['latitude']);
        $this->assertNull($result['longitude']);
    }


    /**
     * Verejny Nominatim vracia pri prekroceni limitu 429. Kym sa taky "vysledok"
     * cachoval, jedno odmietnutie znamenalo miesto bez suradnic na cely den.
     */
    #[Test]
    public function a_rate_limited_lookup_is_not_cached(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        $rateLimited = true;

        Http::fake(function () use (&$rateLimited) {
            return $rateLimited
                ? Http::response('Too many requests', 429)
                : Http::response([
                    [
                        'name' => 'Katedrala sv. Martina',
                        'lat' => '48.1485965',
                        'lon' => '17.1077477',
                        'address' => ['city' => 'Bratislava', 'country' => 'Slovensko'],
                    ],
                ], 200);
        });

        $geocoder = new NominatimGeocoder();
        $this->assertNull($geocoder->lookup('Katedrala sv. Martina', 'Bratislava')['latitude']);

        $rateLimited = false;

        $this->assertSame(48.1485965, $geocoder->lookup('Katedrala sv. Martina', 'Bratislava')['latitude']);
    }

    #[Test]
    public function a_rate_limited_municipality_lookup_is_not_cached(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        $rateLimited = true;

        Http::fake(function () use (&$rateLimited) {
            return $rateLimited
                ? Http::response('Too many requests', 429)
                : Http::response([
                    [
                        'name' => 'Sabinov',
                        'lat' => '49.1023',
                        'lon' => '21.0977',
                        'address' => ['town' => 'Sabinov', 'country' => 'Slovensko'],
                    ],
                ], 200);
        });

        $geocoder = new NominatimGeocoder();
        $this->assertNull($geocoder->lookupMunicipality('Sabinov', 'Slovensko')['latitude']);

        $rateLimited = false;

        $this->assertSame(49.1023, $geocoder->lookupMunicipality('Sabinov', 'Slovensko')['latitude']);
    }

    /**
     * „Kultúrny dom“ sa presne rovná kultúrnemu domu v každej druhej obci —
     * zhoda mena tu nie je dôkaz identity, iba typu.
     */
    #[Test]
    public function a_generic_name_matching_exactly_does_not_beat_a_wrong_town(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Kultúrny dom',
                    'display_name' => 'Kultúrny dom, 122, Červenica pri Sabinove, Slovensko',
                    'lat' => '49.1343715',
                    'lon' => '21.0256405',
                    'address' => [
                        'house_number' => '122',
                        'village' => 'Červenica pri Sabinove',
                        'country' => 'Slovensko',
                    ],
                ],
            ], 200),
        ]);

        $result = (new NominatimGeocoder())->lookup('Kultúrny dom', 'Sabinov', 'Slovensko');

        $this->assertNull($result['latitude']);
        $this->assertNull($result['longitude']);
    }

    #[Test]
    public function a_generic_name_in_the_requested_town_still_matches(): void
    {
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
        Config::set('services.nominatim.cache_ttl', 3600);

        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Kultúrny dom',
                    'display_name' => 'Kultúrny dom, Janka Borodáča, Sabinov, Slovensko',
                    'lat' => '49.0998938',
                    'lon' => '21.0981632',
                    'address' => [
                        'road' => 'Janka Borodáča',
                        'town' => 'Sabinov',
                        'country' => 'Slovensko',
                    ],
                ],
            ], 200),
        ]);

        $result = (new NominatimGeocoder())->lookup('Kultúrny dom', 'Sabinov', 'Slovensko');

        $this->assertSame(49.0998938, $result['latitude']);
        $this->assertSame(21.0981632, $result['longitude']);
        $this->assertSame('Sabinov', $result['city']);
    }
}
