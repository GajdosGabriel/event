<?php

namespace Tests\Unit\Questions;

use App\Services\Questions\FontLibrary;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chýbajúci glyf GD nakreslí ako prázdny rámček — bez chyby, bez varovania.
 * Žiadny iný test to nezachytí, takže po výmene písma v `resources/fonts/`
 * je toto jediná poistka, že sa na snímke neobjaví „Star# tr#nica".
 */
class FontCoverageTest extends TestCase
{
    #[Test]
    public function a_font_is_available_for_rendering(): void
    {
        $this->assertTrue(function_exists('imagettftext'), 'GD nemá podporu FreeType — snímka nemá čím kresliť text.');
        $this->assertTrue(app(FontLibrary::class)->isUsable());
    }

    #[Test]
    public function fonts_cover_slovak_and_czech_diacritics(): void
    {
        $fonts = app(FontLibrary::class);

        foreach (['bold' => $fonts->bold(), 'semibold' => $fonts->semibold(), 'regular' => $fonts->regular()] as $weight => $path) {
            // Porovnáva sa vykreslený tvar, nie šírka: znak z Private Use Area
            // v písme určite nie je, takže sa nakreslí ako náhradný rámček —
            // a čokoľvek, čo vyzerá rovnako, v písme tiež chýba. Samotná šírka
            // by nestačila, tá sa náhodou zhoduje (napr. „Ĺ" má rovnaký
            // rozstup ako rámček, hoci glyf má).
            $missing = $this->renderGlyph($path, "\u{E000}");

            foreach (mb_str_split('áäčďéěíĺľňóôŕšťúýžÁČĎĹĽŇŠŤŽ–…') as $char) {
                $this->assertNotSame(
                    $missing,
                    $this->renderGlyph($path, $char),
                    sprintf('Rez %s (%s) nepozná znak %s.', $weight, $path, $char),
                );
            }
        }
    }

    /** Otisk vykresleného znaku — na porovnanie s náhradným rámčekom. */
    private function renderGlyph(string $font, string $char): string
    {
        $image = imagecreatetruecolor(80, 80);
        imagefilledrectangle($image, 0, 0, 79, 79, imagecolorallocate($image, 255, 255, 255));
        imagettftext($image, 40, 0, 10, 60, imagecolorallocate($image, 0, 0, 0), $font, $char);

        ob_start();
        imagepng($image, null, 0);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return md5($bytes);
    }
}
