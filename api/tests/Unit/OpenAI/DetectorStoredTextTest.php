<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\ChatGPT;
use App\Services\OpenAI\Detector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Náhrada za zmazaný zdroj: text podujatia z DB.
 *
 * Archív hlascirkvi.sk má stovky podujatí, ktorým ostal len názov. Holý
 * reťazec „Púť Medžugorie 2025" vráti AI s `venue: null` — miesto prečíta, až
 * keď vie, že ide o názov podujatia. Copywriter ho však dostať nesmie: z
 * jedného riadku by si popis vymyslel.
 */
class DetectorStoredTextTest extends TestCase
{
    use RefreshDatabase;

    private function chatGpt(): ChatGPT
    {
        return new class extends ChatGPT
        {
            public ?string $extractInput = null;

            public ?string $copywriterInput = null;

            public function extractData(array|string $input, ?\Carbon\Carbon $referenceDate = null): array
            {
                $this->extractInput = is_string($input) ? $input : json_encode($input);

                return ['title' => 'Púť', 'venue' => ['name' => 'Medžugorie', 'city' => null], 'organizer' => null];
            }

            public function extractCopywriter(array|string $input): array
            {
                $this->copywriterInput = is_string($input) ? $input : json_encode($input);

                return ['event_body' => null];
            }
        };
    }

    #[Test]
    public function title_alone_is_analysed_but_never_rewritten_into_a_description(): void
    {
        $chatGpt = $this->chatGpt();

        $result = (new Detector(chatGPT: $chatGpt))->detectFromStoredText('', 'Púť Medžugorie 2025');

        $this->assertTrue($result['success']);
        $this->assertStringStartsWith('Názov podujatia: Púť Medžugorie 2025', (string) $chatGpt->extractInput);
        $this->assertNull($chatGpt->copywriterInput);
        $this->assertNull($result['corrected_text']);
        $this->assertSame('Medžugorie', $result['event_payload']['venue']['name']);
    }

    #[Test]
    public function the_copywriter_gets_the_stored_body_without_the_title(): void
    {
        $chatGpt = $this->chatGpt();
        $html = '<p>Farnosť Detva pozýva na púť do Padovy.</p><p>Odchod o 5:00 od kostola, návrat v piatok večer.</p>';

        (new Detector(chatGPT: $chatGpt))->detectFromStoredText($html, 'Púť Padova');

        $this->assertStringContainsString('Názov podujatia: Púť Padova', (string) $chatGpt->extractInput);
        $this->assertStringContainsString("pozýva na púť do Padovy.\nOdchod", (string) $chatGpt->extractInput);
        $this->assertStringNotContainsString('Názov podujatia', (string) $chatGpt->copywriterInput);
        $this->assertStringStartsWith('Farnosť Detva', (string) $chatGpt->copywriterInput);
    }

    #[Test]
    public function nothing_to_read_is_reported_as_a_failure(): void
    {
        $result = (new Detector(chatGPT: $this->chatGpt()))->detectFromStoredText('<p> </p>', null);

        $this->assertFalse($result['success']);
    }
}
