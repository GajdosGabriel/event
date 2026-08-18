<?php

namespace App\Services\Questions;

use App\Enums\SlideTheme;
use App\Enums\SlideVariant;
use App\Services\Tickets\QrCodeGenerator;
use GdImage;

/**
 * Vykreslí snímku s QR kódom, ktorú organizátor premietne na plátno.
 *
 * Kreslí sa v čistom GD — rovnako ako zmenšovanie obrázkov v
 * App\Services\Files\ImageVariantGenerator. Žiadna nová composer závislosť:
 * projekt si drží veľmi štíhly `require` a headless prehliadač ani Imagick na
 * tomto hostingu nie sú.
 *
 * Snímka sa **nikde neukladá**. Vzniká pri každom stiahnutí nanovo, presne ako
 * QR kódy vstupeniek. Cena je okolo pol sekundy CPU, čo drží limiter `render`.
 *
 * Pasce GD, kvôli ktorým vyzerá kód inak, než by človek čakal, sú popísané
 * v SlideCanvas — pri každej metóde tá jej.
 */
class SlideRenderer
{
    public function __construct(
        private SlideCanvas $canvas,
        private FontLibrary $fonts,
        private QrCodeGenerator $qr,
    ) {}

    public function isAvailable(): bool
    {
        return $this->fonts->isUsable();
    }

    /** @return string bajty PNG */
    public function render(SlideContent $content, SlideVariant $variant, SlideTheme $theme): string
    {
        $spec = SlideSpec::for($variant, $theme);
        $palette = $theme->palette();

        $image = $this->canvas->create($spec->width, $spec->height);
        $photo = $this->canvas->decodePhoto($content->photo);

        $this->paintBackground($image, $spec, $palette, $photo);
        $this->paintCard($image, $spec, $palette, $content);
        $this->paintTextColumn($image, $spec, $palette, $content, $photo);

        if ($photo !== null) {
            imagedestroy($photo);
        }

        ob_start();
        imagepng($image, null, 6);
        $bytes = (string) ob_get_clean();

        imagedestroy($image);

        return $bytes;
    }

    /**
     * @param  array<string, mixed>  $palette
     */
    private function paintBackground(GdImage $image, SlideSpec $spec, array $palette, ?GdImage $photo): void
    {
        if ($spec->theme->usesPhotoBackdrop() && $photo !== null) {
            $this->canvas->blurredBackdrop($image, $photo, $spec->width, $spec->height);
            $this->canvas->scrim($image, $spec->width, $spec->height, (string) $palette['bg_from'], (int) $palette['scrim']);

            // Druhý závoj len na strane s textom — fotka býva na jednom kraji
            // svetlá a inak by tam nadpis zmizol.
            $this->paintTextSideScrim($image, $spec, (string) $palette['bg_from']);

            return;
        }

        if ($spec->theme === SlideTheme::Bold) {
            $this->canvas->gradientDiagonal(
                $image,
                $spec->width,
                $spec->height,
                (string) $palette['bg_from'],
                (string) $palette['bg_from2'],
                (string) $palette['bg_to2'],
                (string) $palette['bg_to'],
            );

            return;
        }

        $this->canvas->gradientVertical($image, $spec->width, $spec->height, (string) $palette['bg_from'], (string) $palette['bg_to']);
    }

    /**
     * Prechod do tmy nad textovým pruhom.
     *
     * Musí dobehnúť plynulo až na nulu, inak vznikne v mieste, kde pruh končí,
     * viditeľný zvislý zlom — a ten je na projektore nápadnejší než samotná
     * fotka. Preto sa nekreslí len po šírku textu, ale doexponuje sa cez celé
     * plátno s druhou mocninou útlmu.
     */
    private function paintTextSideScrim(GdImage $image, SlideSpec $spec, string $hex): void
    {
        $peak = 76;

        if ($spec->twoColumn) {
            for ($x = 0; $x < $spec->width; $x++) {
                $t = $x / max(1, $spec->width - 1);
                $alpha = (int) round($peak * (1 - $t) * (1 - $t));

                if ($alpha > 0) {
                    imagefilledrectangle($image, $x, 0, $x, $spec->height - 1, $this->canvas->color($image, $hex, $alpha));
                }
            }

            return;
        }

        for ($y = 0; $y < $spec->height; $y++) {
            $t = $y / max(1, $spec->height - 1);
            $alpha = (int) round($peak * (1 - $t) * (1 - $t));

            if ($alpha > 0) {
                imagefilledrectangle($image, 0, $y, $spec->width - 1, $y, $this->canvas->color($image, $hex, $alpha));
            }
        }
    }

