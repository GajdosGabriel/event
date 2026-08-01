<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\ChatGPT;
use App\Services\OpenAI\Detector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Popis podujatia z plagátu, ktorý je len obrázok.
 *
 * Copywriter je čisto textový, takže pri prázdnej textovej vrstve nemal z čoho
 * vychádzať a podujatie vzniklo bez popisu — aj keď mal plagát v grafike celý
 * program. Prepis z vision (`poster_text`) je preň náhradný vstup.
 */
class DetectorPosterDescriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function poster_transcript_feeds_the_copywriter_when_the_document_has_no_text(): void
    {
        $chatGpt = new class extends ChatGPT
        {
            public ?string $copywriterInput = null;

            public function extractDataFromPoster(string $text, array $imageDataUrls = [], ?\Carbon\Carbon $referenceDate = null): array
            {
                return [
                    'title' => 'Eparchiálna odpustová slávnosť',
                    'start_at' => '2026-08-15 16:00:00',
                    'end_at' => '2026-08-16 12:00:00',
                    'organizer' => ['name' => 'Gréckokatolícka eparchia Košice', 'street_and_number' => null, 'city' => 'Košice'],
                    'venue' => null,
                    'email' => null,
                    'phone' => null,
                    'persons' => [],
                    'poster_text' => "Sobota 15.8.2026\n16:00 Malé svätenie vody\n17:00 Veľká večiereň s lítiou",
                ];
            }

            public function extractCopywriter(array|string $input): array
            {
                $this->copywriterInput = is_string($input) ? $input : json_encode($input);

                return ['event_body' => '<p>Rozšírený popis</p>'];
            }
        };

        $result = (new Detector(chatGPT: $chatGpt))->detectFromPoster('', ['data:image/jpeg;base64,x']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Malé svätenie vody', (string) $chatGpt->copywriterInput);
        $this->assertSame('<p>Rozšírený popis</p>', $result['corrected_text']);

        // Prepis ostáva k dispozícii ako záloha, keď copywriter zlyhá…
        $this->assertStringContainsString('Veľká večiereň', (string) $result['poster_text']);

        // …ale do formulára nepatrí, nie je to pole podujatia.
        $this->assertArrayNotHasKey('poster_text', $result['event_payload']);
    }

    #[Test]
    public function a_usable_text_layer_still_wins_over_the_transcript(): void
    {
        $chatGpt = new class extends ChatGPT
        {
            public ?string $copywriterInput = null;

            public function extractDataFromPoster(string $text, array $imageDataUrls = [], ?\Carbon\Carbon $referenceDate = null): array
            {
                return [
                    'title' => 'Letný koncert',
                    'start_at' => null,
                    'end_at' => null,
                    'organizer' => null,
                    'venue' => null,
                    'email' => null,
                    'phone' => null,
                    'persons' => [],
                    'poster_text' => 'Útržok z grafiky',
                ];
            }

            public function extractCopywriter(array|string $input): array
            {
                $this->copywriterInput = is_string($input) ? $input : json_encode($input);

                return ['event_body' => null];
            }
        };

        $text = 'Mesto Skúšobné pozýva na letný koncert 21. augusta 2026 o 18:00 v Kultúrnom dome.';

        (new Detector(chatGPT: $chatGpt))->detectFromPoster($text, []);

        $this->assertSame($text, $chatGpt->copywriterInput);
    }
}
