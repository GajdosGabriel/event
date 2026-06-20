<?php

namespace Tests\Unit\Venues;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Geocoding\MunicipalityResolver;
use App\Services\OpenAI\ChatGPT;
use App\Services\OpenAI\Detector;
use App\Services\Places\WikipediaPlaceEnricher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DetectorVenueDetailsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function detect_venue_details_prefers_geocoded_address_and_coordinates(): void
    {
        $municipalityId = DB::table('municipalities')->insertGetId([
            'fullname' => 'Bratislava',
            'shortname' => 'Bratislava',
            'zip' => '81101',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $chatGpt = new class extends ChatGPT {
            public function extractVenueDetails(array|string $input): array
            {
                return [
                    'name' => 'Katedrala sv. Martina',
                    'street' => 'Nepresna adresa',
                    'postcode' => null,
                    'city' => 'Bratislava',
                    'country' => 'Slovakia',
                    'latitude' => 1.0,
                    'longitude' => 2.0,
                ];
            }
        };

        $geocoder = new class extends NominatimGeocoder {
            public function lookup(string $name, string $city, ?string $country = null): array
            {
                return [
                    'name' => $name,
                    'street' => 'Rudnayovo namestie 1',
                    'postcode' => '811 01',
                    'city' => $city,
                    'country' => $country,
                    'latitude' => 48.1485965,
                    'longitude' => 17.1077477,
                ];
            }
        };

        $enricher = new class extends WikipediaPlaceEnricher {
            public function enrich(string $name, string $city, ?string $country = null): array
            {
                return [
                    'official_name' => 'Dóm svätého Martina (Bratislava)',
                    'object_description' => 'Dóm svätého Martina je gotická katedrála v Bratislave. Patrí medzi významné historické stavby mesta. Nachádza sa blízko Bratislavského hradu. V minulosti slúžil ako korunovačný chrám. Dnes je dôležitou kultúrnou pamiatkou.',
                    'image_url' => 'https://upload.wikimedia.org/example.jpg',
                    'image_urls' => [
                        'https://upload.wikimedia.org/example.jpg',
                        'https://upload.wikimedia.org/example-2.jpg',
                        'https://upload.wikimedia.org/example.jpg',
                    ],
                    'website' => 'https://dom.fara.sk',
                    'email' => 'info@dom.fara.sk',
                    'phone' => '+421123456789',
                    'reference_url' => 'https://sk.wikipedia.org/wiki/D%C3%B3m_sv%C3%A4t%C3%A9ho_Martina_(Bratislava)',
                    'enrichment_source' => 'wikipedia',
                ];
            }
        };

        $detector = new Detector(
            chatGPT: $chatGpt,
            nominatimGeocoder: $geocoder,
            municipalityResolver: new MunicipalityResolver(),
            wikipediaPlaceEnricher: $enricher,
        );

        $result = $detector->detectVenueDetails('Katedrala sv. Martina', 'Bratislava', 'Slovakia');

        $this->assertTrue($result['success']);
        $this->assertSame('Dóm svätého Martina (Bratislava)', $result['venue_payload']['name']);
        $this->assertSame('Rudnayovo namestie 1', $result['venue_payload']['street']);
        $this->assertSame('811 01', $result['venue_payload']['postcode']);
        $this->assertSame(48.1485965, $result['venue_payload']['latitude']);
        $this->assertSame(17.1077477, $result['venue_payload']['longitude']);
        $this->assertSame('wikipedia', $result['venue_payload']['enrichment_source']);
        $this->assertSame('https://upload.wikimedia.org/example.jpg', $result['venue_payload']['image_url']);
        $this->assertSame([
            'https://upload.wikimedia.org/example-2.jpg',
        ], $result['venue_payload']['image_urls']);
        $this->assertSame($municipalityId, $result['venue_payload']['village_id']);
        $this->assertSame([
            'id' => $municipalityId,
            'fullname' => 'Bratislava',
            'shortname' => 'Bratislava',
            'zip' => '81101',
        ], $result['venue_payload']['matched_municipality']);
        $this->assertSame([
            'confidence' => 'high',
            'match_source' => 'city_and_postcode',
        ], $result['venue_payload']['municipality_match']);
        $this->assertSame([
        ], $result['missing_required_fields']);
        $this->assertTrue($result['can_store_immediately']);
        $this->assertSame([
            'village_id' => $municipalityId,
            'name' => 'Dóm svätého Martina (Bratislava)',
            'street' => 'Rudnayovo namestie 1',
            'postcode' => '811 01',
            'body' => 'Dóm svätého Martina je gotická katedrála v Bratislave. Patrí medzi významné historické stavby mesta. Nachádza sa blízko Bratislavského hradu. V minulosti slúžil ako korunovačný chrám. Dnes je dôležitou kultúrnou pamiatkou.',
            'website' => 'https://dom.fara.sk',
            'email' => 'info@dom.fara.sk',
            'phone' => '+421123456789',
            'country' => 'Slovakia',
            'latitude' => 48.1485965,
            'longitude' => 17.1077477,
            'capacity' => null,
            'opening_hours' => null,
            'category' => null,
            'status' => 'draft',
        ], $result['venue_store_payload']);
    }

    #[Test]
    public function detect_venue_details_does_not_replace_input_with_different_landmark_from_same_municipality(): void
    {
        $municipalityId = DB::table('municipalities')->insertGetId([
            'fullname' => 'Raslavice',
            'shortname' => 'Raslavice',
            'zip' => '08641',
            'district_id' => 1,
            'region_id' => 1,
            'use' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $chatGpt = new class extends ChatGPT {
            public function extractVenueDetails(array|string $input): array
            {
                return [
                    'name' => 'Kultúrny dom Raslavice',
                    'street' => 'Toplianska 560',
                    'postcode' => '086 41',
                    'city' => 'Raslavice',
                    'country' => 'Slovensko',
                    'latitude' => null,
                    'longitude' => null,
                ];
            }
        };

        $geocoder = new class extends NominatimGeocoder {
            public function lookup(string $name, string $city, ?string $country = null): array
            {
                return [
                    'name' => 'Synagóga v Raslaviciach',
                    'street' => 'Alejová 1',
                    'postcode' => '086 41',
                    'city' => 'Raslavice',
                    'country' => 'Slovensko',
                    'latitude' => 49.123,
                    'longitude' => 21.456,
                ];
            }
        };

        $enricher = new class extends WikipediaPlaceEnricher {
            public function enrich(string $name, string $city, ?string $country = null): array
            {
                return [
                    'official_name' => 'Synagóga v Raslaviciach',
                    'object_description' => 'Popis iného objektu.',
                    'image_url' => 'https://upload.wikimedia.org/synagogue.jpg',
                    'website' => 'https://example.com/synagogue',
                    'reference_url' => 'https://sk.wikipedia.org/wiki/Synag%C3%B3ga_v_Raslaviciach',
                    'enrichment_source' => 'wikipedia',
                ];
            }
        };

        $detector = new Detector(
            chatGPT: $chatGpt,
            nominatimGeocoder: $geocoder,
            municipalityResolver: new MunicipalityResolver(),
            wikipediaPlaceEnricher: $enricher,
        );

        $result = $detector->detectVenueDetails('Kultúrny dom Raslavice', 'Raslavice', 'Slovensko');

        $this->assertTrue($result['success']);
        $this->assertSame('Kultúrny dom Raslavice', $result['venue_payload']['name']);
        $this->assertSame('Toplianska 560', $result['venue_payload']['street']);
        $this->assertSame('086 41', $result['venue_payload']['postcode']);
        $this->assertNull($result['venue_payload']['latitude']);
        $this->assertNull($result['venue_payload']['longitude']);
        $this->assertNull($result['venue_payload']['enrichment_source']);
        $this->assertNull($result['venue_payload']['image_url']);
        $this->assertNotNull($result['venue_payload']['village_id']);
        $this->assertSame('Raslavice', $result['venue_payload']['matched_municipality']['shortname']);
        $this->assertSame('Kultúrny dom Raslavice', $result['venue_store_payload']['name']);
        $this->assertSame('Toplianska 560', $result['venue_store_payload']['street']);
    }
}
