<?php

namespace Tests\Unit\Geocoding;

use App\Services\Geocoding\MunicipalityNameFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Číselník obcí zanáša migrácia, takže tu ide o skutočné dáta — presne tie,
 * na ktorých hľadanie beží aj na produkcii.
 */
class MunicipalityNameFinderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_finds_a_municipality_inside_an_address_line(): void
    {
        // Presne to, čo model vrátil z klokočovského plagátu ako „ulicu".
        $this->assertSame(
            'Klokočov',
            (new MunicipalityNameFinder())->find('Klokočov - Zemplínska Šírava'),
        );
    }

    #[Test]
    public function it_finds_a_municipality_inside_an_event_title(): void
    {
        $this->assertSame(
            'Klokočov',
            (new MunicipalityNameFinder())->find('Eparchiálna odpustová slávnosť Klokočov'),
        );
    }

    #[Test]
    public function it_prefers_the_longest_name(): void
    {
        // „Nová Ves" aj „Ves" sú obce — kratšia nesmie vyhrať nad dlhšou,
        // inak by podujatie skončilo v inom okrese.
        $this->assertSame(
            'Spišská Nová Ves',
            (new MunicipalityNameFinder())->find('Kultúrny dom, Spišská Nová Ves'),
        );
    }

    #[Test]
    public function it_ignores_words_that_do_not_start_with_a_capital(): void
    {
        // Veľké písmeno je jediné, čo bráni tomu, aby bežné slovo trafilo
        // rovnomennú obec. Bez neho by „hora", „vieska" či „lipa" v ktorejkoľvek
        // vete plagátu vyrobili miesto konania.
        $this->assertNull((new MunicipalityNameFinder())->find('stretnutie pri kostole v hore'));
    }

    #[Test]
    public function typical_venue_names_and_titles_match_nothing(): void
    {
        $finder = new MunicipalityNameFinder();

        foreach ([
            'Chrám Zosnutia presvätej Bohorodičky',
            'Kostol sv. Michala',
            'Farský kostol Nanebovzatia Panny Márie',
            'Koncert Márie Podhradskej',
            'Slávnostná akadémia',
        ] as $text) {
            $this->assertNull($finder->find($text), $text . ' nemá byť obec.');
        }
    }

    #[Test]
    public function it_returns_null_for_empty_input(): void
    {
        $finder = new MunicipalityNameFinder();

        $this->assertNull($finder->find(null));
        $this->assertNull($finder->find('   '));
    }
}
