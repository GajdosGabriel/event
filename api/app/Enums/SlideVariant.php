<?php

namespace App\Enums;

/**
 * Formát generovanej snímky s QR kódom.
 *
 * Tlačový A4 zámerne chýba — snímka je určená na plátno a na sociálne siete;
 * na dvere sály sa dá vytlačiť aj `slide`.
 */
enum SlideVariant: string
{
    /** Na projektor a do PowerPointu. */
    case Slide = 'slide';

    /** Na Instagram a Facebook pred akciou. */
    case Square = 'square';

    public function width(): int
    {
        return match ($this) {
            self::Slide => 1920,
            self::Square => 1080,
        };
    }

    public function height(): int
    {
        return match ($this) {
            self::Slide => 1080,
            self::Square => 1080,
        };
    }

    /**
     * Široká snímka kladie text a QR vedľa seba, štvorec pod seba. Je to jediný
     * rozdiel v rozvrhu — všetko ostatné sú zlomky rozmerov.
     */
    public function isTwoColumn(): bool
    {
        return $this === self::Slide;
    }
}
