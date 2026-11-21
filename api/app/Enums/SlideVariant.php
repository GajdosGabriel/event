<?php

namespace App\Enums;

/**
 * Formát generovanej snímky s QR kódom.
 *
 * Snímka nástenky otázok používa `slide` a `square`. Pozvánka na akciu
 * (panel Propagácia) navyše `story` a `poster` — tie sa tlačia a zdieľajú
 * pred akciou, nie premietajú počas nej.
 */
enum SlideVariant: string
{
    /** Na projektor a do PowerPointu. */
    case Slide = 'slide';

    /** Na Instagram a Facebook pred akciou. */
    case Square = 'square';

    /** Instagram/Facebook/WhatsApp status — na výšku 9:16. */
    case Story = 'story';

    /**
     * Plagát A4 na výšku, 200 DPI. Viac netreba — QR je na plagáte veľký
     * a 300 DPI by v GD znamenalo trojnásobok pamäte aj času bez viditeľného
     * rozdielu na nástenke.
     */
    case Poster = 'poster';

    public function width(): int
    {
        return match ($this) {
            self::Slide => 1920,
            self::Square, self::Story => 1080,
            self::Poster => 1654,
        };
    }

    public function height(): int
    {
        return match ($this) {
            self::Slide, self::Square => 1080,
            self::Story => 1920,
            self::Poster => 2339,
        };
    }

    /**
     * Široká snímka kladie text a QR vedľa seba, ostatné pod seba. Je to
     * jediný rozdiel v rozvrhu — všetko ostatné sú zlomky rozmerov.
     */
    public function isTwoColumn(): bool
    {
        return $this === self::Slide;
    }

    /** Formát na výšku — textu sa vojde viac riadkov a QR môže byť väčší. */
    public function isTall(): bool
    {
        return $this->height() > $this->width();
    }

    /** Formáty pozvánky na akciu — snímka nástenky ostáva pri svojich dvoch. */
    public static function inviteValues(): array
    {
        return [self::Square->value, self::Story->value, self::Slide->value, self::Poster->value];
    }
}
