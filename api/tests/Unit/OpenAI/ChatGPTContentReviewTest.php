<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\ChatGPT;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChatGPTContentReviewTest extends TestCase
{
    #[DataProvider('summaries')]
    public function test_summary_is_preserved_or_derived_from_the_verdict(string $summary, array $issues, string $expected): void
    {
        $this->fakeReview(['score' => 90, 'summary' => $summary, 'issues' => $issues]);

        $result = (new ChatGPT())->extractContentReview('canal', 'Organizátor', 'Popis organizátora.');

        $this->assertSame($expected, $result['summary']);
        $this->assertSame($issues, $result['issues']);
        $this->assertSame(90, $result['score']);
        Http::assertSentCount(1);
    }

    public static function summaries(): array
    {
        $issues = [['severity' => 'warning', 'mode' => 'grammar', 'message' => 'Doplňte čiarku.', 'quote' => 'a to']];

        return [
            'empty clean review' => ['', [], 'Text je bez výhrad.'],
            'blank clean review' => [" \n\t", [], 'Text je bez výhrad.'],
            'empty with issues' => ['', $issues, 'Doplňte čiarku.'],
            'existing summary' => ['  Opravte interpunkciu.  ', $issues, 'Opravte interpunkciu.'],
        ];
    }

    #[DataProvider('invalidSummaries')]
    public function test_missing_or_invalid_summary_still_fails(array $summary): void
    {
        $this->fakeReview(['score' => 90, 'issues' => [], ...$summary]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('summary');

        (new ChatGPT())->extractContentReview('canal', 'Organizátor', 'Popis organizátora.');
    }

    public static function invalidSummaries(): array
    {
        return [[[]], [['summary' => null]], [['summary' => []]], [['summary' => 123]]];
    }

    private function fakeReview(array $review): void
    {
        config()->set('openai.api_key', 'test-key');
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['content' => json_encode($review)],
            ]],
        ])]);
    }
}
