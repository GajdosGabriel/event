<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\ContentExtractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContentExtractorTest extends TestCase
{
    #[Test]
    public function it_ignores_tkkbs_logo_image_when_extracting_text_from_stredblok(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="sk">
<body>
    <center>
        <table>
            <tr>
                <td class="stredblok">
                    <img src="image/tkkbs/tkkbs_logo.gif" border="0" alt="TK KBS">
                    <p>Bratislava 9. apríla 2026 12:00 (TK KBS) Toto je hlavný text článku.</p>
                    <p>Druhý odsek ostáva zachovaný.</p>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>
HTML;

        $extractor = new ContentExtractor;

        $result = $extractor->extract($html, 'https://www.tkkbs.sk/view.php?cisloclanku=20260409026');

        $this->assertSame(
            'Bratislava 9. apríla 2026 12:00 (TK KBS) Toto je hlavný text článku. Druhý odsek ostáva zachovaný.',
            $result['text']
        );
        $this->assertSame([], $result['attachments']);
    }

    #[Test]
    public function it_drops_the_tkkbs_publication_header_with_the_rubric(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="sk">
<body>
    <center>
        <table>
            <tr>
                <td class="stredblok">
                    <span class="clanadpis">Vzdelávací kurz</span><br>
                    <span class="malemodre">P:3, 06. 07. 2026 08:53, DOM</span><br><br>
                    <span class="clatext">Bratislava 7. júla (TK KBS) Kurz sa koná v Bratislave.</span>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>
HTML;

        $result = (new ContentExtractor)->extract($html, 'https://www.tkkbs.sk/view.php?cisloclanku=20260707001');

        $this->assertSame('Vzdelávací kurz Bratislava 7. júla (TK KBS) Kurz sa koná v Bratislave.', $result['text']);
        $this->assertStringNotContainsString('DOM', $result['text']);
        $this->assertStringNotContainsString('08:53', $result['text']);
    }
}
