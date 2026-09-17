<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\ImportPageFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Zdroj zlyhá aj na jednej požiadavke (na produkcii „cURL error 28" nad
 * vyveska.sk). Bez opakovania sa článok stratí z importu na celý beh.
 */
class ImportPageFetcherRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

    #[Test]
    public function timed_out_request_is_retried(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            return Http::response('<html><body>Podujatie</body></html>');
        });

        $html = (new ImportPageFetcher)->fetch('https://www.vyveska.sk/clanok/');

        $this->assertStringContainsString('Podujatie', $html);
        $this->assertSame(2, $attempts);
    }

    #[Test]
    public function server_error_is_retried_and_then_reported(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        try {
            (new ImportPageFetcher)->fetch('https://www.vyveska.sk/clanok/');
            $this->fail('Fetcher should have thrown on 503.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('with status 503', $e->getMessage());
        }

        Http::assertSentCount(3);
    }

    #[Test]
    public function missing_page_is_not_retried(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        try {
            (new ImportPageFetcher)->fetch('https://www.vyveska.sk/clanok/');
            $this->fail('Fetcher should have thrown on 404.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('with status 404', $e->getMessage());
        }

        Http::assertSentCount(1);
    }
}