    /**
     * Biela karta s QR kódom, adresou a kódom na prepísanie.
     *
     * Vnútorné odsadenie karty JE tichá zóna QR kódu — viď SlideSpec. Zmenšiť
     * ho znamená rozbiť skenovanie, hoci na obrázku to nevidno.
     *
     * @param  array<string, mixed>  $palette
     */
    private function paintCard(GdImage $image, SlideSpec $spec, array $palette, SlideContent $content): void
    {
        $cardColor = $this->canvas->color($image, (string) $palette['card']);
        $this->canvas->roundedRect($image, $spec->cardX, $spec->cardY, $spec->cardW, $spec->cardH, $spec->cardRadius, $cardColor);

        $qr = $this->qr->imageForUrl($content->qrUrl, $spec->qrSize);
        imagecopy($image, $qr, $spec->qrX(), $spec->qrY(), 0, 0, $spec->qrSize, $spec->qrSize);
        imagedestroy($qr);

        $centerX = $spec->cardX + (int) round($spec->cardW / 2);
        $textColor = $this->canvas->color($image, (string) $palette['card_text']);
        $mutedColor = $this->canvas->color($image, $this->mix((string) $palette['card_text'], (string) $palette['card'], 0.42));

        $y = $spec->qrY() + $spec->qrSize + (int) round($spec->height * 0.022);

        $y += $this->line($image, $content->cta, $this->fonts->semibold(), $spec->ctaSize, $centerX, $y, $mutedColor, 'center', 1.6);

        // Adresa sa musí zmestiť do karty za každú cenu — je to jediná cesta
        // pre toho, komu nefunguje fotoaparát. Radšej menšie písmo než text
        // pretečený cez okraj.
        $this->line(
            $image,
            $content->url,
            $this->fonts->bold(),
            $this->shrinkToWidth($content->url, $this->fonts->bold(), $spec->urlSize, $spec->cardInnerWidth()),
            $centerX,
            $y,
            $textColor,
            'center',
            1.5,
        );
    }

    /** Najväčšia veľkosť písma, pri ktorej sa jednoriadkový text ešte zmestí. */
    private function shrinkToWidth(string $text, string $font, int $maxSize, int $maxWidth): int
    {
        for ($size = $maxSize; $size > 8; $size -= 2) {
            if ($this->canvas->textWidth($font, $size, $text) <= $maxWidth) {
                return $size;
            }
        }

        return 8;
    }

    /**
     * Textový stĺpec. Blok sa najprv zmeria a až potom sa zvislo vycentruje
     * v dostupnom pruhu — inak by krátky názov visel pri hornom okraji a dlhý
     * by pretiekol dole.
     *
     * @param  array<string, mixed>  $palette
     */
    private function paintTextColumn(GdImage $image, SlideSpec $spec, array $palette, SlideContent $content, ?GdImage $photo): void
    {
        $textColor = $this->canvas->color($image, (string) $palette['text']);
        $mutedColor = $this->canvas->color($image, (string) $palette['muted']);
        $accentColor = $this->canvas->color($image, (string) $palette['accent']);

        // Textový blok sa skladá zhora nadol a musí sa zmestiť do pruhu vedľa
        // (alebo nad) kartou. Keď sa nezmestí, ubúda od najmenej dôležitého:
        // najprv miesto, potom podnadpis, potom odznak, a až nakoniec sa
        // zmenšuje nadpis. Bez tohto by dlhý názov podujatia pretiekol cez QR
        // kód — a to je jediná vec na snímke, ktorá musí byť čitateľná vždy.
        $dropped = 0;
        $titleMax = $spec->titleMaxSize;

        do {
            $rows = $this->buildRows($spec, $content, $textColor, $mutedColor, $accentColor, $titleMax, $dropped);
            $total = array_sum(array_column($rows, 'height'));

            if ($total <= $spec->textH) {
                break;
            }

            if ($dropped < 3) {
                $dropped++;

                continue;
            }

            $titleMax -= 6;
        } while ($titleMax > $spec->titleMinSize);

        $y = $spec->textY + max(0, (int) round(($spec->textH - $total) / 2));

        foreach ($rows as $row) {
            if ($row['kind'] === 'badge') {
                $this->canvas->circleBadge(
                    $image,
                    $photo,
                    $this->monogram($content->organizer ?? $content->title),
                    $spec->textX,
                    $y,
                    $spec->badgeSize,
                    (string) $palette['accent'],
                    (string) $palette['card'],
                    $this->fonts->bold(),
                );
            } else {
                $this->canvas->drawLine($image, $row['text'], $row['font'], $row['size'], $spec->textX, $y, $row['color']);
            }

            $y += $row['height'];
        }
    }

