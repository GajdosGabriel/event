<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\ImportedNameMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CanalKeyTest extends TestCase
{
    /** Dvojice z kontroly produkčnej databázy (23. 10. 2026), ktoré import založil dvakrát. */
    public static function sameOrganizer(): array
    {
        return [
            'právna forma' => ['Musica aeterna', 'Musica aeterna o.z'],
            'OZ vpredu aj vzadu' => ['OZ KREDIV', 'Občianske združenie KREDIV'],
            'n. o. s medzerou' => ['Cor Sancti Martini, n. o', 'Cor Sancti Martini, n.o'],
            'skratka v zátvorke' => ['Ekumenická rada cirkví v SR', 'Ekumenická rada cirkví (ERC) v SR'],
            'v SR / v Slovenskej republike' => ['Ekumenická rada cirkví v SR', 'Ekumenická rada cirkví v Slovenskej republike'],
            'na Slovensku' => ['Rehoľa sv. Augustína', 'Rehoľa sv. Augustína na Slovensku'],
            'sv. / svätého' => ['Spolok svätého Vojtecha', 'Spolok sv. Vojtecha'],
            'pri' => ['Rada pre mládež a univerzity KBS', 'Rada pre mládež a univerzity pri KBS'],
            'rímskokatolícka' => ['Farnosť Kežmarok', 'Rímskokatolícka farnosť Kežmarok'],
            'pád obce' => ['Biskupstvo Nitra', 'Biskupstvo v Nitre'],
            'preklep' => ['Klub kresťanských lekárov a zdravotníkov Bratislava', 'Klub kresťanských lekárov a zdravotníkov Bratislave'],
            'vynechané písmená' => ['Pápežské misijné diela', 'Pápežské misjné diela'],
            'pomlčky' => ['Mesto Bardejov – oddelenie kultúry', 'Mesto Bardejov - oddelenie kultúry'],
        ];
    }

    /** Podobné názvy rôznych subjektov — import ich zlúčiť nesmie. */
    public static function differentOrganizers(): array
    {
        return [
            'iná fakulta' => ['Teologická fakulta KU', 'Teologická fakulta TU'],
            'iný kraj' => ['Trenčiansky samosprávny kraj', 'Trnavský samosprávny kraj'],
            'iná diecéza' => ['Komisia pre mládež Žilinskej diecézy', 'Komisia pre mládež Spišskej diecézy'],
            'iný rok' => ['Organizačný výbor SDM 2023', 'Organizačný výbor SDM 2027'],
            'online / offline' => ['Kolégium ONLINE', 'Kolégium OFFLINE'],
            'iné spoločenstvo' => ['Spoločenstvo Joel', 'Spoločenstvo Jozue'],
            'iný obrad' => ['Slovenská gréckokatolícka farnosť v Prahe', 'Slovenská rímskokatolícka farnosť v Prahe'],
            'všeobecný názov' => ['Katolícka cirkev', 'Katolícka cirkev na Slovensku'],
            'patrocínium bez obce' => ['Rímskokatolícka farnosť sv. Mikuláša', 'Farnosť svätého Mikuláša'],
            'patrocínium s preklepom' => ['Farnosť Kráľovnej rodiny', 'Farnosť Kráľovej rodiny'],
            'iný farský úrad' => ['Rímskokatolícky farský úrad v Bojnej', 'Rímskokatolícky farský úrad v Pozbe'],
        ];
    }

    #[Test]
    #[DataProvider('sameOrganizer')]
    public function variants_of_one_organizer_match(string $a, string $b): void
    {
        $this->assertTrue(ImportedNameMatcher::canalKeysMatch(ImportedNameMatcher::canalKey($a), ImportedNameMatcher::canalKey($b)));
    }

    #[Test]
    #[DataProvider('differentOrganizers')]
    public function similar_names_of_different_organizers_do_not_match(string $a, string $b): void
    {
        $this->assertFalse(ImportedNameMatcher::canalKeysMatch(ImportedNameMatcher::canalKey($a), ImportedNameMatcher::canalKey($b)));
    }

    #[Test]
    public function the_key_drops_only_what_does_not_identify_the_organizer(): void
    {
        $this->assertSame('pre sunavu', ImportedNameMatcher::canalKey('Občianske združenie PRE ŠUŇAVU, o.z.'));
        $this->assertSame('farnost sv jakuba', ImportedNameMatcher::canalKey('Rímskokatolícka farnosť svätého Jakuba'));
        $this->assertSame('', ImportedNameMatcher::canalKey('o. z.'));
    }
}
