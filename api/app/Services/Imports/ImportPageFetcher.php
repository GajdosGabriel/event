<?php

namespace App\Services\Imports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportPageFetcher
{
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

        return $this->normalizeEncoding(
            $response->body(),
            $response->header('Content-Type')
        );
    }

    private function normalizeEncoding(string $body, ?string $contentType): string
    {
        $encoding = $this->extractEncodingFromContentType($contentType)
            ?? $this->extractEncodingFromHtml($body);

        if ($encoding === null) {
            return $body;
        }

        $normalizedEncoding = strtolower(trim($encoding, '"\''));

        if (in_array($normalizedEncoding, ['utf-8', 'utf8'], true)) {
            return $body;
        }

        $converted = null;

        if (function_exists('mb_convert_encoding')) {
            try {
                $converted = mb_convert_encoding($body, 'UTF-8', $encoding);
            } catch (\ValueError) {
                $converted = null;
            }
        }

        if (! is_string($converted) || $converted === '') {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $body) ?: null;
        }

        if (! is_string($converted) || $converted === '') {
            return $body;
        }

        return preg_replace(
            '/(<meta[^>]+charset=)(["\']?)[^"\'\s>]+/iu',
            '$1$2utf-8',
            $converted
        ) ?? $converted;
    }

    private function extractEncodingFromContentType(?string $contentType): ?string
    {
        if (! is_string($contentType) || trim($contentType) === '') {
            return null;
        }

        if (preg_match('/charset\s*=\s*([^;\s]+)/i', $contentType, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractEncodingFromHtml(string $html): ?string
    {
        if (preg_match('/<meta[^>]+charset\s*=\s*["\']?([^"\'\s>]+)/iu', $html, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/<meta[^>]+content=["\'][^"\']*charset=([^"\';\s>]+)/iu', $html, $matches)) {
            return trim($matches[1]);
        }

        if (Str::contains($html, ["\x8a", "\x8d", "\x8e", "\x9a", "\x9d", "\x9e"])) {
            return 'Windows-1250';
        }

        return null;
    }
}
