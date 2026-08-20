<?php

namespace App\Services\OpenAI;

use App\Services\Imports\HtmlCharsetNormalizer;

class WebPageFetcher
{
    public function __construct(
        private readonly HtmlCharsetNormalizer $charsetNormalizer = new HtmlCharsetNormalizer(),
    ) {}

    public function fetch(string $url): string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = 'cURL Error: ' . curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            throw new \RuntimeException("HTTP chyba: {$httpCode}");
        }

        // Zdroj nemusí byť v UTF-8 (tkkbs.sk servíruje Windows-1250). Bez
        // prekódovania tu skončí rozbitá diakritika v `body_ai` každého
        // importovaného podujatia.
        return $this->charsetNormalizer->normalize(
            (string) $response,
            is_string($contentType) ? $contentType : null,
        );
    }
}
