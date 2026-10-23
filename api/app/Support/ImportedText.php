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
