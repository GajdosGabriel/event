<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\EcavCongregationName;
use App\Services\Imports\ImportedNameMatcher;
use App\Services\Imports\OrganizerName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EcavCongregationNameTest extends TestCase
{
    /** Zápisy z ecav.sk a z databázy (16. 11. 2026) → jednotný tvar. */
    public static function congregations(): array
    {
        return [
            'skratka' => ['CZ ECAV Poltár', 'CZ ECAV Poltár'],
            'rozpísané' => ['Cirkevný zbor ECAV Liptovský Mikuláš', 'CZ ECAV Liptovský Mikuláš'],
            'na Slovensku' => ['Cirkevný zbor ECAV na Slovensku Modra', 'CZ ECAV Modra'],
            'malé písmeno a pád' => ['cirkevný zbor ECAV v Sobotišti', 'CZ ECAV v Sobotišti'],
            'evanjelický a. v.' => ['Evanjelický a. v. cirkevný zbor v Modre', 'CZ ECAV v Modre'],
            'evanj.' => ['Evanj. a. v. cirkevný zbor v Modre-Kráľovej', 'CZ ECAV v Modre-Kráľovej'],
            'evanjelický cirkevný zbor' => ['Evanjelický cirkevný zbor v Modre-Kráľovej', 'CZ ECAV v Modre-Kráľovej'],
            'evanjelický zbor' => ['Evanjelický zbor Kobeliarovo', 'CZ ECAV Kobeliarovo'],
            'rozpísaná cirkev' => ['Cirkevný zbor Evanjelickej cirkvi a. v. na Slovensku Betliar', 'CZ ECAV Betliar'],
            'úradný tvar s čiarkou' => ['ECAV na Slovensku, Cirkevný zbor Betliar', 'CZ ECAV Betliar'],
            'nesúmerná pomlčka' => ['CZ ECAV Košice- Terasa', 'CZ ECAV Košice-Terasa'],
            'súmerná pomlčka ostáva' => ['CZ ECAV Svätý Kríž - Lazisko', 'CZ ECAV Svätý Kríž - Lazisko'],
        ];
    }

    /** Nie je to (jeden) zbor ECAV — názov ostáva. */
    public static function notCongregations(): array
    {
        return [
            'viac zborov' => ['Cirkevné zbory ECAV Kežmarok, Levoča a Sabinov'],
            'bez ECAV' => ['Cirkevný zbor v Ružomberku'],
            'celá cirkev' => ['Evanjelická cirkev a. v. na Slovensku'],
            'bez obce' => ['CZ ECAV na Slovensku'],
            'spevácky zbor' => ['Evanjelický spevácky zbor Tranoscius'],
            'organizácia ECAV' => ['EMC ECAV'],
            'holé ECAV s obcou' => ['ECAV Nitra'],
        ];
    }

    #[Test]
    #[DataProvider('congregations')]
    public function it_unifies_the_congregation_name(string $input, string $expected): void
    {
        $this->assertSame($expected, EcavCongregationName::normalize($input));
    }

    #[Test]
    #[DataProvider('notCongregations')]
    public function it_leaves_other_names_alone(string $input): void
    {
        $this->assertSame($input, EcavCongregationName::normalize($input));
    }

    #[Test]
    public function the_imported_organizer_name_is_unified(): void
    {
        $this->assertSame('CZ ECAV Betliar', OrganizerName::sanitize('ECAV na Slovensku, Cirkevný zbor Betliar'));
        $this->assertSame('CZ ECAV Bardejov', OrganizerName::sanitize('Cirkevný zbor ECAV Bardejov vás srdečne pozýva'));
    }

    #[Test]
    public function variants_of_one_congregation_share_the_canal_key(): void
    {
        $match = fn (string $a, string $b) => ImportedNameMatcher::canalKeysMatch(ImportedNameMatcher::canalKey($a), ImportedNameMatcher::canalKey($b));

        $this->assertTrue($match('CZ ECAV Poltár', 'Cirkevný zbor ECAV na Slovensku Poltár'));
        $this->assertTrue($match('CZ ECAV Modra', 'Evanjelický a. v. cirkevný zbor Modra'));
        $this->assertTrue($match('CZ ECAV Sobotište', 'cirkevný zbor ECAV v Sobotišti'));

        $this->assertFalse($match('CZ ECAV Modra', 'CZ ECAV Modra-Kráľová'));
        $this->assertFalse($match('CZ ECAV Kežmarok', 'Cirkevné zbory ECAV Kežmarok, Levoča a Sabinov'));
        $this->assertFalse($match('CZ ECAV Levoča', 'CZ ECAV Lučenec'));
    }
}
