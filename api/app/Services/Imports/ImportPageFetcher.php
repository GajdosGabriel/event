<?php

namespace App\Services\Imports;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ImportPageFetcher
{
    public function __construct(
        private readonly HtmlCharsetNormalizer $charsetNormalizer = new HtmlCharsetNormalizer,
    ) {}

    public function fetch(string $url): string
    {
        // Source sites drop or stall single requests (vyveska.sk regularly
        // times out on one article while the rest of the listing is fine).
        // Without a retry that one article is logged as an import error and
        // the event is missing until the next scheduled run. 4xx responses are
        // not retried — the next attempt would land the same way.
        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->retry(
                3,
                fn (int $attempt) => $attempt * 1000,
                fn (\Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            )
            ->withHeaders([
                'User-Agent' => (string) config('services.imports.user_agent', config('app.name', 'Event API').' importer'),
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Import request failed for '.$url.' with status '.$response->status());
        }

        return $this->charsetNormalizer->normalize(
            $response->body(),
            $response->header('Content-Type')
        );
    }
}
