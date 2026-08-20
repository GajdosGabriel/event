<?php

namespace App\Services\Imports;

use Illuminate\Support\Str;

/**
 * Prevedie stiahnuté HTML na UTF-8.
 *
 * Slovenské zdroje (tkkbs.sk a spol.) stále servírujú Windows-1250 a charset
 * hlásia len v <meta>, nie v Content-Type hlavičke. Bez prekódovania sa bajty
 * dostanú do DOMDocument, libxml ich po zlyhaní UTF-8 parsovania prečíta ako
 * ISO-8859-1 a v texte ostane „nede¾u" namiesto „nedeľu" — pričom ť/ž/š
 * (0x9D/0x9E/0x9A) v Latin-1 neexistujú a zmiznú úplne. Preto sa normalizuje
 * hneď po stiahnutí, kým sú bajty ešte celé.
 */
class HtmlCharsetNormalizer
{
    public function normalize(string $body, ?string $contentType = null): string
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

        // Prepísaná <meta charset> musí sedieť s bajtami, inak si ju DOMDocument
        // prečíta a text prekóduje druhýkrát.
        return $this->rewriteMetaCharset($converted);
    }

    private function rewriteMetaCharset(string $html): string
    {
        $rewritten = preg_replace(
            '/(<meta[^>]*?charset\s*=\s*)(["\']?)[^"\'\s>;]+/i',
            '$1$2utf-8',
            $html
        );

        return is_string($rewritten) ? $rewritten : $html;
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

    /**
     * Vzory sú zámerne bez /u: vstup je tu ešte v pôvodnom kódovaní a preg s
     * modifikátorom /u nad nevalidným UTF-8 nevráti zhodu, ale false — deklarácia
     * charsetu by potom ostala neprečítaná.
     */
    private function extractEncodingFromHtml(string $html): ?string
    {
        if (preg_match('/<meta[^>]+charset\s*=\s*["\']?([^"\'\s>;]+)/i', $html, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/<meta[^>]+content=["\'][^"\']*charset=([^"\';\s>]+)/i', $html, $matches)) {
            return trim($matches[1]);
        }

        // Bajty, ktoré v Latin-1 ani ISO-8859-2 nič neznamenajú, ale vo
        // Windows-1250 nesú š/ť/ž — zdroj bez deklarovaného charsetu.
        if (Str::contains($html, ["\x8a", "\x8d", "\x8e", "\x9a", "\x9d", "\x9e"])) {
            return 'Windows-1250';
        }

        return null;
    }
}
