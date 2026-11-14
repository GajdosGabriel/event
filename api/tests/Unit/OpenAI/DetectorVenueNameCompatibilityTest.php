<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\Detector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Zhoda názvu miesta s výsledkom z Wikipédie či geokódera. Druhové slovo
 * („kostol“, „dom kultúry“) nestačí — inak miesto dostane fotku, kontakty
 * a súradnice inej budovy z tej istej obce.
 */
class DetectorVenueNameCompatibilityTest extends TestCase
{
    private function compatible(string $candidate, string $reference, ?string $city = null): bool
    {
        $method = new \ReflectionMethod(Detector::class, 'areVenueNamesCompatible');

        return $method->invoke(new Detector, $candidate, $reference, $city, 'Slovensko');
    }

    /** @return array<string, array{string, string, ?string}> */
    public static function incompatible(): array
    {
        return [
            'iný kostol' => ['Kostol sv. Martina', 'Kostol sv. Jána Krstiteľa', null],
            'iný kostol naopak' => ['Kostol sv. Jána Krstiteľa', 'Kostol sv. Martina', null],
            'iný Ján' => ['Kostol sv. Jána Nepomuckého', 'Kostol sv. Jána Krstiteľa', null],
            'iný dom kultúry' => ['Dom kultúry Dúbravka', 'Dom kultúry Ružinov', 'Bratislava'],
            'len druhové slová' => ['Kultúrny dom', 'Dom kultúry', null],
        ];
    }

    /** @return array<string, array{string, string, ?string}> */
    public static function compatibleNames(): array
    {
        return [
            'skratka DK' => ['DK Ružinov', 'Dom kultúry Ružinov', null],
            'skratka DK naopak' => ['Dom kultúry Ružinov', 'DK Ružinov', null],
            's mestom' => ['Dom kultúry Ružinov, Bratislava', 'Dom kultúry Ružinov', null],
            's mestom naopak' => ['Dom kultúry Ružinov', 'Dom kultúry Ružinov, Bratislava', null],
            'mesto v parametri' => ['DK Ružinov', 'Dom kultúry Ružinov, Bratislava', 'Bratislava'],
            'plný tvar svätého' => ['Kostol svätého Martina', 'Kostol sv. Martina', null],
        ];
    }

    #[Test]
    #[DataProvider('incompatible')]
    public function different_buildings_sharing_a_generic_word_are_not_compatible(string $candidate, string $reference, ?string $city): void
    {
        $this->assertFalse($this->compatible($candidate, $reference, $city));
    }

    #[Test]
    #[DataProvider('compatibleNames')]
    public function same_building_under_another_form_is_compatible(string $candidate, string $reference, ?string $city): void
    {
        $this->assertTrue($this->compatible($candidate, $reference, $city));
    }
}
