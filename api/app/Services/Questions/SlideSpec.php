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
        /** Základná medzera medzi blokmi (pod QR, pod odznakom). */
        public int $gap = 0,
        /** Biela karta s QR kódom — chýba len v náhľade odkazu (og). */
        public bool $hasCard = true,
    ) {}

    /** Šírka, ktorú má text vnútri bielej karty k dispozícii. */
    public function cardInnerWidth(): int
    {
        return $this->cardW - 2 * $this->cardInnerPad;
    }

    public static function for(SlideVariant $variant, SlideTheme $theme): self
    {
        if (! $variant->hasQrCard()) {
            return self::cardless($variant, $theme);
        }

        $w = $variant->width();
        $h = $variant->height();
        // Veľkosti sa odvodzujú od kratšej strany. Pri širokej snímke a štvorci
        // je to výška (čísla sú teda tie isté ako predtým); pri formátoch na
        // výšku by výška nafúkla kartu aj písmo cez okraj šírky.
        $s = min($w, $h);
        $tall = $variant->isTall();
        $pad = (int) round($s * 0.075);
        $twoColumn = $variant->isTwoColumn();

        // Karta je na širokej snímke výrazne väčšia — premieta sa na plátno
        // a musí sa dať naskenovať zo zadného radu. Na štvorci sa naopak pozerá
        // z ruky, takže tam ustúpi textu.
        // Na výšku je pod textom miesta dosť — plagát sa skenuje z chodby
        // a story z cudzieho telefónu, tak QR dostane viac.
        $cardW = (int) round($s * ($twoColumn ? 0.62 : ($tall ? 0.56 : 0.42)));

        // Tichá zóna okolo QR: štandard žiada štyri moduly, čo je pri
        // typickom kóde zhruba 14 % jeho veľkosti. Odsadenie karty je preto
        // odvodené od QR, nie naopak — inak by ho neskoršie „utiahnutie
        // layoutu" ticho zmenšilo pod hranicu čitateľnosti.
        $qrSize = (int) round($cardW / 1.30);
        $innerPad = (int) round(($cardW - $qrSize) / 2);

        $ctaSize = (int) round($s * ($twoColumn || $tall ? 0.032 : 0.026));
        $urlSize = (int) round($s * ($twoColumn || $tall ? 0.036 : 0.028));

        // Výška bloku pod QR: dva riadky aj s medzerami, plus spodné odsadenie.
        $belowQr = (int) round($ctaSize * 1.6 + $urlSize * 1.5) + $innerPad;
        $cardH = $innerPad + $qrSize + (int) round($s * 0.022) + $belowQr;

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
            $textH = $cardY - $pad - (int) round($s * 0.022);
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
            cardRadius: (int) round($s * 0.032),
            cardInnerPad: $innerPad,
            qrSize: $qrSize,
            textX: $textX,
            textY: $textY,
            textW: max(1, $textW),
            textH: max(1, $textH),
            // Odznak s fotkou kanála sa na štvorec nezmestí bez toho, aby ubral
            // z nadpisu — a na sociálnu sieť ho aj tak nesie profil, z ktorého
            // sa obrázok zdieľa.
            badgeSize: $twoColumn ? (int) round($s * 0.105) : 0,
            eyebrowSize: (int) round($s * ($twoColumn ? 0.028 : ($tall ? 0.034 : 0.026))),
            titleMaxSize: (int) round($s * ($twoColumn ? 0.095 : ($tall ? 0.092 : 0.063))),
            titleMinSize: (int) round($s * ($twoColumn ? 0.044 : 0.036)),
            titleMaxLines: $twoColumn || $tall ? ($tall ? 4 : 3) : 2,
            subtitleSize: (int) round($s * ($twoColumn ? 0.030 : ($tall ? 0.036 : 0.024))),
            metaSize: (int) round($s * ($twoColumn ? 0.032 : ($tall ? 0.040 : 0.024))),
            ctaSize: $ctaSize,
            urlSize: $urlSize,
            gap: (int) round($s * 0.022),
        );
    }

    /**
     * Náhľad odkazu (`og:image`) — bez QR karty. QR v náhľade na WhatsAppe
     * či Facebooku nemá zmysel: človek už drží v ruke odkaz, na ktorý stačí
     * ťuknúť. Text dostane celú šírku a väčšie písmo, lebo sa náhľad
     * zobrazuje zmenšený na šírku bubliny v chate.
     */
    private static function cardless(SlideVariant $variant, SlideTheme $theme): self
    {
        $w = $variant->width();
        $h = $variant->height();
        $s = min($w, $h);
        $pad = (int) round($s * 0.09);

        return new self(
            variant: $variant,
            theme: $theme,
            width: $w,
            height: $h,
            pad: $pad,
            twoColumn: false,
            cardX: 0,
            cardY: 0,
            cardW: 0,
            cardH: 0,
            cardRadius: 0,
            cardInnerPad: 0,
            qrSize: 0,
            textX: $pad,
            textY: $pad,
            textW: $w - 2 * $pad,
            textH: $h - 2 * $pad,
            // Bez odznaku: organizátora v náhľade nesie podnadpis a monogram
            // vo farebnom motíve splýva s pozadím.
            badgeSize: 0,
            eyebrowSize: (int) round($s * 0.042),
            titleMaxSize: (int) round($s * 0.11),
            titleMinSize: (int) round($s * 0.06),
            titleMaxLines: 3,
            subtitleSize: (int) round($s * 0.042),
            metaSize: (int) round($s * 0.046),
            ctaSize: 0,
            urlSize: 0,
            gap: (int) round($s * 0.022),
            hasCard: false,
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
