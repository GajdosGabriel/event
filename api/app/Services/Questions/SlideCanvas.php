<?php

namespace App\Services\Questions;

use GdImage;

/**
 * Kresliace primitívy nad GD. Oddelené od SlideRenderer, aby v ňom ostal len
 * rozvrh snímky a nie stovky riadkov práce s pixelmi.
 *
 * Väčšina metód tu existuje preto, že „rozumný" postup dá v GD zlý výsledok.
 * Odôvodnenia sú pri jednotlivých metódach — nie sú to poznámky pre poriadok,
 * ale zoznam pascí, do ktorých sa dá spadnúť pri prvom „upratovaní".
 */
class SlideCanvas
{
    /**
     * Nové plátno.
     *
     * `alphablending = true` a `savealpha = false` je pri skladaní snímky
     * NUTNÉ a je to opak toho, čo robí ImageVariantGenerator (ten zachováva
     * alfu zdroja pri zmenšovaní). Pri vypnutom blendingu sa priesvitný závoj
     * namiesto zmiešania *nahradí* a z celého stmavenia vyjde čierna doska.
     */
    public function create(int $width, int $height): GdImage
    {
        $im = imagecreatetruecolor($width, $height);
        imagealphablending($im, true);
        imagesavealpha($im, false);

        return $im;
    }

    /** Farba z `#RRGGBB`. `$alpha` je GD stupnica 0 (nepriehľadné) – 127. */
    public function color(GdImage $im, string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = $this->rgb($hex);

        return $alpha > 0
            ? imagecolorallocatealpha($im, $r, $g, $b, $alpha)
            : imagecolorallocate($im, $r, $g, $b);
    }

    /**
     * Zvislý prechod ako riadky po jednom pixeli. Pri 1080 riadkoch to stojí
     * okolo 26 ms a je to presné; kreslenie otočených čiar netreba.
     */
    public function gradientVertical(GdImage $im, int $width, int $height, string $from, string $to): void
    {
        [$r1, $g1, $b1] = $this->rgb($from);
        [$r2, $g2, $b2] = $this->rgb($to);

        for ($y = 0; $y < $height; $y++) {
            $t = $height > 1 ? $y / ($height - 1) : 0;
            $color = imagecolorallocate(
                $im,
                (int) round($r1 + ($r2 - $r1) * $t),
                (int) round($g1 + ($g2 - $g1) * $t),
                (int) round($b1 + ($b2 - $b1) * $t),
            );
            imagefilledrectangle($im, 0, $y, $width - 1, $y, $color);
        }
    }

    /**
     * Uhlopriečny prechod zo štyroch rohových farieb.
     *
     * Semienko je 64×64 a interpoluje sa v PHP, nie 2×2 nechané na GD:
     * `imagecopyresampled` z obrázka 2×2 nevytvorí prechod, ale štyri ostro
     * ohraničené štvrtiny — bilineárna interpolácia v GD mieša len medzi
     * stredmi zdrojových pixelov, takže vonkajšie tri štvrtiny plochy zostanú
     * ploché. Pri 64×64 sú stredy dosť husté na to, aby bol výsledok hladký,
     * a naplnenie stojí zlomok milisekundy.
     */
    public function gradientDiagonal(GdImage $im, int $width, int $height, string $tl, string $tr, string $bl, string $br): void
    {
        $n = 64;
        $seed = imagecreatetruecolor($n, $n);

        [$tlR, $tlG, $tlB] = $this->rgb($tl);
        [$trR, $trG, $trB] = $this->rgb($tr);
        [$blR, $blG, $blB] = $this->rgb($bl);
        [$brR, $brG, $brB] = $this->rgb($br);

        for ($y = 0; $y < $n; $y++) {
            $v = $y / ($n - 1);

            for ($x = 0; $x < $n; $x++) {
                $u = $x / ($n - 1);

                $r = (1 - $u) * (1 - $v) * $tlR + $u * (1 - $v) * $trR + (1 - $u) * $v * $blR + $u * $v * $brR;
                $g = (1 - $u) * (1 - $v) * $tlG + $u * (1 - $v) * $trG + (1 - $u) * $v * $blG + $u * $v * $brG;
                $b = (1 - $u) * (1 - $v) * $tlB + $u * (1 - $v) * $trB + (1 - $u) * $v * $blB + $u * $v * $brB;

                imagesetpixel($seed, $x, $y, imagecolorallocate($seed, (int) round($r), (int) round($g), (int) round($b)));
            }
        }

        imagecopyresampled($im, $seed, 0, 0, 0, 0, $width, $height, $n, $n);
        imagedestroy($seed);
    }

