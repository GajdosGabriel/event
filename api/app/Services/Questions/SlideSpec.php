<?php

namespace App\Services\Questions;

use App\Enums\SlideTheme;
use App\Enums\SlideVariant;

/**
 * Rozvrh snímky — všetky súradnice a veľkosti písma na jednom mieste.
 *
 * Každé číslo je odvodené zo zlomku rozmerov plátna, takže široká snímka
 * (1920×1080) a štvorec (1080×1080) idú jednou kresliacou cestou a v
 * SlideRenderi nie je ani jedna natvrdo napísaná súradnica.
 */
final readonly class SlideSpec
{
    private function __construct(
        public SlideVariant $variant,
        public SlideTheme $theme,
        public int $width,
        public int $height,
        public int $pad,
        /** Text vľavo a QR vpravo (široká snímka), alebo pod sebou (štvorec). */
        public bool $twoColumn,
        public int $cardX,
        public int $cardY,
        public int $cardW,
        public int $cardH,
        public int $cardRadius,
        public int $cardInnerPad,
        public int $qrSize,
        /** Zvislý pruh, do ktorého sa vojde textový blok. */
        public int $textX,
        public int $textY,
        public int $textW,
        public int $textH,
        public int $badgeSize,
        public int $eyebrowSize,
        public int $titleMaxSize,
        public int $titleMinSize,
        public int $titleMaxLines,
        public int $subtitleSize,
        public int $metaSize,
        public int $ctaSize,
        public int $urlSize,
    ) {
    }

    /** Šírka, ktorú má text vnútri bielej karty k dispozícii. */
    public function cardInnerWidth(): int
    {
        return $this->cardW - 2 * $this->cardInnerPad;
    }

    public static function for(SlideVariant $variant, SlideTheme $theme): self
    {
        $w = $variant->width();
        $h = $variant->height();
        $pad = (int) round($h * 0.075);
        $twoColumn = $variant->isTwoColumn();

        // Karta je na širokej snímke výrazne väčšia — premieta sa na plátno
        // a musí sa dať naskenovať zo zadného radu. Na štvorci sa naopak pozerá
        // z ruky, takže tam ustúpi textu.
        $cardW = (int) round($h * ($twoColumn ? 0.62 : 0.42));

        // Tichá zóna okolo QR: štandard žiada štyri moduly, čo je pri
        // typickom kóde zhruba 14 % jeho veľkosti. Odsadenie karty je preto
        // odvodené od QR, nie naopak — inak by ho neskoršie „utiahnutie
        // layoutu" ticho zmenšilo pod hranicu čitateľnosti.
        $qrSize = (int) round($cardW / 1.30);
        $innerPad = (int) round(($cardW - $qrSize) / 2);

        $ctaSize = (int) round($h * ($twoColumn ? 0.032 : 0.026));
        $urlSize = (int) round($h * ($twoColumn ? 0.036 : 0.028));

        // Výška bloku pod QR: dva riadky aj s medzerami, plus spodné odsadenie.
        $belowQr = (int) round($ctaSize * 1.6 + $urlSize * 1.5) + $innerPad;
        $cardH = $innerPad + $qrSize + (int) round($h * 0.022) + $belowQr;

        if ($twoColumn) {
            $cardX = $w - $pad - $cardW;
            $cardY = (int) round(($h - $cardH) / 2);
            $textX = $pad;
            $textW = $cardX - $pad - (int) round($pad * 0.9);
            $textY = $pad;
            $textH = $h - 2 * $pad;
        } else {
            $cardX = (int) round(($w - $cardW) / 2);
            $cardY = $h - $pad - $cardH;
            $textX = $pad;
            $textW = $w - 2 * $pad;
            $textY = $pad;
            $textH = $cardY - $pad - (int) round($h * 0.022);
        }

        return new self(
            variant: $variant,
            theme: $theme,
            width: $w,
            height: $h,
            pad: $pad,
            twoColumn: $twoColumn,
            cardX: $cardX,
            cardY: $cardY,
            cardW: $cardW,
            cardH: $cardH,
            cardRadius: (int) round($h * 0.032),
            cardInnerPad: $innerPad,
            qrSize: $qrSize,
            textX: $textX,
            textY: $textY,
            textW: max(1, $textW),
            textH: max(1, $textH),
            // Odznak s fotkou kanála sa na štvorec nezmestí bez toho, aby ubral
            // z nadpisu — a na sociálnu sieť ho aj tak nesie profil, z ktorého
            // sa obrázok zdieľa.
            badgeSize: $twoColumn ? (int) round($h * 0.105) : 0,
            eyebrowSize: (int) round($h * ($twoColumn ? 0.028 : 0.026)),
            titleMaxSize: (int) round($h * ($twoColumn ? 0.095 : 0.063)),
            titleMinSize: (int) round($h * ($twoColumn ? 0.044 : 0.036)),
            titleMaxLines: $twoColumn ? 3 : 2,
            subtitleSize: (int) round($h * ($twoColumn ? 0.030 : 0.024)),
            metaSize: (int) round($h * ($twoColumn ? 0.032 : 0.024)),
            ctaSize: $ctaSize,
            urlSize: $urlSize,
        );
    }

    public function qrX(): int
    {
        return $this->cardX + (int) round(($this->cardW - $this->qrSize) / 2);
    }

    public function qrY(): int
    {
        return $this->cardY + $this->cardInnerPad;
    }
}
