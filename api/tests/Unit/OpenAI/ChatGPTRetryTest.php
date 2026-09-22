<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\ChatGPT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prechodný výpadok OpenAI (na produkcii „520 error code: 520") nesmie
 * nechať importované podujatie bez prepisu — volanie sa má zopakovať.
 */
class ChatGPTRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('openai.api_key', 'test-key');
        Sleep::fake();
    }

    private function copywriterResponse(): array
    {
        return [
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['content' => json_encode(['event_body' => '<p>Prepis</p>'])],
            ]],
        ];
    }

    #[Test]
    public function server_error_is_retried(): void
    {
        Http::fakeSequence('api.openai.com/*')
            ->push('error code: 520', 520)
            ->push($this->copywriterResponse());

        $result = (new ChatGPT)->extractCopywriter(str_repeat('Popis podujatia. ', 10));

        $this->assertSame('<p>Prepis</p>', $result['event_body']);
        Http::assertSentCount(2);
    }

    #[Test]
    public function client_error_is_not_retried(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['code' => 'invalid_request']], 400)]);

        try {
            (new ChatGPT)->extractCopywriter(str_repeat('Popis podujatia. ', 10));
            $this->fail('ChatGPT should have thrown on OpenAI error.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('OpenAI API error: 400', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    #[Test]
    public function gives_up_after_three_attempts(): void
    {
        Http::fake(['api.openai.com/*' => Http::response('error code: 520', 520)]);

        try {
            (new ChatGPT)->extractCopywriter(str_repeat('Popis podujatia. ', 10));
            $this->fail('ChatGPT should have thrown on OpenAI error.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('OpenAI API error: 520', $e->getMessage());
        }

        Http::assertSentCount(3);
    }
}