    /**
     * Riadky textového bloku. `$dropped` hovorí, koľko nepovinných riadkov už
     * ustúpilo miestu: 1 = bez miesta konania, 2 = aj bez podnadpisu,
     * 3 = aj bez odznaku.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(
        SlideSpec $spec,
        SlideContent $content,
        int $textColor,
        int $mutedColor,
        int $accentColor,
        int $titleMaxSize,
        int $dropped,
    ): array {
        $fitted = $this->canvas->fitLines(
            $this->canvas->sanitizeText($content->title),
            $this->fonts->bold(),
            max($spec->titleMinSize, $titleMaxSize),
            $spec->titleMinSize,
            $spec->textW,
            $spec->titleMaxLines,
        );

        $rows = [];

        if ($spec->badgeSize > 0 && $dropped < 3) {
            $rows[] = ['kind' => 'badge', 'height' => $spec->badgeSize + (int) round($spec->height * 0.028)];
        }

        $rows[] = $this->textRow($content->eyebrow, $this->fonts->semibold(), $spec->eyebrowSize, $accentColor, 1.55);

        $lastLine = count($fitted['lines']) - 1;

        foreach ($fitted['lines'] as $index => $line) {
            $rows[] = $this->textRow(
                $line,
                $this->fonts->bold(),
                $fitted['size'],
                $textColor,
                $index === $lastLine ? 1.35 : 1.14,
            );
        }

        if (filled($content->subtitle) && $dropped < 2) {
            $fit = $this->fitMetaLine($this->canvas->sanitizeText($content->subtitle), $this->fonts->regular(), $spec->subtitleSize, $spec->textW);
            $rows[] = $this->textRow($fit['text'], $this->fonts->regular(), $fit['size'], $mutedColor, 1.5);
        }

        if (filled($content->when)) {
            $fit = $this->fitMetaLine($this->canvas->sanitizeText($content->when), $this->fonts->semibold(), $spec->metaSize, $spec->textW);
            $rows[] = $this->textRow($fit['text'], $this->fonts->semibold(), $fit['size'], $textColor, 1.28);
        }

        if (filled($content->where) && $dropped < 1) {
            $fit = $this->fitMetaLine($this->canvas->sanitizeText($content->where), $this->fonts->regular(), $spec->metaSize, $spec->textW);
            $rows[] = $this->textRow($fit['text'], $this->fonts->regular(), $fit['size'], $mutedColor, 1.28);
        }

        return $rows;
    }

    /**
     * @return array{kind: string, text: string, font: string, size: int, color: int, height: int}
     */
    private function textRow(string $text, string $font, int $size, int $color, float $lineFactor): array
    {
        $metrics = $this->canvas->metrics($font, $size);

        return [
            'kind' => 'text',
            'text' => $text,
            'font' => $font,
            'size' => $size,
            'color' => $color,
            'height' => (int) round(($metrics['ascent'] + $metrics['descent']) * $lineFactor),
        ];
    }

    /** Nakreslí riadok a vráti, o koľko sa má posunúť kurzor. */
    private function line(GdImage $image, string $text, string $font, int $size, int $x, int $y, int $color, string $align, float $lineFactor): int
    {
        $this->canvas->drawLine($image, $text, $font, $size, $x, $y, $color, $align);

        $metrics = $this->canvas->metrics($font, $size);

        return (int) round(($metrics['ascent'] + $metrics['descent']) * $lineFactor);
    }

    /**
     * Jednoriadkový sprievodný text (termín, miesto, názov podujatia).
     *
     * Najprv sa skúsi zmenšiť písmo — odseknutý dátum („22. 11. 2026, 00:…")
     * je horší než o dva body menší, ale celý. Skracovanie tromi bodkami je až
     * posledná záchrana, keď ani najmenšia veľkosť nestačí.
     *
     * @return array{size: int, text: string}
     */
    private function fitMetaLine(string $text, string $font, int $maxSize, int $maxWidth): array
    {
        $minSize = max(10, (int) round($maxSize * 0.72));

        for ($size = $maxSize; $size >= $minSize; $size -= 2) {
            if ($this->canvas->textWidth($font, $size, $text) <= $maxWidth) {
                return ['size' => $size, 'text' => $text];
            }
        }

        return ['size' => $minSize, 'text' => $this->truncate($text, $font, $minSize, $maxWidth)];
    }

    /** Skrátenie tromi bodkami — posledná záchrana pre fitMetaLine(). */
    private function truncate(string $text, string $font, int $size, int $maxWidth): string
    {
        if ($text === '' || $this->canvas->textWidth($font, $size, $text) <= $maxWidth) {
            return $text;
        }

        $chars = mb_str_split($text);

        while ($chars !== [] && $this->canvas->textWidth($font, $size, implode('', $chars).'…') > $maxWidth) {
            array_pop($chars);
        }

        return rtrim(implode('', $chars)).'…';
    }

    /** Prvé písmená prvých dvoch slov — náhrada za chýbajúcu fotku kanála. */
    private function monogram(string $name): string
    {
        $words = preg_split('/\s+/u', $this->canvas->sanitizeText($name)) ?: [];
        $letters = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $letters .= mb_strtoupper(mb_substr($word, 0, 1));

            if (mb_strlen($letters) === 2) {
                break;
            }
        }

        return $letters;
    }

    /** Zmiešanie dvoch farieb — na tlmený text vnútri bielej karty. */
    private function mix(string $from, string $to, float $ratio): string
    {
        $a = sscanf(ltrim($from, '#'), '%2x%2x%2x');
        $b = sscanf(ltrim($to, '#'), '%2x%2x%2x');

        return sprintf(
            '#%02X%02X%02X',
            (int) round($a[0] + ($b[0] - $a[0]) * $ratio),
            (int) round($a[1] + ($b[1] - $a[1]) * $ratio),
            (int) round($a[2] + ($b[2] - $a[2]) * $ratio),
        );
    }
}
