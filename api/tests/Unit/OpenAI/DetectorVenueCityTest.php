<?php

namespace Tests\Unit\OpenAI;

use App\Services\Geocoding\NominatimGeocoder;
use App\Services\OpenAI\ChatGPT;
use App\Services\OpenAI\Detector;
use App\Services\Places\WikipediaPlaceEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Obec z plagátu. Model ju vracia zriedka ako samostatný údaj — býva v adresnom
 * riadku alebo v názve podujatia — a bez nej ostane v sprievodcovi prázdne
 * povinné pole „Mesto / obec" a nespustí sa ani detekcia miesta.
 */
class DetectorVenueCityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rebrik suradnic sa po neuspesnej zhode budovy pyta geokodera na adresu
        // a na stred obce -- bez fake-u by test siel na verejny Nominatim.
        Http::fake();
    }

    /** @param array<string, mixed> $venue */
    private function detector(array $venue, ?string $title = null): Detector
    {
        $chatGpt = new class($venue, $title) extends ChatGPT
        {
            /** @param array<string, mixed> $venue */
            public function __construct(private readonly array $venue, private readonly ?string $title)
            {
                parent::__construct();
            }

            public function extractDataFromPoster(string $text, array $imageDataUrls = [], ?\Carbon\Carbon $referenceDate = null): array
            {
                return [
                    'title' => $this->title,
                    'start_at' => null,
                    'end_at' => null,
                    'organizer' => null,
                    'venue' => $this->venue,
                    'email' => null,
                    'phone' => null,
                    'persons' => [],
                ];
            }

            public function extractCopywriter(array|string $input): array
            {
                return ['event_body' => null];
            }

            // Doplnená obec spustí `detectVenueDetails()` — bez týchto atrap by
            // test volal OpenAI, Nominatim aj Wikipédiu naozaj.
            public function extractVenueDetails(array|string $input): array
            {
                return ['name' => null, 'street' => null, 'postcode' => null, 'city' => null, 'country' => null];
            }
        };

        $geocoder = new class extends NominatimGeocoder
        {
            public function lookup(string $name, string $city, ?string $country = null): array
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

        return new Detector(
            chatGPT: $chatGpt,
            nominatimGeocoder: $geocoder,
            wikipediaPlaceEnricher: $enricher,
        );
    }

    #[Test]
    public function the_city_is_taken_from_the_address_line_when_the_model_leaves_it_empty(): void
    {
        $result = $this->detector([
            'name' => 'Chrám Zosnutia presvätej Bohorodičky',
            'street_and_number' => 'Klokočov - Zemplínska Šírava',
            'city' => null,
        ])->detectFromPoster('');

        $this->assertSame('Klokočov', $result['event_payload']['venue']['city']);

        // Adresný riadok bez čísla domu nie je ulica — inak by z neho vznikla
        // adresa „Klokočov - Zemplínska Šírava, Klokočov".
        $this->assertNull($result['event_payload']['venue']['street_and_number']);
    }

    #[Test]
    public function the_city_can_come_from_the_event_title(): void
    {
        $result = $this->detector(
            ['name' => 'Chrám Zosnutia presvätej Bohorodičky', 'street_and_number' => null, 'city' => null],
            'Eparchiálna odpustová slávnosť Klokočov',
        )->detectFromPoster('');

        $this->assertSame('Klokočov', $result['event_payload']['venue']['city']);
    }

    #[Test]
    public function a_real_street_survives_next_to_the_detected_city(): void
    {
        $result = $this->detector([
            'name' => 'Kultúrny dom',
            'street_and_number' => 'Hlavná 12, Nové Zámky',
            'city' => null,
        ])->detectFromPoster('');

        $this->assertSame('Nové Zámky', $result['event_payload']['venue']['city']);
        $this->assertSame('Hlavná 12, Nové Zámky', $result['event_payload']['venue']['street_and_number']);
    }

    #[Test]
    public function a_city_from_the_model_is_never_overwritten(): void
    {
        $result = $this->detector([
            'name' => 'Kostol sv. Michala',
            'street_and_number' => 'Klokočov - Zemplínska Šírava',
            'city' => 'Michalovce',
        ])->detectFromPoster('');

        $this->assertSame('Michalovce', $result['event_payload']['venue']['city']);
        $this->assertSame('Klokočov - Zemplínska Šírava', $result['event_payload']['venue']['street_and_number']);
    }
}
