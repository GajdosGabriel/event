<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\Detector;
use App\Services\OpenAI\WebPageFetcher;
use App\Services\OpenAI\WebPageFetchException;
use Mockery;
use Tests\TestCase;

class DetectorSourceFailureTest extends TestCase
{
    public function test_source_http_status_is_preserved_without_parsing_error_text(): void
    {
        $fetcher = Mockery::mock(WebPageFetcher::class);
        $fetcher->shouldReceive('fetch')->once()->andThrow(new WebPageFetchException('Missing', 404));
        $result = (new Detector(fetcher: $fetcher))->detectFromUrl('https://example.test/missing');
        $this->assertFalse($result['success']);
        $this->assertSame(404, $result['source_http_status']);
    }
}
