<?php

namespace Tests\Feature\Venues;

use App\Services\OpenAI\Detector;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardVenueDetectTest extends EventSetupTest
{
    #[Test]
    public function detect_returns_payload_from_detector_service(): void
    {
        $municipalityId = (int) DB::table('municipalities')->value('id');

        $this->instance(Detector::class, new class($municipalityId) extends Detector {
            public function __construct(private readonly int $municipalityId = 0)
            {
                parent::__construct();
            }

            public function detectVenueDetails(string $name, string $city, ?string $country = null): array
            {
                return [
                    'success' => true,
                    'message' => 'Miesto analyzovane',
                    'venue_payload' => [
                        'name' => 'Dóm svätého Martina (Bratislava)',
                        'street' => 'Hlavna 1',
                        'postcode' => '811 01',
                        'city' => $city,
                        'country' => $country,
                        'latitude' => 48.1485965,
                        'longitude' => 17.1077477,
                        'object_description' => 'Dóm svätého Martina je gotická katedrála v Bratislave. Patrí medzi významné historické stavby mesta. Nachádza sa v historickom jadre. V minulosti slúžil ako korunovačný chrám. Dnes je dôležitou kultúrnou pamiatkou.',
                        'image_url' => 'https://upload.wikimedia.org/example.jpg',
                        'website' => 'https://example.sk',
                        'reference_url' => 'https://sk.wikipedia.org/wiki/D%C3%B3m_sv%C3%A4t%C3%A9ho_Martina_(Bratislava)',
                        'enrichment_source' => 'wikipedia',
                        'village_id' => $this->municipalityId,
                        'matched_municipality' => [
                            'id' => $this->municipalityId,
                            'fullname' => 'Bratislava',
                            'shortname' => 'Bratislava',
                            'zip' => '81101',
                        ],
                        'municipality_match' => [
                            'confidence' => 'high',
                            'match_source' => 'city_and_postcode',
                        ],
                    ],
                    'venue_store_payload' => [
                        'village_id' => $this->municipalityId,
                        'name' => 'Dóm svätého Martina (Bratislava)',
                        'street' => 'Hlavna 1',
                        'postcode' => '811 01',
                        'body' => 'Dóm svätého Martina je gotická katedrála v Bratislave. Patrí medzi významné historické stavby mesta. Nachádza sa v historickom jadre. V minulosti slúžil ako korunovačný chrám. Dnes je dôležitou kultúrnou pamiatkou.',
                        'website' => 'https://example.sk',
                        'country' => $country,
                        'latitude' => 48.1485965,
                        'longitude' => 17.1077477,
                        'capacity' => null,
                        'opening_hours' => null,
                        'category' => null,
                        'status' => 'draft',
                    ],
                    'missing_required_fields' => [],
                    'can_store_immediately' => true,
                ];
            }
        });

        $response = $this->postJson('/api/dashboard/venues/detect', [
            'name' => 'Katedrala sv. Martina',
            'city' => 'Bratislava',
            'country' => 'Slovakia',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Miesto analyzovane',
            'venue_payload' => [
                'name' => 'Dóm svätého Martina (Bratislava)',
                'street' => 'Hlavna 1',
                'postcode' => '811 01',
                'city' => 'Bratislava',
                'country' => 'Slovakia',
                'latitude' => 48.1485965,
                'longitude' => 17.1077477,
                'object_description' => 'Dóm svätého Martina je gotická katedrála v Bratislave. Patrí medzi významné historické stavby mesta. Nachádza sa v historickom jadre. V minulosti slúžil ako korunovačný chrám. Dnes je dôležitou kultúrnou pamiatkou.',
                'image_url' => 'https://upload.wikimedia.org/example.jpg',
                'website' => 'https://example.sk',
                'reference_url' => 'https://sk.wikipedia.org/wiki/D%C3%B3m_sv%C3%A4t%C3%A9ho_Martina_(Bratislava)',
                'enrichment_source' => 'wikipedia',
                'village_id' => $municipalityId,
                'matched_municipality' => [
                    'id' => $municipalityId,
                    'fullname' => 'Bratislava',
                    'shortname' => 'Bratislava',
                    'zip' => '81101',
                ],
                'municipality_match' => [
                    'confidence' => 'high',
                    'match_source' => 'city_and_postcode',
                ],
            ],
            'venue_store_payload' => [
                'village_id' => $municipalityId,
                'name' => 'Dóm svätého Martina (Bratislava)',
                'street' => 'Hlavna 1',
                'postcode' => '811 01',
                'body' => 'Dóm svätého Martina je gotická katedrála v Bratislave. Patrí medzi významné historické stavby mesta. Nachádza sa v historickom jadre. V minulosti slúžil ako korunovačný chrám. Dnes je dôležitou kultúrnou pamiatkou.',
                'website' => 'https://example.sk',
                'country' => 'Slovakia',
                'latitude' => 48.1485965,
                'longitude' => 17.1077477,
                'capacity' => null,
                'opening_hours' => null,
                'category' => null,
                'status' => 'draft',
            ],
            'missing_required_fields' => [],
            'can_store_immediately' => true,
        ]);
    }

    #[Test]
    public function detect_validates_required_fields(): void
    {
        $response = $this->postJson('/api/dashboard/venues/detect', [
            'name' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'city']);
    }
}
