<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\ChatGPT;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dlhý zoškrabaný text (celý program púte) sa nesmie prestať prepisovať na
 * HTML len preto, že je dlhý — na produkcii z toho ostal surový zlepenec bez
 * odstavcov. Ide po častiach a nič sa nesmie stratiť.
 */
class ChatGPTCopywriterChunkingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('openai.api_key', 'test-key');
    }

    /** Text, ktorý presiahne strop jedného volania (5 000 znakov). */
    private function longText(int $paragraphs = 12): string
    {
        $parts = [];

        for ($i = 1; $i <= $paragraphs; $i++) {
            $parts[] = "Bod programu {$i}. " . str_repeat("Podrobnosti k bodu {$i} programu pute. ", 20);
        }

        return implode("\n\n", $parts);
    }

    private function fakeCopywriter(): void
    {
        $call = 0;

        Http::fake([
            'api.openai.com/*' => function () use (&$call) {
                $call++;

                return Http::response([
                    'choices' => [[
                        'finish_reason' => 'stop',
                        'message' => ['content' => json_encode([
                            'event_body' => "<h3 class=\"event-section-title\">Cast {$call}</h3><p>Prepis {$call}</p>",
                        ])],
                    ]],
                ]);
            },
        ]);
    }

    #[Test]
    public function long_text_is_rewritten_in_several_calls_instead_of_failing(): void
    {
        $this->fakeCopywriter();

        $text = $this->longText();
        $this->assertGreaterThan(5000, mb_strlen($text));

        $result = (new ChatGPT())->extractCopywriter($text);

        // Predtým tu letela výnimka „Text je na rozsirenie prilis dlhy".
        $this->assertStringContainsString('Prepis 1', $result['event_body']);
        $this->assertStringContainsString('Prepis 2', $result['event_body']);
        Http::assertSentCount(2);
    }

    #[Test]
    public function chunks_stay_under_the_single_call_limit_and_keep_the_whole_text(): void
    {
        $this->fakeCopywriter();

        $text = $this->longText();
        $sent = [];

        (new ChatGPT())->extractCopywriter($text);

        Http::recorded(function (Request $request) use (&$sent) {
            $sent[] = $request->data()['messages'][1]['content'];
        });

        $this->assertNotEmpty($sent);

        foreach ($sent as $content) {
            $this->assertLessThanOrEqual(5000 + 1000, mb_strlen($content), 'Cast presiahla strop jedneho volania.');
        }

        // Žiadny bod programu nesmie z volaní vypadnúť — orezaný vstup by
        // znamenal, že z popisu ticho zmizne koniec programu.
        $all = implode("\n", $sent);

        for ($i = 1; $i <= 12; $i++) {
            $this->assertStringContainsString("Bod programu {$i}.", $all);
        }
    }

    #[Test]
    public function failed_chunk_falls_back_to_paragraphs_instead_of_losing_content(): void
    {
        $call = 0;

        Http::fake([
            'api.openai.com/*' => function () use (&$call) {
                $call++;

                if ($call === 2) {
                    return Http::response('nope', 500);
                }

                return Http::response([
                    'choices' => [[
                        'finish_reason' => 'stop',
                        'message' => ['content' => json_encode([
                            'event_body' => '<p>Prepis</p>',
                        ])],
                    ]],
                ]);
            },
        ]);

        $result = (new ChatGPT())->extractCopywriter($this->longText());

        $this->assertStringContainsString('<p>Prepis</p>', $result['event_body']);
        // Neprepísaná časť ostáva v popise aspoň ako odstavce.
        $this->assertStringContainsString('Bod programu 7.', $result['event_body']);
    }

    #[Test]
    public function total_failure_still_throws_so_the_caller_falls_back_to_raw_text(): void
    {
        Http::fake(['api.openai.com/*' => Http::response('nope', 500)]);

        $this->expectException(\RuntimeException::class);

        (new ChatGPT())->extractCopywriter($this->longText());
    }

    #[Test]
    public function short_text_still_goes_in_a_single_call(): void
    {
        $this->fakeCopywriter();

        (new ChatGPT())->extractCopywriter(str_repeat('Kratky popis podujatia. ', 20));

        Http::assertSentCount(1);
    }
}
