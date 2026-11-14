<?php

namespace Tests\Unit\Venues;

use App\Services\Geocoding\MunicipalityResolver;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\OpenAI\ChatGPT;
use App\Services\OpenAI\Detector;
use App\Services\Places\WikipediaPlaceEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Neúspech Nominatimu (429) sa necachuje. Detector kedysi volal lookup() aj
 * lookupDetailed(), takže práve keď geokodér odmietal, dostal každý dotaz dvakrát.
 */
class DetectorNominatimOnceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function rejected_geocoder_is_asked_one_series_of_name_queries(): void
    {
        Cache::flush();
        config([
            'services.nominatim.base_url' => 'https://nominatim.example',
            'services.nominatim.min_interval' => 0,
        ]);
        Http::fake(['https://nominatim.example/*' => Http::response([], 429)]);

        $chatGpt = new class extends ChatGPT
        {
            public function extractVenueDetails(array|string $input): array
            {
                return [];
            }
        };

        $enricher = new class extends WikipediaPlaceEnricher
        {
            public function enrich(string $name, string $city, ?string $country = null): array
            {
                return [];
            }
        };

        $detector = new Detector(
            chatGPT: $chatGpt,
            nominatimGeocoder: new NominatimGeocoder,
            municipalityResolver: new MunicipalityResolver,
            wikipediaPlaceEnricher: $enricher,
        );

        $result = $detector->detectVenueDetails('Kultúrny dom Raslavice', 'Raslavice', 'Slovensko');

        $queries = $result['debug']['geocoder']['queries'] ?? [];
        $nameRequests = Http::recorded(fn (Request $request): bool => isset($request->data()['q']));

        $this->assertNotEmpty($queries);
        $this->assertSame('geocoder_unavailable', $result['debug']['geocoder']['reason']);
        $this->assertCount(count($queries), $nameRequests);
    }
}
