<?php

namespace App\Support;

/**
 * Drobné opravy textu z importu, ktoré sa opakujú naprieč zdrojmi.
 *
 * Žijú na jednom mieste, aby ich používal import (nové články) aj migrácia,
 * ktorá opravuje už uložené záznamy.
 */
class ImportedText
{
    /**
     * Interná poznámka tlačovej agentúry: „(TK KBS, is, eg, ml; pz) 20260616014"
     * (autori, redaktor a číslo článku). Čitateľovi nič nehovorí.
     */
    private const NEWSROOM_CODE = '/[ \t]*\(\s*TK\s*KBS\s*,[^()<>]*\)(?:[ \t]*\d{8,})?/u';

    /**
     * Redakčná hlavička článku TK KBS: „Bratislava 2. októbra (TK KBS)",
     * „Vatikán/Južná Kórea 22. októbra (TK KBS) –", „Žilina, 2. marca (TK KBS)".
     * Miesto a deň môžu chýbať (AI z nich občas nechala len „februára (TK KBS)").
     * Miesto sú slová s veľkým začiatočným písmenom, aby sa nezobralo nič
     * z predošlej vety („TK KBS informoval Marián Oravec, Bratislava …").
     */
    private const DATELINE = '/'
        .'(?:\p{Lu}[\p{L}.’\'-]*(?:(?:\s*\/\s*|\s+[–-]\s+|\s+)\p{Lu}[\p{L}.’\'-]*){0,3},?\s+)?'
        .'(?:\d{1,2}\.\s*)?'
        .'(?:januára|februára|marca|apríla|mája|júna|júla|augusta|septembra|októbra|novembra|decembra)'
        .'(?:\s+\d{4})?\s*\(TK\s*KBS\)[.:]?(\s*<\/(?:strong|b)>)?(?:\s*[–—-](?=\s))?[ \t]*'
        .'/u';

    /** Čo po hlavičke ostane prázdne: „<p></p>", „<strong></strong>", „<li>Kontakt:</li>"… */
    private const DATELINE_LEFTOVERS = [
        '/(?:\s*\(TK\s*KBS\))+/u' => '',
        '~<(strong|b|em|i)\b[^>]*>\s*</\1>~iu' => '',
        '~(<(?:p|li)\b[^>]*>)\s*(?:<br\s*/?>\s*)*~iu' => '$1',
        '~(?:\s*<br\s*/?>)+\s*(</(?:p|li)>)~iu' => '$1',
        '~[ \t]*,[ \t]*(</(?:p|li)>)~u' => '$1',
        '~<(p|li)\b[^>]*>\s*(?:<strong>)?\s*(?:Kontakt|Dátum správy)\s*:?\s*(?:</strong>)?\s*</\1>~iu' => '',
        '~<(p|li|h[1-6])\b[^>]*>\s*</\1>[ \t]*\n?~iu' => '',
        '~<(ul|ol)\b[^>]*>\s*</\1>[ \t]*\n?~iu' => '',
    ];

    /** Locatív názvu miesta → nominatív („Kostole sv. Jána" → „Kostol sv. Jána"). */
    private const VENUE_CASES = [
        '/^Seminárnom\s+kostole\b/iu' => 'Seminárny kostol',
        '/^Kostole\b/iu' => 'Kostol',
        '/^Kláštore\b/iu' => 'Kláštor',
        '/^Katedrále\b/iu' => 'Katedrála',
        '/^Bazilike\b/iu' => 'Bazilika',
        '/^Kaplnke\b/iu' => 'Kaplnka',
        '/^Chráme\b/iu' => 'Chrám',
        '/^Dome\b/iu' => 'Dom',
    ];

    /** Odstráni interné kódy agentúry z textu alebo HTML. */
    public static function stripNewsroomCodes(string $text): string
    {
        return preg_replace(self::NEWSROOM_CODE, '', $text) ?? $text;
    }

