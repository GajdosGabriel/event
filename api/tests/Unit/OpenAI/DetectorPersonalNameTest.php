<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\Detector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Organizátor z plagátu: osobné meno nahradí názov miesta, organizácia nie.
 * Druhové slová sa hľadajú bez diakritiky — „Nadácia“ nesmie prejsť ako meno.
 */
class DetectorPersonalNameTest extends TestCase
{
    private function looksLikePersonalName(string $value): bool
    {
        $method = new \ReflectionMethod(Detector::class, 'looksLikePersonalName');

        return $method->invoke(new Detector, $value);
    }

    /** @return array<string, array{string}> */
    public static function organizations(): array
    {
        return [
            'nadácia' => ['Nadácia Pontis'],
            'univerzita' => ['Univerzita Komenského'],
            'gymnázium' => ['Gymnázium Pavla Horova'],
            'divadlo' => ['Divadlo Andreja Bagara'],
            'múzeum' => ['Múzeum Andyho Warhola'],
            'knižnica' => ['Knižnica Juraja Fándlyho'],
            'rád' => ['Rád Svätého Benedikta'],
            'kláštor' => ['Kláštor Kapucínov'],
            'kino' => ['Kino Úsmev'],
            'združenie' => ['Združenie Mladých'],
            'občianske združenie' => ['Pomocná Ruka o. z.'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function persons(): array
    {
        return [
            'Ján Novák' => ['Ján Novák'],
            'dvojité priezvisko' => ['Mária Kováčová-Horná'],
            'Konrád nie je rád' => ['Konrád Radošovský'],
            'Kinová nie je kino' => ['Eva Kinová'],
        ];
    }

    #[Test]
    #[DataProvider('organizations')]
    public function an_organization_is_not_a_personal_name(string $value): void
    {
        $this->assertFalse($this->looksLikePersonalName($value));
    }

    #[Test]
    #[DataProvider('persons')]
    public function a_personal_name_is_recognized(string $value): void
    {
        $this->assertTrue($this->looksLikePersonalName($value));
    }
}
