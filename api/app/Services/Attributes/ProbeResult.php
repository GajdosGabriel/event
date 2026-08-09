<?php

namespace App\Services\Attributes;

/**
 * Výsledok jedného pokusu sondy.
 *
 * `reason` je kľúč do prekladov (`attribute_checks.reasons.*`), nie hláška
 * z knižnice — majiteľ v e-maile potrebuje vetu, ktorej rozumie, nie
 * „cURL error 6". Surová hláška by navyše šla e-mailom von, a v nej býva aj
 * IP a cesty zo servera.
 */
final class ProbeResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $reason = null,
        public readonly ?int $httpStatus = null,
        public readonly bool $skipped = false,
    ) {
    }

    public static function ok(?int $httpStatus = null): self
    {
        return new self(true, null, $httpStatus);
    }

    public static function failed(string $reason, ?int $httpStatus = null): self
    {
        return new self(false, $reason, $httpStatus);
    }

    /**
     * „Nevieme overiť" — nie je to úspech ani zlyhanie.
     *
     * Rozdiel je zásadný pre majiteľa: zlyhanie mu pošle e-mail, že má
     * pokazený web. Keby sa medzi ne počítalo aj to, čo sonda len nezvládne
     * (napr. doména s diakritikou na serveri bez rozšírenia `intl`), rozposielali
     * by sme obvinenia z vlastnej nemohúcnosti. Stav preto zostane, ako bol.
     */
    public static function skipped(string $reason): self
    {
        return new self(false, $reason, null, true);
    }
}
