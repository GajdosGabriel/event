<?php

namespace App\Contracts;

use App\Services\Attributes\ProbeResult;

/**
 * Sonda pre jeden typ hodnoty — vie povedať, či daná hodnota ešte funguje.
 *
 * Toto je miesto, kam sa vešajú budúce overovania (telefón, IČO, profil na
 * sociálnej sieti…). Zvyšok — evidencia, opakovanie, upozornenie majiteľovi —
 * je spoločný a sonda o ňom nemusí vedieť nič.
 *
 * Sonda nesmie mať vedľajšie účinky: nič nezapisuje, nikomu nepíše, len sa
 * pozrie a povie výsledok.
 */
interface AttributeProbe
{
    /** Názov atribútu na modeli, ktorý táto sonda overuje (napr. 'website'). */
    public function attribute(): string;

    public function probe(string $value): ProbeResult;
}
