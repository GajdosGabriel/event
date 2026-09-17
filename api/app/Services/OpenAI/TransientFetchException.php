<?php

namespace App\Services\OpenAI;

/**
 * Sťahovanie zlyhalo na niečom, čo o chvíľu môže prejsť — prerušené spojenie,
 * timeout, rozbitý HTTP/2 rámec, 5xx. Nesie sa len medzi pokusmi vo
 * `WebPageFetcher`; von ide až `final()`, aby volajúci dostal tú istú výnimku
 * ako pred opakovaním.
 */
class TransientFetchException extends \RuntimeException
{
    public function __construct(string $message, private readonly ?int $httpStatus = null)
    {
        parent::__construct($message, $httpStatus ?? 0);
    }

    public function final(): \RuntimeException
    {
        // Stav servera je údaj, ktorý si Detector ukladá do
        // `meta.ai_detector.source_http_status`. Číslo cURL chyby nie —
        // v tom poli by sa tváril ako HTTP kód.
        return $this->httpStatus !== null
            ? new WebPageFetchException($this->getMessage(), $this->httpStatus)
            : new \RuntimeException($this->getMessage());
    }
}
