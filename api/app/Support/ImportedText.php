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
    private const NEWSROOM_CODE = '/[ \t]*\(TK\s*KBS,[^()<>]*\)(?:[ \t]*\d{8,})?/u';

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
