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

        $extractor = new ContentExtractor();

        $result = $extractor->extract($html, 'https://www.tkkbs.sk/view.php?cisloclanku=20260409026');

        $this->assertSame(
            'Bratislava 9. apríla 2026 12:00 (TK KBS) Toto je hlavný text článku. Druhý odsek ostáva zachovaný.',
            $result['text']
        );
        $this->assertSame([], $result['attachments']);
    }
}
