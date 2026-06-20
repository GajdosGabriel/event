<?php

namespace App\Services\OpenAI;

class WebPageFetcher
{
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
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            throw new \RuntimeException("HTTP chyba: {$httpCode}");
        }

        return $response;
    }
}
