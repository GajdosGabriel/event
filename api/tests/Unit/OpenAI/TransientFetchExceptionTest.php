<?php

namespace Tests\Unit\OpenAI;

use App\Services\OpenAI\TransientFetchException;
use App\Services\OpenAI\WebPageFetchException;
use Tests\TestCase;

class TransientFetchExceptionTest extends TestCase
{
    public function test_server_status_survives_as_web_page_fetch_exception(): void
    {
        $final = (new TransientFetchException('HTTP chyba: 503', 503))->final();

        $this->assertInstanceOf(WebPageFetchException::class, $final);
        $this->assertSame(503, $final->getCode());
    }

    public function test_transport_error_has_no_http_status(): void
    {
        $final = (new TransientFetchException('cURL Error: Error in the HTTP2 framing layer'))->final();

        $this->assertNotInstanceOf(WebPageFetchException::class, $final);
        $this->assertSame(0, $final->getCode());
    }
}
