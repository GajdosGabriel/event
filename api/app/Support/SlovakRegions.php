<?php

namespace App\Support;

/**
 * Názvy vyšších územných celkov (samosprávnych krajov) k stĺpcu
 * `municipalities.region_id`. Číselník je súčasťou importovaného dumpu
 * `database/municipalities.sql` a v DB k nemu neexistuje tabuľka s názvami —
 * držíme ich teda tu, na jednom mieste, aj pre PHP aj pre SQL.
 *
 * Hodnota 9 nie je kraj: pod ňu patrí jediná pseudo-obec „Celé Slovensko“
 * používaná pre celoslovenské podujatia.
 */
class SlovakRegions
{
    public const NAMES = [
        1 => 'Banskobystrický kraj',
        2 => 'Bratislavský kraj',
        3 => 'Košický kraj',
        4 => 'Nitriansky kraj',
        5 => 'Prešovský kraj',
        6 => 'Trenčiansky kraj',
        7 => 'Trnavský kraj',
        8 => 'Žilinský kraj',
        9 => 'Celé Slovensko',
    ];

    public const FALLBACK = 'Ostatné';

    public static function name(?int $regionId): string
    {
        return self::NAMES[$regionId] ?? self::FALLBACK;
    }

    /**
     * SQL výraz, ktorý z čísla kraja spraví jeho názov priamo v dotaze —
     * aby sa názov dal vybrať bez ďalšieho spracovania nad výsledkom.
     */
    public static function caseExpression(string $column): string
    {
        $cases = '';

        foreach (self::NAMES as $id => $name) {
            $cases .= " WHEN {$id} THEN '".str_replace("'", "''", $name)."'";
        }

        return "(CASE {$column}{$cases} ELSE '".self::FALLBACK."' END)";
    }
}
