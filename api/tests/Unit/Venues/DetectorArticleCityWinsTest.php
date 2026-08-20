<?php

namespace Tests\Unit\Venues;

use App\Services\Geocoding\MunicipalityResolver;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\OpenAI\ChatGPT;
use App\Services\OpenAI\Detector;
use App\Services\Places\WikipediaPlaceEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `village_id` sa počítalo z firstString(geokóder.city, AI.city, článok.city),
 * takže obec z geokódera vždy prebila obec z článku. V produkcii tak
 * „Amfiteáter Košice“ dostal obec Námestovo — OSM našiel amfiteáter tam a
 * „Košice“ z článku sa zahodili.
 *
 * Geokóder je autorita nad ulicou a súradnicami budovy, nie nad tým, v ktorej
 * obci sa podujatie koná.
 */
class DetectorArticleCityWinsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_city_from_the_article_decides_the_municipality(): void
    {
        $kosice = $this->municipality('Košice');
        $namestovo = $this->municipality('Námestovo');

        $detector = $this->detectorReturningCity('Námestovo');

        $result = $detector->detectVenueDetails('Amfiteáter Košice', 'Košice', 'Slovensko');

        $this->assertSame(
            $kosice,
            $result['venue_payload']['village_id'],
            'Obec z článku musí rozhodnúť o village_id.',
        );
        $this->assertNotSame($namestovo, $result['venue_payload']['village_id']);
    }

    #[Test]
    public function the_geocoded_city_still_wins_when_the_article_city_is_not_a_municipality(): void
    {
        // Pútnické miesto ani mestská časť nie sú obce. Vtedy je obec
        // z geokódera jediný použiteľný údaj a musí sa zachovať.
        $trencin = $this->municipality('Trenčín');

        $detector = $this->detectorReturningCity('Trenčín');

        $result = $detector->detectVenueDetails('Lúka pod kláštorom', 'Skalka pri Trenčíne', 'Slovensko');

        $this->assertSame($trencin, $result['venue_payload']['village_id']);
    }

    /**
     * Číselník obcí zanáša migrácia, takže tu je celý a netreba doň nič
     * vkladať — vlastný riadok by len vyrobil druhé „Košice“ s vyšším id,
     * ktoré by resolver aj tak nenašiel.
     */
    private function municipality(string $name): int
    {
        $id = DB::table('municipalities')->where('fullname', $name)->value('id');

        $this->assertNotNull($id, sprintf('Obec „%s“ chýba v číselníku.', $name));

        return (int) $id;
    }

    private function detectorReturningCity(string $geocodedCity): Detector
    {
        $chatGpt = new class extends ChatGPT
        {
            public function extractVenueDetails(array|string $input): array
            {
                return [
                    'name' => null, 'street' => null, 'postcode' => null,
                    'city' => null, 'country' => 'Slovensko',
                    'latitude' => null, 'longitude' => null,
                ];
            }
        };

        $geocoder = new class($geocodedCity) extends NominatimGeocoder
        {
            public function __construct(private readonly string $geocodedCity) {}

            public function lookup(string $name, string $city, ?string $country = null): array
            {
                return [
                    'name' => $name,
                    'street' => 'Hlavná 1',
                    'postcode' => null,
                    'city' => $this->geocodedCity,
                    'country' => $country,
                    'latitude' => 49.4079,
                    'longitude' => 19.4801,
                ];
            }
        };

        $enricher = new class extends WikipediaPlaceEnricher
        {
            public function enrich(string $name, string $city, ?string $country = null): array
            {
                return [];
            }
        };

        return new Detector(
            chatGPT: $chatGpt,
            nominatimGeocoder: $geocoder,
            municipalityResolver: new MunicipalityResolver(),
            wikipediaPlaceEnricher: $enricher,
        );
    }
}