    /**
     * Odstráni redakčnú hlavičku TK KBS („Bratislava 2. októbra (TK KBS)")
     * z textu alebo HTML. Čitateľovi nič nehovorí a dátum v nej nie je termín
     * podujatia.
     */
    public static function stripDatelines(string $text): string
    {
        // Zatvárací tag ostáva, prázdny „<strong></strong>" zmizne nižšie.
        $stripped = preg_replace(self::DATELINE, '$1', $text, -1, $count);

        if ($count === 0 && ! preg_match('/\(TK\s*KBS\)/u', $text)) {
            return $text;
        }

        $stripped ??= $text;

        foreach (self::DATELINE_LEFTOVERS as $pattern => $replacement) {
            $stripped = preg_replace($pattern, $replacement, $stripped) ?? $stripped;
        }

        return trim($stripped);
    }

    /**
     * Začiatok navigácie v päte webu tkkbs.sk: „| Upozorniť na chybu v správe |
     * [naspäť] O nás | Ochrana osobných údajov | … Nastavenie Cookies
     * (c) TK KBS 2003 - 2026". Import ju občas zoberie s textom článku.
     */
    private const PORTAL_FOOTER_START = '/(?:\[naspäť\]\s*)?(?:Upozorniť\s+na\s+chybu\s+v\s+správe'
        .'|O\s+nás(?:\s|\||<[^>]+>)+Ochrana\s+osobných\s+údajov'
        .'|Ochrana\s+osobných\s+údajov(?:\s|\||<[^>]+>)+(?:Logo(?:\s|\||<[^>]+>)+)?Copyright'
        .'|Hovorca\s+KBS(?:\s|\||<[^>]+>)+Zoznam\s+hovorcov\s+diecéz)/u';

    /** Koniec päty, prípadne s osirelým číslom správy („Kontakt: 20260910015"), ktoré AI pridala za ňu. */
    private const PORTAL_FOOTER_END = '/\(c\)\s*TK\s*KBS\s*\d{4}\s*[-–]\s*\d{4}\.?(?:(?:\s|<[^>]+>)*Kontakt:?(?:\s|<[^>]+>)*\d{11}\b)?/u';

    /**
     * Čo pred pätou ostane bez obsahu: oddeľovač „|", číslo správy
     * („20261103003") alebo osirelý štítok („Kontakt:"), ktorého obsahom bola
     * päta. Redakčný kredit („Zdroje: TASR", „TK KBS informovala …") ostáva.
     */
    private const PORTAL_FOOTER_LEFTOVERS = [
        '/[\s|]+$/u' => '',
        '/[\s,.]*\b\d{11}$/u' => '',
        '/(>|\n|^)\s*\p{Lu}[\p{L}\d ]{0,30}:$/u' => '$1',
    ];

    /**
     * Odstráni z textu alebo HTML pätu webu tkkbs.sk. Redakčný kredit pred
     * ňou nechá. Čo po nej v HTML ostane nevyvážené či prázdne, upraví
     * volajúci (HtmlBodyCleaner).
     */
    public static function stripPortalFooter(string $text): string
    {
        $stripped = $text;

        // Päta môže byť rozdelená do viacerých blokov aj viackrát (AI ju občas zopakovala).
        while (preg_match(self::PORTAL_FOOTER_START, $stripped, $start, PREG_OFFSET_CAPTURE) === 1
            && preg_match(self::PORTAL_FOOTER_END, $stripped, $end, PREG_OFFSET_CAPTURE, $start[0][1]) === 1) {
            $before = substr($stripped, 0, $start[0][1]);
            foreach (self::PORTAL_FOOTER_LEFTOVERS as $pattern => $replacement) {
                $before = preg_replace($pattern, $replacement, $before) ?? $before;
            }

            $stripped = $before.substr($stripped, $end[0][1] + strlen($end[0][0]));
        }

        return $stripped;
    }

    /** „Sv.omša" → „Sv. omša" (skratka svätý/svätá bez medzery pred slovom). */
    public static function fixAbbreviationSpacing(string $text): string
    {
        return preg_replace('/(?<![\w.@\/-])([Ss]v)\.(?=\p{L})/u', '$1. ', $text) ?? $text;
    }

    /** Názov miesta: bez „(okres)" a v 1. páde. */
    public static function venueName(string $name): string
    {
        $name = trim(preg_replace('/\s*\(okres\)\s*$/iu', '', $name) ?? $name);

        foreach (self::VENUE_CASES as $pattern => $nominative) {
            $fixed = preg_replace($pattern, $nominative, $name, 1, $count);

            if ($count > 0 && is_string($fixed)) {
                return $fixed;
            }
        }

        return $name;
    }
}
