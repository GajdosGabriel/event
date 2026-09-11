<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\ChatGPT;
use App\Services\OpenAI\HtmlBodyFinisher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Formátovanie do HTML ide až nad hotovým textom prepisu — stena textu
 * od copywritera či editora sa rozčlení, štruktúrované HTML sa nechá tak.
 */
class HtmlBodyFinisherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('openai.api_key', 'test-key');
    }

    private function flatText(): string
    {
        return 'Pozývame na púť do Levoče. '.str_repeat('Program začína svätou omšou a pokračuje modlitbami pri Mariánskej hore. ', 8)
            .'Cena 10 eur, kontakt info@farnost.sk.';
    }

    private function fakeFormatter(string $html): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => json_encode(['html' => $html])],
                ]],
            ]),
        ]);
    }

    #[Test]
    public function structured_html_is_only_cleaned_without_calling_the_formatter(): void
    {
        Http::fake();

        $html = '<h3 class="event-section-title">Program</h3><p>'.$this->flatText().'</p><ul class="event-list"><li class="event-list-item">Omša</li></ul>';

        $result = (new HtmlBodyFinisher(new ChatGPT))->finish($html);

        $this->assertStringContainsString('<h3>Program</h3>', $result);
        $this->assertStringNotContainsString('class=', $result);
        Http::assertNothingSent();
    }

    #[Test]
    public function a_wall_of_text_is_formatted_after_the_rewrite(): void
    {
        $text = $this->flatText();
        $this->fakeFormatter('<h3>Program</h3><p>'.$text.'</p><p><strong>Cena:</strong> 10 eur</p><script>x</script>');

        $result = (new HtmlBodyFinisher(new ChatGPT))->finish('<p>'.$text.'</p>');

        $this->assertStringContainsString('<h3>Program</h3>', $result);
        $this->assertStringContainsString('<strong>Cena:</strong>', $result);
        $this->assertStringNotContainsString('<script', $result);
        Http::assertSentCount(1);
    }

    #[Test]
    public function plain_text_without_tags_is_formatted_too(): void
    {
        $text = $this->flatText();
        $this->fakeFormatter('<p>'.$text.'</p><ul><li>Omša</li></ul>');

        $result = (new HtmlBodyFinisher(new ChatGPT))->finish($text);

        $this->assertStringContainsString('<ul>', $result);
    }

    #[Test]
    public function formatter_that_drops_content_is_ignored(): void
    {
        $this->fakeFormatter('<h3>Program</h3><p>Púť do Levoče.</p>');

        $result = (new HtmlBodyFinisher(new ChatGPT))->finish('<p>'.$this->flatText().'</p>');

        $this->assertSame('<p>'.$this->flatText().'</p>', $result);
    }

    #[Test]
    public function formatter_failure_keeps_the_cleaned_text(): void
    {
        Http::fake(['api.openai.com/*' => Http::response('boom', 500)]);

        $result = (new HtmlBodyFinisher(new ChatGPT))->finish('<p>'.$this->flatText().'</p>');

        $this->assertSame('<p>'.$this->flatText().'</p>', $result);
    }

    #[Test]
    public function short_text_in_one_paragraph_is_left_alone(): void
    {
        Http::fake();

        $result = (new HtmlBodyFinisher(new ChatGPT))->finish('<p>Krátka pozvánka na omšu.</p>');

        $this->assertSame('<p>Krátka pozvánka na omšu.</p>', $result);
        Http::assertNothingSent();
    }
}
