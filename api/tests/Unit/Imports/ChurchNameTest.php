<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\ChurchName;
use App\Services\Imports\ImportedNameMatcher;
use App\Services\Imports\OrganizerName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChurchNameTest extends TestCase
{
    public static function names(): array
    {
        return [
            'genitív z vety' => ['Cirkvi bratskej', 'Cirkev bratská'],
            'už úradný' => ['Cirkev bratská', 'Cirkev bratská'],
            'zbor v lokáli' => ['zbor Cirkvi bratskej v Prešove', 'Cirkev bratská v Prešove'],
            'zbor za čiarkou' => ['Cirkev bratská, zbor Prešov', 'Cirkev bratská Prešov'],
            'skratka za slovom zbor' => ['Zbor CB Prešov', 'Cirkev bratská Prešov'],
            'cirkevný zbor' => ['Cirkevný zbor Cirkvi bratskej Košice', 'Cirkev bratská Košice'],
            'dovetok krajiny' => ['Apoštolskej cirkvi na Slovensku', 'Apoštolská cirkev na Slovensku'],
            'úradný dovetok s čiarkou' => ['Cirkev adventistov siedmeho dňa, Slovenské združenie', 'Cirkev adventistov siedmeho dňa'],
            'baptistický zbor' => ['Baptistický zbor Bratislava', 'Bratská jednota baptistov Bratislava'],
            'katolícka bez zboru' => ['Rímskokatolíckej cirkvi', 'Rímskokatolícka cirkev'],
            'bez diakritiky' => ['Cirkvi bratskej v Presove', 'Cirkev bratská v Presove'],
        ];
    }

    #[Test]
    #[DataProvider('names')]
    public function it_unifies_church_names(string $input, string $expected): void
    {
        $this->assertSame($expected, ChurchName::normalize($input));
    }

    /** Čo nie je jedna registrovaná cirkev (prípadne jej zbor), ostáva nedotknuté. */
    #[Test]
    public function other_names_stay_untouched(): void
    {
        foreach ([
            'Cirkev bratská a ECAV',
            'Rímskokatolícka cirkev, farnosť Prešov',
            'Českobratská cirkev evanjelická',
            'Rada Cirkvi bratskej',
            'Bratislavská arcidiecéza',
            'Kresťanský zbor Prešov',
            'CB Prešov',
        ] as $name) {
            $this->assertSame($name, ChurchName::normalize($name), $name);
        }
    }

    #[Test]
    public function inflected_variants_share_one_canal_key(): void
    {
        $this->assertTrue(ImportedNameMatcher::canalKeysMatch(
            ImportedNameMatcher::canalKey('Cirkvi bratskej'),
            ImportedNameMatcher::canalKey('Cirkev bratská'),
        ));
        $this->assertTrue(ImportedNameMatcher::canalKeysMatch(
            ImportedNameMatcher::canalKey('zbor Cirkvi bratskej v Prešove'),
            ImportedNameMatcher::canalKey('Cirkev bratská Prešov'),
        ));
        // Zbor nie je celá cirkev.
        $this->assertFalse(ImportedNameMatcher::canalKeysMatch(
            ImportedNameMatcher::canalKey('Cirkev bratská Prešov'),
            ImportedNameMatcher::canalKey('Cirkev bratská'),
        ));
    }

    #[Test]
    public function organizer_sanitizing_keeps_the_congregation_whole(): void
    {
        // Čiarka v „…, zbor Prešov" nie je zoznam spoluorganizátorov.
        $this->assertSame('Cirkev bratská Prešov', OrganizerName::sanitize('Cirkev bratská, zbor Prešov'));
        $this->assertSame('Cirkev bratská', OrganizerName::sanitize('Cirkvi bratskej vás srdečne pozýva'));
    }

    #[Test]
    public function it_finds_the_church_in_a_venue_name(): void
    {
        $this->assertSame('Cirkev bratská', ChurchName::inVenueName('Modlitebňa Cirkvi bratskej')['short'] ?? null);
        $this->assertNull(ChurchName::inVenueName('Kostol sv. Mikuláša'));
        // Farnosti sa volajú po patrocíniu — z katolíckeho kostola zbor neodvodíme.
        $this->assertNull(ChurchName::inVenueName('Kostol Rímskokatolíckej cirkvi'));
    }
}
