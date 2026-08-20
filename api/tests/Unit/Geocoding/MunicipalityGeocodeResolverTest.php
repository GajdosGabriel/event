<?php

namespace Tests\Unit\Geocoding;

use App\Models\Municipality;
use App\Services\Geocoding\MunicipalityGeocodeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Číselník obcí zanáša migrácia, takže tu ide o skutočné dáta — presne tie,
 * na ktorých dohľadanie obce beží aj na produkcii.
 */
class MunicipalityGeocodeResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('services.nominatim.base_url', 'https://nominatim.example');
    }

    private function fullnameOf(?array $result): ?string
    {
        if ($result === null) {
            return null;
        }

        return Municipality::find($result['village_id'])?->fullname;
    }

    #[Test]
    public function a_city_from_the_catalog_never_touches_the_network(): void
    {
        Http::fake();

        $result = (new MunicipalityGeocodeResolver())->resolve('Poprad', 'Dom kultúry');

        $this->assertSame('Poprad', $this->fullnameOf($result));
        Http::assertNothingSent();
    }

    #[Test]
    public function the_venue_name_is_read_as_the_municipality_when_no_city_is_known(): void
    {
        // Pútnické miesto býva pomenované rovno obcou („púť do Gaboltova")
        // a samostatné mesto v článku nie je.
        Http::fake();

        $result = (new MunicipalityGeocodeResolver())->resolve(null, 'Gaboltov');

        $this->assertSame('Gaboltov', $this->fullnameOf($result));
        Http::assertNothingSent();
    }

    #[Test]
    public function hyphen_spacing_does_not_break_the_catalog_match(): void
    {
        // Číselník má „Šaštín - Stráže", zdroje píšu „Šaštín-Stráže".
        Http::fake();

        $result = (new MunicipalityGeocodeResolver())->resolve('Šaštín-Stráže', 'Bazilika Sedembolestnej Panny Márie');

        $this->assertSame('Šaštín - Stráže', $this->fullnameOf($result));
        Http::assertNothingSent();
    }

    #[Test]
    public function slovak_locative_forms_are_de_inflected_without_the_network(): void
    {
        // Verejný Nominatim má limit ~1 dotaz/s a pri dávkovom importe vracia
        // 429. Bežné mesto sa preto naň nesmie spoliehať — inak by podujatia
        // pri throttlingu ticho končili v zbernom „Celé Slovensko".
        Http::fake();

        $resolver = new MunicipalityGeocodeResolver();

        $expected = [
            'Bratislave' => 'Bratislava',
            'Košiciach'  => 'Košice',
            'Prešove'    => 'Prešov',
            'Nitre'      => 'Nitra',
            'Žiline'     => 'Žilina',
            'Zvolene'    => 'Zvolen',
            'Klokočova'  => 'Klokočov',
            'Levoči'     => 'Levoča',
        ];

        foreach ($expected as $inflected => $municipality) {
            $this->assertSame(
                $municipality,
                $this->fullnameOf($resolver->resolve($inflected, null)),
                "„{$inflected}" . '" sa malo odvodiť na ' . $municipality,
            );
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function a_building_is_never_de_inflected_into_a_municipality(): void
    {
        // Bez poistky by kmeň „kaplnk" trafil obec Kaplna a „kostol" Kostolec.
        Http::fake();

        $resolver = new MunicipalityGeocodeResolver();

        foreach (['Kostol', 'Kostole', 'Kaplnka', 'Kaplnke', 'Dome kultúry', 'Sále', 'Aule', 'Bazilike'] as $building) {
            $this->assertNull(
                $resolver->resolve($building, null),
                "„{$building}" . '" nie je obec.',
            );
        }
    }

    #[Test]
    public function municipalities_actually_named_after_a_church_still_resolve(): void
    {
        // Poistka vyššie beží až po presnej zhode v číselníku, takže skutočné
        // obce s takým menom o nič neprídu.
        Http::fake();

        $resolver = new MunicipalityGeocodeResolver();

        $this->assertSame('Kostolné', $this->fullnameOf($resolver->resolve('Kostolné', null)));
        $this->assertSame('Kaplna', $this->fullnameOf($resolver->resolve('Kaplna', null)));
        Http::assertNothingSent();
    }

    #[Test]
    public function a_region_name_in_the_city_slot_resolves_to_nothing(): void
    {
        // AI občas vloží do mesta kraj. Odvodenie z kmeňa to nesmie premeniť
        // na náhodnú obec.
        Http::fake();

        $this->assertNull((new MunicipalityGeocodeResolver())->resolve('Banskobystrický', null));
        $this->assertNull((new MunicipalityGeocodeResolver())->resolve('Slovensko', null));
    }

    #[Test]
    public function a_pilgrimage_site_that_is_not_a_municipality_is_geocoded(): void
    {
        // Presne prípad z tkkbs článku o Skalke: „Skalka pri Trenčíne" žiadna
        // obec nie je, OSM ju radí pod Trenčín. Bez tohto kroku podujatie
        // skončilo v zbernom „Celé Slovensko".
        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Skalka pri Trenčíne',
                    'lat' => '48.9064469',
                    'lon' => '18.0751505',
                    'address' => [
                        'locality' => 'Skalka pri Trenčíne',
                        'city' => 'Trenčín',
                        'postcode' => '911 01',
                        'country' => 'Slovensko',
                    ],
                ],
            ], 200),
        ]);

        $result = (new MunicipalityGeocodeResolver())->resolve('Skalka pri Trenčíne', 'lúka pod kláštorom');

        $this->assertSame('Trenčín', $this->fullnameOf($result));
        $this->assertSame('911 01', $result['postcode']);
        $this->assertSame(48.9064469, $result['latitude']);
    }

    #[Test]
    public function a_hit_without_a_municipality_is_discarded(): void
    {
        // Keď dotaz trafí len kraj, adresa nemá city/town/village. Taký
        // výsledok nesmie podujatie zaradiť nikam.
        Http::fake([
            'https://nominatim.example/search*' => Http::response([
                [
                    'name' => 'Banskobystrický',
                    'lat' => '48.6',
                    'lon' => '19.2',
                    'address' => [
                        'region' => 'Banskobystrický kraj',
                        'country' => 'Slovensko',
                    ],
                ],
            ], 200),
        ]);

        $this->assertNull((new MunicipalityGeocodeResolver())->resolve('Banskobystrický', null));
    }

    #[Test]
    public function a_failing_geocoder_returns_null_instead_of_throwing(): void
    {
        Http::fake([
            'https://nominatim.example/search*' => Http::response('boom', 500),
        ]);

        $this->assertNull((new MunicipalityGeocodeResolver())->resolve('Skalka pri Trenčíne', 'lúka pod kláštorom'));
    }

    #[Test]
    public function empty_input_resolves_to_nothing(): void
    {
        Http::fake();

        $this->assertNull((new MunicipalityGeocodeResolver())->resolve(null, null));
        $this->assertNull((new MunicipalityGeocodeResolver())->resolve('   ', ''));
        Http::assertNothingSent();
    }
    #[Test]
    public function a_country_wide_placeholder_never_becomes_a_municipality(): void
    {
        // „Slovensko“ nie je obec — je to priznanie, že miesto nie je známe,
        // a patrí na zberné miesto. Nominatim ho však vždy niekam trafí:
        // v produkcii tak vzniklo miesto „Slovensko“ pripnuté na obec
        // Celulózka, ktoré používa sedem podujatí.
        Http::fake();

        $resolver = new MunicipalityGeocodeResolver();

        foreach (['Slovensko', 'Celé Slovensko', 'Slovenská republika', 'online'] as $placeholder) {
            $this->assertNull(
                $resolver->resolve($placeholder, null),
                sprintf('„%s“ nie je obec.', $placeholder),
            );
            $this->assertNull(
                $resolver->resolve(null, $placeholder),
                sprintf('„%s“ nie je ani názov miesta.', $placeholder),
            );
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function a_real_municipality_is_not_mistaken_for_a_placeholder(): void
    {
        Http::fake();

        $resolved = (new MunicipalityGeocodeResolver())->resolve('Slovenská Ľupča', null);

        $this->assertSame('Slovenská Ľupča', $this->fullnameOf($resolved));
        Http::assertNothingSent();
    }
}