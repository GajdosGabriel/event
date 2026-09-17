<?php

namespace App\Services\OpenAI;

use App\Services\Imports\HtmlCharsetNormalizer;

class WebPageFetcher
{
    /** Prechodné chyby prenosu — spojenie, TLS, timeout, rozbitý HTTP/2 rámec. */
    private const TRANSIENT_ERRORS = [
        CURLE_COULDNT_CONNECT,
        CURLE_COULDNT_RESOLVE_HOST,
        CURLE_OPERATION_TIMEDOUT,
        CURLE_PARTIAL_FILE,
        CURLE_GOT_NOTHING,
        CURLE_SEND_ERROR,
        CURLE_RECV_ERROR,
        CURLE_SSL_CONNECT_ERROR,
        16,  // CURLE_HTTP2
        92,  // CURLE_HTTP2_STREAM
    ];

    private const ATTEMPTS = 3;

    public function __construct(
        private readonly HtmlCharsetNormalizer $charsetNormalizer = new HtmlCharsetNormalizer,
    ) {}

    public function fetch(string $url): string
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $this->attempt($url);
            } catch (TransientFetchException $e) {
                // Zdroje vypadnú aj na jednu požiadavku (hlascirkvi.sk vracia
                // „Error in the HTTP2 framing layer", vyveska.sk timeout).
                // Bez opakovania si podujatie odnesie neúspešný pokus v
                // `meta.ai_detector` a na prepis popisu čaká ďalšie hodiny.
                if ($attempt >= self::ATTEMPTS) {
                    throw $e->final();
                }

                usleep($attempt * 500_000);
            }
        }
    }

    private function attempt(string $url): string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            // HTTP/2 tu nič nezískava a niektoré zdroje v ňom rozbijú rámec
            // uprostred odpovede — nad HTTP/1.1 tie isté adresy prejdú.
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $errno = curl_errno($ch);
            $error = 'cURL Error: '.curl_error($ch);
            curl_close($ch);

            if (in_array($errno, self::TRANSIENT_ERRORS, true)) {
                throw new TransientFetchException($error);
            }

            throw new \RuntimeException($error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // 5xx a 429 sú stav servera v danú chvíľu, nie odpoveď na našu adresu.
        if (in_array((int) $httpCode, [429, 500, 502, 503, 504], true)) {
            throw new TransientFetchException("HTTP chyba: {$httpCode}", (int) $httpCode);
        }

        if ($httpCode !== 200 || $response === false) {
            throw new WebPageFetchException("HTTP chyba: {$httpCode}", (int) $httpCode);
        }

        // Zdroj nemusí byť v UTF-8 (tkkbs.sk servíruje Windows-1250). Bez
        // prekódovania tu skončí rozbitá diakritika v popise každého
        // importovaného podujatia.
        return $this->charsetNormalizer->normalize(
            (string) $response,
            is_string($contentType) ? $contentType : null,
        );
    }
}
