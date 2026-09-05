<?php

namespace Tests\Unit\Support;

use App\Support\Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jednotné zaobchádzanie s adresou zadanou človekom (App\Support\Url).
 *
 * Tvrdenia sú tu naschvál konkrétne: normalizáciou prechádza všetko, čo sa
 * do databázy uloží ako web, takže zmena správania sa musí prejaviť pádom
 * testu, nie tichým prepísaním odkazov organizátorov.
 */
class UrlTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string|null}> */
    public static function addresses(): array
    {
        return [
            'doplní chýbajúcu schému' => ['divadlo.sk', 'https://divadlo.sk'],
            'zjednotí veľkosť písmen v doméne' => ['WWW.Divadlo.SK', 'https://www.divadlo.sk'],
            'oreže biele znaky' => ['  divadlo.sk  ', 'https://divadlo.sk'],
            'zachová cestu' => ['divadlo.sk/program', 'https://divadlo.sk/program'],
            'zachová query' => ['divadlo.sk/p?rok=2026', 'https://divadlo.sk/p?rok=2026'],
            'zahodí kotvu' => ['divadlo.sk/p#dnes', 'https://divadlo.sk/p'],
            'zahodí koncové lomítko' => ['divadlo.sk/program/', 'https://divadlo.sk/program'],
            'zahodí prednastavený port' => ['https://divadlo.sk:443/x', 'https://divadlo.sk/x'],
            'ponechá neštandardný port' => ['https://divadlo.sk:8080/x', 'https://divadlo.sk:8080/x'],
            'zahodí prihlasovacie údaje' => ['http://user:heslo@divadlo.sk/x', 'http://divadlo.sk/x'],
            'ponechá http' => ['http://divadlo.sk', 'http://divadlo.sk'],
            'zvládne doménu s diakritikou' => ['kúpele.sk', 'https://kúpele.sk'],
            'zvládne punycode' => ['xn--kpele-7ua.sk', 'https://xn--kpele-7ua.sk'],
            'odmietne prázdnu hodnotu' => ['   ', null],
            'odmietne host bez bodky' => ['localhost', null],
            'odmietne IP adresu' => ['127.0.0.1', null],
            'odmietne cudziu schému' => ['javascript:alert(1)', null],
            'odmietne pomlčku na kraji' => ['-divadlo.sk', null],
            'odmietne holý text' => ['toto nie je adresa', null],
        ];
    }

    #[Test]
    #[DataProvider('addresses')]
    public function it_normalizes_addresses_the_way_people_type_them(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Url::normalize($input));
    }

    #[Test]
    public function normalizing_is_stable(): void
    {
        // Uloženej hodnote sa cast pri každom ďalšom uložení dotkne znova.
        // Keby normalizácia nebola idempotentná, adresa by sa pri každom
        // uložení formulára menila a overovanie by ju stále považovalo za novú.
        $once = Url::normalize('WWW.Divadlo.SK/program/');

        $this->assertSame($once, Url::normalize((string) $once));
    }

    /** @return array<string, array{0: string, 1: string|null}> */
    public static function redirectTargets(): array
    {
        return [
            // Toto je celý dôvod, prečo redirectTarget() existuje: normalize()
            // by lomku odrezal, server by presmeroval späť a sonda by sa
            // zacyklila na funkčnom webe.
            'ponechá koncové lomítko' => ['https://divadlo.sk/program/', 'https://divadlo.sk/program/'],
            'ponechá cestu bez lomítka' => ['https://divadlo.sk/program', 'https://divadlo.sk/program'],
            'ponechá koreň' => ['https://divadlo.sk/', 'https://divadlo.sk/'],
            'ponechá query' => ['https://divadlo.sk/p/?rok=2026', 'https://divadlo.sk/p/?rok=2026'],
            'zjednotí veľkosť písmen v doméne' => ['https://WWW.Divadlo.SK/x/', 'https://www.divadlo.sk/x/'],
            'zahodí prihlasovacie údaje' => ['https://user:heslo@divadlo.sk/x/', 'https://divadlo.sk/x/'],
            'zahodí prednastavený port' => ['https://divadlo.sk:443/x/', 'https://divadlo.sk/x/'],
            'ponechá neštandardný port' => ['https://divadlo.sk:8080/x/', 'https://divadlo.sk:8080/x/'],
            'odmietne cudziu schému' => ['javascript:alert(1)', null],
            'odmietne adresu bez schémy' => ['divadlo.sk/x/', null],
            'odmietne IP adresu' => ['http://127.0.0.1/x', null],
            'odmietne host bez bodky' => ['http://localhost/x', null],
            'odmietne prázdnu hodnotu' => ['   ', null],
        ];
    }

    #[Test]
    #[DataProvider('redirectTargets')]
    public function it_keeps_the_path_of_a_redirect_target_untouched(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Url::redirectTarget($input));
    }

    #[Test]
    public function a_redirect_target_keeps_what_normalizing_would_strip(): void
    {
        // Rozdiel medzi oboma metódami je zámerný a je to práve tá lomka.
        // Keby sa zjednotili, vráti sa zacyklenie sondy na kanonizujúcich
        // weboch — a s ním falošné hlásenia o pokazenom webe.
        $slashed = 'https://divadlo.sk/program/';

        $this->assertSame('https://divadlo.sk/program', Url::normalize($slashed));
        $this->assertSame($slashed, Url::redirectTarget($slashed));
    }

    #[Test]
    public function local_and_private_addresses_are_never_safe_to_probe(): void
    {
        // Sonda chodí na adresu, ktorú zadal cudzí človek — bez tejto poistky
        // by z nej bola cesta do vnútornej siete servera.
        $this->assertFalse(Url::isSafeToProbe('http://localhost/admin'));
        $this->assertFalse(Url::isSafeToProbe('http://127.0.0.1/'));
        $this->assertFalse(Url::isSafeToProbe('http://192.168.1.1/'));
        $this->assertFalse(Url::isSafeToProbe('http://[::1]/'));
    }

    #[Test]
    public function private_addresses_are_recognised(): void
    {
        $this->assertFalse(Url::isPublicAddress('10.0.0.1'));
        $this->assertFalse(Url::isPublicAddress('192.168.0.10'));
        $this->assertFalse(Url::isPublicAddress('127.0.0.1'));
        $this->assertTrue(Url::isPublicAddress('93.184.216.34'));
    }
}