    /**
     * Zdroj orezaný „na výplň" — pomer sa zachová, prebytok sa odreže.
     *
     * Zvislé ťažisko je na 40 %, nie v strede: na fotkách z podujatí bývajú
     * tváre v hornej tretine a stredové orezanie ich uťalo.
     */
    public function coverResample(GdImage $src, int $dstW, int $dstH): GdImage
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $scale = max($dstW / $srcW, $dstH / $srcH);
        $cropW = (int) round($dstW / $scale);
        $cropH = (int) round($dstH / $scale);
        $srcX = (int) (($srcW - $cropW) / 2);
        $srcY = (int) (($srcH - $cropH) * 0.4);

        $dst = $this->create($dstW, $dstH);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $dstW, $dstH, $cropW, $cropH);

        return $dst;
    }

    /**
     * Fotka rozostretá cez celé pozadie.
     *
     * GD má pevné jadro 3×3, takže jeden prechod pri 1920×1080 stojí okolo pol
     * sekundy a na pekné rozostrenie ich treba päť. Preto sa fotka najprv
     * zmenší na 256 px na dlhšej strane, rozostrí sa tam (~35 ms) a až potom sa
     * roztiahne späť — výsledné rozostrenie je zodpovedajúco silnejšie a
     * roztiahnutie pridá vlastné zjemnenie. Pod 192 px šírky nechodiť, začnú
     * presvitať artefakty roztiahnutia.
     */
    public function blurredBackdrop(GdImage $canvas, GdImage $photo, int $width, int $height): void
    {
        $smallW = 256;
        $smallH = max(1, (int) round($smallW * $height / $width));

        $small = $this->coverResample($photo, $smallW, $smallH);

        for ($i = 0; $i < 5; $i++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopyresampled($canvas, $small, 0, 0, 0, 0, $width, $height, $smallW, $smallH);
        imagedestroy($small);
    }

    /** Priesvitný závoj cez celé plátno — kvôli čitateľnosti textu na fotke. */
    public function scrim(GdImage $im, int $width, int $height, string $hex, int $alpha): void
    {
        if ($alpha <= 0) {
            return;
        }

        imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $this->color($im, $hex, $alpha));
    }

    /**
     * Zaoblený obdĺžnik.
     *
     * Musí to byť antialiasovaný mnohouholník: `imageantialias()` sa pri
     * `imagefilledellipse` **ticho ignoruje** (v rohu vzniknú dve farby namiesto
     * šesťdesiatich) a rovnako sa ignoruje pri priesvitnej výplni. Preto sa
     * oblúky aproximujú úsečkami a farba musí byť nepriehľadná.
     */
    public function roundedRect(GdImage $im, int $x, int $y, int $w, int $h, int $radius, int $color): void
    {
        $radius = max(0, min($radius, (int) floor(min($w, $h) / 2)));

        if ($radius === 0) {
            imagefilledrectangle($im, $x, $y, $x + $w - 1, $y + $h - 1, $color);

            return;
        }

        $centers = [
            [$x + $w - $radius, $y + $radius, -90],
            [$x + $w - $radius, $y + $h - $radius, 0],
            [$x + $radius, $y + $h - $radius, 90],
            [$x + $radius, $y + $radius, 180],
        ];

        $points = [];
        $segments = 12;

        foreach ($centers as [$cx, $cy, $startAngle]) {
            for ($i = 0; $i <= $segments; $i++) {
                $angle = deg2rad($startAngle + 90 * $i / $segments);
                $points[] = (int) round($cx + $radius * cos($angle));
                $points[] = (int) round($cy + $radius * sin($angle));
            }
        }

        imageantialias($im, true);
        imagefilledpolygon($im, $points, $color);
        imageantialias($im, false);
    }

    /**
     * Kruhový odznak s fotkou, alebo s monogramom, keď fotka nie je.
     *
     * Kreslí sa štvornásobne zväčšený a až potom sa zmenší — GD nevie
     * antialiasovať kruhovú masku, takže hladký okraj vznikne až zmenšením.
     * Pri 180 px je to okolo piatich milisekúnd.
     */
    public function circleBadge(
        GdImage $canvas,
        ?GdImage $photo,
        string $monogram,
        int $x,
        int $y,
        int $size,
        string $bgHex,
        string $fgHex,
        string $font,
    ): void {
        $factor = 4;
        $big = $size * $factor;

        $sprite = $this->create($big, $big);
        imagealphablending($sprite, false);
        imagefilledrectangle($sprite, 0, 0, $big - 1, $big - 1, imagecolorallocatealpha($sprite, 0, 0, 0, 127));
        imagealphablending($sprite, true);

        if ($photo !== null) {
            $filled = $this->coverResample($photo, $big, $big);
            imagecopy($sprite, $filled, 0, 0, 0, 0, $big, $big);
            imagedestroy($filled);
        } else {
            imagefilledrectangle($sprite, 0, 0, $big - 1, $big - 1, $this->color($sprite, $bgHex));

            if ($monogram !== '') {
                $fontSize = (int) round($big * 0.42);
                $this->drawLine($sprite, $monogram, $font, $fontSize, (int) ($big / 2), (int) ($big / 2 - $fontSize * 0.7), $this->color($sprite, $fgHex), 'center');
            }
        }

        // Vyhryznutie kruhu: všetko mimo polomeru sa prepíše na priehľadné.
        $this->punchCircle($sprite, $big);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $sprite, $x, $y, 0, 0, $size, $size, $big, $big);
        imagedestroy($sprite);
    }

    private function punchCircle(GdImage $sprite, int $size): void
    {
        $transparent = imagecolorallocatealpha($sprite, 0, 0, 0, 127);
        imagealphablending($sprite, false);

        $center = ($size - 1) / 2;
        $radiusSq = $center * $center;

        for ($py = 0; $py < $size; $py++) {
            $dy = $py - $center;

            for ($px = 0; $px < $size; $px++) {
                $dx = $px - $center;

                if ($dx * $dx + $dy * $dy > $radiusSq) {
                    imagesetpixel($sprite, $px, $py, $transparent);
                }
            }
        }

        imagealphablending($sprite, true);
        imagesavealpha($sprite, true);
    }

    /**
     * Rozmery riadku pre danú dvojicu (písmo, veľkosť).
     *
     * Meria sa raz referenčným reťazcom, nie pre každý riadok zvlášť:
     * `imagettfbbox` vracia výšku konkrétneho textu, a mäkčene ju nafúknu
     * (pri 48 pt o vyše desať pixelov). Riadkovanie počítané z vlastného
     * rámčeka každého riadku by teda skákalo podľa toho, či riadok náhodou
     * začína na „Á".
     *
     * @return array{ascent: int, descent: int, line: int}
     */
    public function metrics(string $font, int $size): array
    {
        $box = imagettfbbox($size, 0, $font, 'ÁŤĽÔgjpqy');

        if ($box === false) {
            return ['ascent' => $size, 'descent' => (int) round($size * 0.25), 'line' => (int) round($size * 1.4)];
        }

        $ascent = -$box[7];
        $descent = $box[1];

        return [
            'ascent' => $ascent,
            'descent' => $descent,
            'line' => (int) round(($ascent + $descent) * 1.18),
        ];
    }

    public function textWidth(string $font, int $size, string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : $box[2] - $box[0];
    }

    /**
     * Jeden riadok textu. `$topY` je horná hrana, nie účiara — účiaru dopočíta
     * metrika, aby volajúci nemusel riešiť, že GD kreslí od účiary nahor.
     *
     * `$align`: `left` (x je ľavá hrana), `center` (x je stred),
     * `right` (x je pravá hrana).
     */
    public function drawLine(GdImage $im, string $text, string $font, int $size, int $x, int $topY, int $color, string $align = 'left'): void
    {
        if ($text === '') {
            return;
        }

        $box = imagettfbbox($size, 0, $font, $text);

        if ($box === false) {
            return;
        }

        $width = $box[2] - $box[0];
        $metrics = $this->metrics($font, $size);

        $drawX = match ($align) {
            'center' => (int) round($x - $width / 2 - $box[0]),
            'right' => $x - $box[2],
            default => $x - $box[0],
        };

        imagettftext($im, $size, 0, $drawX, $topY + $metrics['ascent'], $color, $font, $text);
    }

    /**
     * Zalomenie na šírku, merané skutočným písmom.
     *
     * Slovo dlhšie ako celý box treba tvrdo rozseknúť — inak sa vonkajší cyklus
     * nikdy neposunie.
     *
     * @return array<int, string>
     */
    public function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if ($this->textWidth($font, $size, $candidate) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            foreach ($this->breakLongWord($word, $font, $size, $maxWidth) as $index => $chunk) {
                if ($index === 0 && $this->textWidth($font, $size, $chunk) <= $maxWidth) {
                    $current = $chunk;

                    continue;
                }

                $lines[] = $current !== '' ? $current : $chunk;
                $current = $current !== '' ? $chunk : '';
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    /** @return array<int, string> */
    private function breakLongWord(string $word, string $font, int $size, int $maxWidth): array
    {
        if ($this->textWidth($font, $size, $word) <= $maxWidth) {
            return [$word];
        }

        $chunks = [];
        $current = '';

        foreach (mb_str_split($word) as $char) {
            if ($current !== '' && $this->textWidth($font, $size, $current . $char) > $maxWidth) {
                $chunks[] = $current;
                $current = '';
            }

            $current .= $char;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Najväčšia veľkosť, pri ktorej sa text zmestí do daného počtu riadkov.
     * Meria naozaj, nie odhadom z počtu znakov — a je to lacné, celé hľadanie
     * je pod 20 ms.
     *
     * @return array{size: int, lines: array<int, string>}
     */
    public function fitLines(string $text, string $font, int $maxSize, int $minSize, int $maxWidth, int $maxLines): array
    {
        $lines = [];

        for ($size = $maxSize; $size >= $minSize; $size -= 4) {
            $lines = $this->wrap($text, $font, $size, $maxWidth);

            if (count($lines) <= $maxLines) {
                return ['size' => $size, 'lines' => $lines];
            }
        }

        // Ani najmenšia veľkosť nestačila — text sa oreže, aby nepretiekol
        // cez QR kód. Poslednému riadku sa dopíšu tri bodky.
        $lines = array_slice($lines, 0, $maxLines);
        $last = count($lines) - 1;

        if ($last >= 0) {
            $lines[$last] = rtrim($lines[$last]) . '…';
        }

        return ['size' => $minSize, 'lines' => $lines];
    }

    /**
     * Dekódovanie fotky s poistkami. Vracia null, keď sa to nedá — snímka potom
     * spadne na monogram alebo na prechod, ale nikdy nespadne celá.
     *
     * Strop na megapixely je tu preto, že `imagecreatefromstring` nemá žiadnu
     * kontrolu veľkosti: fotka 4000×3000 zaberie v pamäti 48 MB.
     */
    public function decodePhoto(?string $bytes, float $maxMegapixels = 30.0): ?GdImage
    {
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || ($info[0] * $info[1]) > $maxMegapixels * 1_000_000) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        return $image === false ? null : $image;
    }

    /**
     * Emoji a symboly, ktoré priložené písmo nepozná. GD ich nakreslí ako
     * prázdny rámček — a keďže náhradné písmo nemá, jediná obrana je vyhodiť
     * ich ešte pred kreslením.
     */
    public function sanitizeText(?string $text): string
    {
        $clean = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{2190}-\x{21FF}]/u', '', (string) $text);

        return trim(preg_replace('/\s+/u', ' ', (string) $clean) ?? '');
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
