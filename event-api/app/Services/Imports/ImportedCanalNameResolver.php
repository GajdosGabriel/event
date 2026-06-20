<?php

namespace App\Services\Imports;

use App\Services\OpenAI\ChatGPT;
use Illuminate\Support\Str;

class ImportedCanalNameResolver
{
    public function __construct(private readonly ChatGPT $chatGPT = new ChatGPT())
    {
    }

    /**
     * @return array{name: string, detected_name: string|null, source_origin: string}
     */
    public function resolve(string $sourceUrl, string $title, string $text): array
    {
        $title = $this->normalizeEncoding($title);
        $text = $this->normalizeEncoding($text);
        $detectedName = $this->extractByHeuristics($title, $text);

        if ($detectedName === null && (bool) config('services.imports.detect_canal_with_ai', false)) {
            try {
                $detectedName = $this->chatGPT->extractCanalName([
                    'title' => $title,
                    'text' => $text,
                    'source_url' => $sourceUrl,
                ]);
            } catch (\Throwable) {
                $detectedName = null;
            }
        }

        $sourceOrigin = $this->extractOrigin($sourceUrl);

        return [
            'name' => $detectedName ?? $this->hostLabel($sourceUrl),
            'detected_name' => $detectedName,
            'source_origin' => $sourceOrigin,
        ];
    }

    private function extractByHeuristics(string $title, string $text): ?string
    {
        $haystacks = [$title, $text];

        $patterns = [
            '/\b(Cirkevn[ýy]\s+zbor\s+ECAV\s+[^,.\n]{2,120})/iu',
            '/\b(CZ\s+ECAV\s+[^,.\n]{2,120})/iu',
            '/\b(Modlitebn[ée]\s+spoločenstvo\s+ECAV)\b/iu',
            '/\b(Modlitebn\p{L}*\s+spoločenstv\p{L}*\s+ECAV)\b/iu',
            '/\b(MOS\s+ECAV)\b/iu',
            '/\b(VD\s+ECAV)\b/iu',
            '/\b(ZD\s+ECAV)\b/iu',
            '/\b(EMC\s+ECAV)\b/iu',
            '/\b(TK\s+KBS)\b/iu',
            '/\b(Tlačov[aá]\s+kancel[aá]ria\s+KBS)\b/iu',
            '/\b(Konferencia\s+biskupov\s+Slovenska)\b/iu',
            '/\b(Evanjelick[áa]\s+cirkev\s+a\.\s*v\.\s+na\s+Slovensku)\b/iu',
        ];

        foreach ($haystacks as $haystack) {
            foreach ($patterns as $pattern) {
                if (! preg_match($pattern, $haystack, $matches)) {
                    continue;
                }

                $name = $this->sanitizeName($matches[1]);
                if ($name !== null) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function sanitizeName(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $value = trim($value, " \t\n\r\0\x0B,.;:-/");

        if ($value === '') {
            return null;
        }

        return Str::limit($value, 250, '');
    }

    private function hostLabel(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        return $host !== '' ? $host : 'imported-source';
    }

    private function extractOrigin(string $url): string
    {
        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?: 'https');
        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host !== '' ? $scheme . '://' . $host : $url;
    }

    private function normalizeEncoding(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        foreach (['Windows-1250', 'ISO-8859-2', 'Windows-1252'] as $encoding) {
            $converted = null;

            if (function_exists('mb_convert_encoding')) {
                try {
                    $converted = mb_convert_encoding($value, 'UTF-8', $encoding);
                } catch (\ValueError) {
                    $converted = null;
                }
            }

            if (! is_string($converted) || $converted === '') {
                $converted = @iconv($encoding, 'UTF-8//IGNORE', $value) ?: null;
            }

            if (is_string($converted) && $converted !== '' && (! function_exists('mb_check_encoding') || mb_check_encoding($converted, 'UTF-8'))) {
                return $converted;
            }
        }

        return $value;
    }
}
