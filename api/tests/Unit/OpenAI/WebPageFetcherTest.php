<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\WebPageFetcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebPageFetcherTest extends TestCase
{
    #[Test]
    public function only_http_and_https_are_fetched(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'fetch');
        file_put_contents($file, '<html>tajné</html>');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/cURL Error/');

            (new WebPageFetcher)->fetch('file:///'.ltrim(str_replace('\\', '/', $file), '/'));
        } finally {
            @unlink($file);
        }
    }
}
