<?php

namespace App\Services\Imports;

use Illuminate\Support\Facades\Http;

class ImportPageFetcher
{
    public function __construct(
        private readonly HtmlCharsetNormalizer $charsetNormalizer = new HtmlCharsetNormalizer(),
    ) {}

    public function fetch(string $url): string
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => (string) config('services.imports.user_agent', config('app.name', 'Event API') . ' importer'),
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Import request failed for ' . $url . ' with status ' . $response->status());
        }

        return $this->charsetNormalizer->normalize(
            $response->body(),
            $response->header('Content-Type')
        );
    }
}
